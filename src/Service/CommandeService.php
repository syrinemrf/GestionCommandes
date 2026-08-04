<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Commande;
use App\Entity\HistoriqueStatutCommande;
use App\Entity\LigneCommande;
use App\Entity\Parametre;
use App\Entity\Product;
use App\Entity\ProductVariation;
use App\Entity\User;
use App\Repository\ParametreRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class CommandeService
{
    private const TRANSITIONS_PAR_STATUT = [
        Commande::STATUT_EN_PREPARATION => 'confirmer',
        Commande::STATUT_PRETE => 'preparer',
        Commande::STATUT_EXPEDIEE => 'expedier',
        Commande::STATUT_EN_LIVRAISON => 'mettre_en_livraison',
        Commande::STATUT_LIVREE => 'livrer',
        Commande::STATUT_ANNULEE => 'annuler',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParametreRepository $parametreRepository,
        private ProductRepository $productRepository,
        private ProductVariationRepository $variationRepository,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private WorkflowInterface $commandeWorkflow,
        private StockMovementService $stockMovementService,
    ) {
    }

    public function isCsrfTokenValid(string $tokenId, mixed $tokenValue): bool
    {
        return $this->csrfTokenManager->isTokenValid(
            new CsrfToken($tokenId, (string) $tokenValue)
        );
    }

    public function saveFromRequest(
        Commande $commande,
        Request $request,
        User $actor,
        User $fournisseur,
        bool $isNew,
    ): void {
        if (!$isNew && !$commande->isModifiable()) {
            throw new \DomainException(
                'Une commande confirmée ne peut plus être modifiée.'
            );
        }

        $parametre = $this->getParametre();

        if ($isNew) {
            $commande
                ->setNumero($parametre->getNumeroCommande())
                ->setDate(new \DateTimeImmutable())
                ->setUser($actor)
                ->setIsDeleted(false);

            $parametre->setNumeroCommande(
                $parametre->getNumeroCommande() + 1
            );
        }

        $commande
            ->setFournisseur($fournisseur)
            ->setTauxTva($isNew ? $parametre->getTva() : $commande->getTauxTva())
            ->setNote($this->nullableValue($request->request->get('note')));

        $ancienStatut = $commande->getStatut();
        $statut = $isNew
            ? Commande::STATUT_EN_ATTENTE_CONFIRMATION
            : (string) $request->request->get('statut', $ancienStatut);
        $transition = $isNew || $statut === $ancienStatut
            ? null
            : $this->getEnabledTransition($commande, $statut);

        if (!$isNew && $this->statusReservesStock($ancienStatut)) {
            $this->stockMovementService->libererReservation(
                $commande,
                $actor
            );
        }

        if ($isNew) {
            $commande->setStatut(Commande::STATUT_EN_ATTENTE_CONFIRMATION);
        } elseif ($transition !== null) {
            $this->commandeWorkflow->apply($commande, $transition);
        }

        $this->fillClient($commande, $request, $fournisseur);
        $this->fillLignes($commande, $request, $fournisseur);

        if ($this->statusReservesStock($statut)) {
            $this->stockMovementService->reserverCommande(
                $commande,
                $actor
            );
        }

        if ($isNew) {
            $this->recordStatusChange(
                $commande,
                null,
                Commande::STATUT_EN_ATTENTE_CONFIRMATION,
                $actor
            );
        } elseif ($transition !== null) {
            $this->recordStatusChange(
                $commande,
                $ancienStatut,
                $commande->getStatut(),
                $actor
            );
        }

        $this->entityManager->persist($commande);
        $this->entityManager->flush();
    }

    public function softDelete(Commande $commande, User $actor): void
    {
        if ($this->statusReservesStock($commande->getStatut())) {
            $this->stockMovementService->libererReservation(
                $commande,
                $actor
            );
        }

        $commande->getClient()?->setIsDeleted(true);
        $commande->setIsDeleted(true);
        $this->entityManager->flush();
    }

    public function updateStatus(
        Commande $commande,
        string $statut,
        User $actor,
        ?\DateTimeImmutable $changedAt = null,
        bool $flush = true,
    ): void
    {
        $ancienStatut = $commande->getStatut();

        if ($statut === $ancienStatut) {
            return;
        }

        $transition = $this->getEnabledTransition($commande, $statut);

        if (
            $statut === Commande::STATUT_ANNULEE
            && $this->statusReservesStock($ancienStatut)
        ) {
            $this->stockMovementService->libererReservation(
                $commande,
                $actor,
                $changedAt,
            );
        }

        if ($statut === Commande::STATUT_LIVREE) {
            $this->stockMovementService->sortirCommande(
                $commande,
                $actor,
                $changedAt,
            );
        }

        $this->commandeWorkflow->apply($commande, $transition);
        $this->recordStatusChange(
            $commande,
            $ancienStatut,
            $commande->getStatut(),
            $actor,
            $changedAt,
        );
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function initializeNewCommande(
        Commande $commande,
        User $actor,
        ?\DateTimeImmutable $createdAt = null,
        bool $flush = true,
    ): void {
        if ($commande->getStatut() !== Commande::STATUT_EN_ATTENTE_CONFIRMATION) {
            throw new \DomainException('Une nouvelle commande doit être en attente de confirmation.');
        }

        $createdAt ??= $commande->getDate();
        $this->entityManager->persist($commande);
        $this->stockMovementService->reserverCommande($commande, $actor, $createdAt);
        $this->recordStatusChange(
            $commande,
            null,
            Commande::STATUT_EN_ATTENTE_CONFIRMATION,
            $actor,
            $createdAt,
        );

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function createLigne(
        Product $produit,
        ProductVariation $variation,
        int $quantite,
        User $fournisseur,
    ): LigneCommande {
        if (
            $produit->isDeleted()
            || $produit->getFournisseur()?->getId() !== $fournisseur->getId()
            || $variation->isDeleted()
            || $variation->getProduct()?->getId() !== $produit->getId()
        ) {
            throw new \DomainException('Le produit ou sa variation ne correspond pas au fournisseur.');
        }

        if ($quantite <= 0) {
            throw new \DomainException('La quantité doit être supérieure à zéro.');
        }

        $prixUnitaire = (float) $produit->getPrix()
            + (float) $variation->getPrixSupplement();

        return (new LigneCommande())
            ->setProduit($produit)
            ->setNomProduit((string) $produit->getLibelle())
            ->setVariation($variation)
            ->setNomVariation((string) $variation->getLibelle())
            ->setQuantite($quantite)
            ->setPrixUnitaire(number_format($prixUnitaire, 3, '.', ''));
    }

    public function recalculateTotal(Commande $commande): void
    {
        $totalHt = 0.0;

        foreach ($commande->getLignes() as $ligne) {
            $totalHt += (float) $ligne->getPrixUnitaire() * $ligne->getQuantite();
        }

        $commande->setTotalHt(number_format($totalHt, 3, '.', ''));
    }

    public function getAvailableStatuses(Commande $commande): array
    {
        $statuts = [$commande->getStatut()];

        foreach ($this->commandeWorkflow->getEnabledTransitions($commande) as $transition) {
            foreach ($transition->getTos() as $statut) {
                $statuts[] = $statut;
            }
        }

        return array_values(array_unique($statuts));
    }

    private function getParametre(): Parametre
    {
        $parametre = $this->parametreRepository->findOneBy([]);

        if (!$parametre) {
            throw new \LogicException(
                'Les paramètres de commande sont introuvables.'
            );
        }

        return $parametre;
    }

    private function fillClient(
        Commande $commande,
        Request $request,
        User $fournisseur,
    ): void {
        $data = [
            'nom' => $this->nullableValue($request->request->get('client_nom')),
            'prenom' => $this->nullableValue($request->request->get('client_prenom')),
            'societe' => $this->nullableValue($request->request->get('client_societe')),
            'telephone' => $this->nullableValue($request->request->get('client_telephone')),
            'email' => $this->nullableValue($request->request->get('client_email')),
            'rue' => $this->nullableValue($request->request->get('client_rue')),
            'complement' => $this->nullableValue($request->request->get('client_complement')),
            'ville' => $this->nullableValue($request->request->get('client_ville')),
            'codePostal' => $this->nullableValue($request->request->get('client_code_postal')),
        ];

        if (!array_filter($data, static fn (?string $value): bool => $value !== null)) {
            $commande->getClient()?->setIsDeleted(true);
            $commande->setClient(null);

            return;
        }

        if (
            $data['email'] !== null
            && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new \DomainException('L’adresse email du client est invalide.');
        }

        $client = $commande->getClient() ?? new Client();
        $client
            ->setFournisseur($fournisseur)
            ->setNom($data['nom'])
            ->setPrenom($data['prenom'])
            ->setSociete($data['societe'])
            ->setTelephone($data['telephone'])
            ->setEmail($data['email'])
            ->setRue($data['rue'])
            ->setComplementAdresse($data['complement'])
            ->setVille($data['ville'])
            ->setCodePostal($data['codePostal'])
            ->setIsDeleted(false);

        $this->entityManager->persist($client);
        $commande->setClient($client);
    }

    private function fillLignes(
        Commande $commande,
        Request $request,
        User $fournisseur,
    ): void {
        $produitIds = $request->request->all('produit');
        $variationIds = $request->request->all('variation');
        $quantites = $request->request->all('quantite');

        if ($produitIds === []) {
            throw new \DomainException(
                'Ajoutez au moins un produit à la commande.'
            );
        }

        foreach ($commande->getLignes()->toArray() as $ancienneLigne) {
            $commande->removeLigne($ancienneLigne);
        }

        $totalHt = 0.0;
        $quantitesParVariation = [];

        foreach ($produitIds as $index => $produitId) {
            $produit = $this->productRepository->find((int) $produitId);
            $variation = $this->variationRepository->find(
                (int) ($variationIds[$index] ?? 0)
            );
            $quantite = (int) ($quantites[$index] ?? 0);

            if (
                !$produit
                || $produit->isDeleted()
                || $produit->getFournisseur()?->getId() !== $fournisseur->getId()
            ) {
                throw new \DomainException(
                    'Un produit sélectionné est invalide.'
                );
            }

            if (
                !$variation
                || $variation->isDeleted()
                || $variation->getProduct()?->getId() !== $produit->getId()
            ) {
                throw new \DomainException(
                    'Une variation sélectionnée est invalide.'
                );
            }

            if ($quantite <= 0) {
                throw new \DomainException(
                    'La quantité doit être supérieure à zéro.'
                );
            }

            $variationId = (int) $variation->getId();
            $quantitesParVariation[$variationId] =
                ($quantitesParVariation[$variationId] ?? 0) + $quantite;
            $stockDisponible = $variation->getStockDisponible();

            if ($quantitesParVariation[$variationId] > $stockDisponible) {
                throw new \DomainException(
                    'Stock insuffisant pour ' . $variation->getLibelle() . '.'
                );
            }

            $ligne = $this->createLigne(
                $produit,
                $variation,
                $quantite,
                $fournisseur,
            );
            $commande->addLigne($ligne);
            $totalHt += (float) $ligne->getPrixUnitaire() * $quantite;
        }

        $commande->setTotalHt(number_format($totalHt, 3, '.', ''));
    }

    private function nullableValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function statusReservesStock(string $statut): bool
    {
        return in_array($statut, [
            Commande::STATUT_EN_ATTENTE_CONFIRMATION,
            Commande::STATUT_EN_PREPARATION,
            Commande::STATUT_PRETE,
            Commande::STATUT_EXPEDIEE,
            Commande::STATUT_EN_LIVRAISON,
        ], true);
    }

    private function getEnabledTransition(
        Commande $commande,
        string $statut
    ): string {
        $transition = self::TRANSITIONS_PAR_STATUT[$statut] ?? null;

        if (
            $transition === null
            || !$this->commandeWorkflow->can($commande, $transition)
        ) {
            throw new \DomainException(
                'Ce changement de statut n’est pas autorisé.'
            );
        }

        return $transition;
    }

    private function recordStatusChange(
        Commande $commande,
        ?string $ancienStatut,
        string $nouveauStatut,
        User $actor,
        ?\DateTimeImmutable $changedAt = null,
    ): void
    {
        $historique = (new HistoriqueStatutCommande())
            ->setCommande($commande)
            ->setAncienStatut($ancienStatut)
            ->setNouveauStatut($nouveauStatut)
            ->setChangedBy($actor)
            ->setChangedAt($changedAt ?? new \DateTimeImmutable());

        $this->entityManager->persist($historique);
    }
}
