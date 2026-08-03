<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\ParametreRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariationRepository;
use App\Repository\UserRepository;
use App\Service\CommandeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CommandeController extends AbstractController
{
    public function list(
        Request $request,
        CommandeRepository $commandeRepository,
        UserRepository $userRepository,
    ): Response {
        $user = $this->getCurrentUser();

        if ($request->query->getBoolean('datatable')) {
            $start = max(0, $request->query->getInt('start', 0));
            $length = min(100, max(1, $request->query->getInt('length', 5)));
            $search = trim(
                (string) ($request->query->all('search')['value'] ?? '')
            );
            $isAdmin = $this->isGranted('ROLE_ADMIN');
            $fournisseur = $isAdmin ? null : $user;

            if ($isAdmin) {
                $fournisseurValue = trim(
                    (string) $request->query->get('fournisseur', '')
                );
                $fournisseurId = ctype_digit($fournisseurValue)
                    ? (int) $fournisseurValue
                    : 0;

                if ($fournisseurId > 0) {
                    $fournisseur = $userRepository->findOneBy([
                        'id' => $fournisseurId,
                        'role' => 'ROLE_FOURNISSEUR',
                        'isDeleted' => false,
                    ]);
                }
            }
            $statut = trim((string) $request->query->get('statut'));
            $statusLabels = $this->getStatusLabels();

            if ($statut === '' || !isset($statusLabels[$statut])) {
                $statut = null;
            }

            $dateFrom = $this->parseDateFilter(
                (string) $request->query->get('dateFrom')
            );
            $dateTo = $this->parseDateFilter(
                (string) $request->query->get('dateTo')
            );

            $result = $commandeRepository->findForDatatable(
                $start,
                $length,
                $search,
                $fournisseur,
                $statut,
                $dateFrom,
                $dateTo
            );
            $rows = [];

            foreach ($result['rows'] as $commande) {
                $client = $commande->getClient();
                $clientLabel = $client
                    ? trim(implode(' ', array_filter([
                        $client->getPrenom(),
                        $client->getNom(),
                    ])))
                    : '';

                if ($clientLabel === '' && $client?->getSociete()) {
                    $clientLabel = $client->getSociete();
                }

                $rows[] = [
                    'id' => $commande->getId(),
                    'numero' => sprintf('CMD-%06d', $commande->getNumero()),
                    'date' => $commande->getDate()->format('d/m/Y H:i'),
                    'client' => $clientLabel !== ''
                        ? $clientLabel
                        : 'Vente sans client',
                    'totalHt' => $commande->getTotalHt(),
                    'totalTtc' => $commande->getTotalTtc(),
                    'statut' => $this->renderView(
                        'commande/_status_select.html.twig',
                        [
                            'commande' => $commande,
                            'statuts' => $statusLabels,
                        ]
                    ),
                    'fournisseur' => $this->formatUser(
                        $commande->getFournisseur(),
                        true
                    ),
                    'createdBy' => $this->formatUser($commande->getUser()),
                    'actions' => $this->renderView(
                        'commande/_row_actions.html.twig',
                        ['commande' => $commande]
                    ),
                ];
            }

            return $this->json([
                'draw' => $request->query->getInt('draw', 1),
                'recordsTotal' => $result['total'],
                'recordsFiltered' => $result['filtered'],
                'data' => $rows,
            ]);
        }

        return $this->render('commande/list.html.twig', [
            'statuts' => $this->getStatusLabels(),
            'fournisseurs' => $this->isGranted('ROLE_ADMIN')
                ? $userRepository->findBy(
                    ['role' => 'ROLE_FOURNISSEUR', 'isDeleted' => false],
                    ['libelle' => 'ASC', 'nom' => 'ASC']
                )
                : [],
        ]);
    }

    public function products(
        Request $request,
        UserRepository $userRepository,
        ProductRepository $productRepository,
        ProductVariationRepository $variationRepository,
        CommandeRepository $commandeRepository,
    ): JsonResponse {
        $fournisseur = $this->resolveFournisseur(
            (int) $request->query->get('fournisseur', 0),
            $userRepository
        );
        $products = $productRepository->findBy([
            'fournisseur' => $fournisseur,
            'isDeleted' => false,
        ], ['libelle' => 'ASC']);
        $quantitesCommande = [];
        $commandeId = (int) $request->query->get('commande', 0);

        if ($commandeId > 0) {
            $commande = $this->getAccessibleCommande(
                $commandeId,
                $commandeRepository
            );

            if ($commande->getFournisseur()?->getId() !== $fournisseur->getId()) {
                throw $this->createAccessDeniedException();
            }

            foreach ($commande->getLignes() as $ligne) {
                $variationId = $ligne->getVariation()?->getId();

                if ($variationId !== null) {
                    $quantitesCommande[$variationId] =
                        ($quantitesCommande[$variationId] ?? 0)
                        + $ligne->getQuantite();
                }
            }
        }
        $data = [];

        foreach ($products as $product) {
            $variations = [];
            $activeVariations = $variationRepository
                ->findActiveByProduct($product);

            foreach ($activeVariations as $variation) {
                $variations[] = [
                    'id' => $variation->getId(),
                    'libelle' => $variation->getLibelle(),
                    'prix' => number_format(
                        (float) $product->getPrix()
                        + (float) $variation->getPrixSupplement(),
                        3,
                        '.',
                        ''
                    ),
                    'stockDisponible' => max(
                        0,
                        $variation->getStock() - $variation->getStockUtilise()
                        + ($quantitesCommande[$variation->getId()] ?? 0)
                    ),
                ];
            }

            if ($variations !== []) {
                $data[] = [
                    'id' => $product->getId(),
                    'libelle' => $product->getLibelle(),
                    'image' => $product->getImage(),
                    'variations' => $variations,
                    'isStandard' => count($activeVariations) === 1
                        && $activeVariations[0]->getAttributs() === [],
                ];
            }
        }

        return $this->json(['products' => $data]);
    }

    public function add(
        Request $request,
        CommandeService $commandeService,
        UserRepository $userRepository,
        ParametreRepository $parametreRepository,
    ): Response {
        if ($request->isMethod('GET')) {
            return $this->render('commande/add.html.twig', [
                'fournisseurs' => $this->isGranted('ROLE_ADMIN')
                    ? $userRepository->findBy([
                        'role' => 'ROLE_FOURNISSEUR',
                        'isDeleted' => false,
                    ])
                    : [],
                'tva' => $parametreRepository->findOneBy([])?->getTva()
                    ?? '0.000',
                'statuts' => $this->getStatusLabels(),
            ]);
        }

        if (!$commandeService->isCsrfTokenValid(
            'commande-add',
            $request->request->get('_token')
        )) {
            return $this->json(
                ['success' => false, 'message' => 'Votre session a expiré. Rechargez la page.'],
                Response::HTTP_FORBIDDEN
            );
        }

        try {
            $actor = $this->getCurrentUser();
            $fournisseur = $this->resolveFournisseur(
                $request->request->getInt('fournisseur'),
                $userRepository
            );
            $commande = new Commande();
            $commandeService->saveFromRequest(
                $commande,
                $request,
                $actor,
                $fournisseur,
                true
            );

            return $this->json([
                'success' => true,
                'message' => 'Commande ajoutée avec succès.',
            ]);
        } catch (\DomainException|\LogicException $exception) {
            return $this->json(
                ['success' => false, 'message' => $exception->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    public function details(
        int $id,
        CommandeRepository $commandeRepository,
    ): Response {
        return $this->render('commande/_details.html.twig', [
            'commande' => $this->getAccessibleCommande(
                $id,
                $commandeRepository
            ),
        ]);
    }

    public function updateStatus(
        int $id,
        Request $request,
        CommandeRepository $commandeRepository,
        CommandeService $commandeService,
    ): JsonResponse {
        $commande = $this->getAccessibleCommande($id, $commandeRepository);

        if (!$commandeService->isCsrfTokenValid(
            'commande-status-' . $commande->getId(),
            $request->request->get('_token')
        )) {
            return $this->json(
                ['success' => false, 'message' => 'Votre session a expiré. Rechargez la page.'],
                Response::HTTP_FORBIDDEN
            );
        }

        try {
            $commandeService->updateStatus(
                $commande,
                (string) $request->request->get('statut')
            );

            return $this->json([
                'success' => true,
                'message' => 'Statut de la commande mis à jour.',
            ]);
        } catch (\DomainException $exception) {
            return $this->json(
                ['success' => false, 'message' => $exception->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    public function edit(
        int $id,
        Request $request,
        CommandeRepository $commandeRepository,
        CommandeService $commandeService,
        UserRepository $userRepository,
    ): Response {
        $commande = $this->getAccessibleCommande($id, $commandeRepository);

        if ($request->isMethod('GET')) {
            $client = $commande->getClient();
            $lignes = [];

            foreach ($commande->getLignes() as $ligne) {
                $lignes[] = [
                    'produitId' => $ligne->getProduit()?->getId(),
                    'variationId' => $ligne->getVariation()?->getId(),
                    'quantite' => $ligne->getQuantite(),
                ];
            }

            return $this->render('commande/edit.html.twig', [
                'commande' => $commande,
                'client' => $client,
                'lignesInitiales' => $lignes,
                'fournisseurs' => $this->isGranted('ROLE_ADMIN')
                    ? $userRepository->findBy([
                        'role' => 'ROLE_FOURNISSEUR',
                        'isDeleted' => false,
                    ])
                    : [],
                'statuts' => $this->getStatusLabels(),
            ]);
        }

        if (!$commandeService->isCsrfTokenValid(
            'commande-edit-' . $commande->getId(),
            $request->request->get('_token')
        )) {
            return $this->json(
                ['success' => false, 'message' => 'Votre session a expiré. Rechargez la page.'],
                Response::HTTP_FORBIDDEN
            );
        }

        try {
            $fournisseur = $this->isGranted('ROLE_ADMIN')
                ? $this->resolveFournisseur(
                    $request->request->getInt('fournisseur'),
                    $userRepository
                )
                : $commande->getFournisseur();

            if (!$fournisseur) {
                throw new \DomainException('Le fournisseur est invalide.');
            }

            $commandeService->saveFromRequest(
                $commande,
                $request,
                $this->getCurrentUser(),
                $fournisseur,
                false
            );

            return $this->json([
                'success' => true,
                'message' => 'Commande modifiée avec succès.',
            ]);
        } catch (\DomainException|\LogicException $exception) {
            return $this->json(
                ['success' => false, 'message' => $exception->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    public function delete(
        int $id,
        Request $request,
        CommandeRepository $commandeRepository,
        CommandeService $commandeService,
    ): JsonResponse {
        $commande = $this->getAccessibleCommande($id, $commandeRepository);

        if (!$commandeService->isCsrfTokenValid(
            'commande-delete-' . $commande->getId(),
            $request->request->get('_token')
        )) {
            return $this->json(
                ['success' => false, 'message' => 'Votre session a expiré. Rechargez la page.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $commandeService->softDelete($commande);

        return $this->json([
            'success' => true,
            'message' => 'Commande supprimée avec succès.',
        ]);
    }

    private function getAccessibleCommande(
        int $id,
        CommandeRepository $commandeRepository,
    ): Commande {
        $commande = $commandeRepository->find($id);

        if (!$commande || $commande->isDeleted()) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        if (
            !$this->isGranted('ROLE_ADMIN')
            && $commande->getFournisseur()?->getId() !== $this->getCurrentUser()->getId()
        ) {
            throw $this->createAccessDeniedException(
                'Vous ne pouvez pas accéder à cette commande.'
            );
        }

        return $commande;
    }

    private function resolveFournisseur(
        int $id,
        UserRepository $userRepository,
    ): User {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->getCurrentUser();
        }

        $fournisseur = $userRepository->findOneBy([
            'id' => $id,
            'role' => 'ROLE_FOURNISSEUR',
            'isDeleted' => false,
        ]);

        if (!$fournisseur) {
            throw new \DomainException(
                'Le fournisseur sélectionné est invalide.'
            );
        }

        return $fournisseur;
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function formatUser(?User $user, bool $withLabel = false): string
    {
        if (!$user) {
            return '-';
        }

        $label = trim($user->getPrenom() . ' ' . $user->getNom());

        if ($withLabel && $user->getLibelle()) {
            $label .= ' (' . $user->getLibelle() . ')';
        }

        return $label;
    }

    private function getStatusLabels(): array
    {
        return [
            Commande::STATUT_EN_ATTENTE_CONFIRMATION => 'En attente de confirmation',
            Commande::STATUT_EN_PREPARATION => 'En préparation',
            Commande::STATUT_PRETE => 'Prête',
            Commande::STATUT_EXPEDIEE => 'Expédiée',
            Commande::STATUT_EN_LIVRAISON => 'En livraison',
            Commande::STATUT_ANNULEE => 'Annulée',
        ];
    }

    private function parseDateFilter(string $value): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            !$date
            || (
                is_array($errors)
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)
            )
        ) {
            return null;
        }

        return $date;
    }
}
