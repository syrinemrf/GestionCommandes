<?php

namespace App\Service;

use App\Entity\ProductVariation;
use App\Repository\ProductVariationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProductVariationService
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
        private ProductVariationRepository $variationRepository,
        private SluggerInterface $slugger,
    ) {
    }

    public function fillFromRequest(
        ProductVariation $variation,
        Request $request
    ): void {
        $attributs = $this->extractAttributs($request);
        $libelle = trim((string) $request->request->get('libelle'));

        if ($libelle === '') {
            $libelle = $attributs === []
                ? (string) $variation->getProduct()?->getLibelle()
                : implode(' - ', array_values($attributs));
        }

        $variation->setLibelle($libelle);

        $variation->setPrixSupplement(
            str_replace(
                ',',
                '.',
                trim((string) $request->request->get('prixSupplement'))
            )
        );

        $variation->setStock(
            $request->request->getInt('stock')
        );

        $variation->setAttributs($attributs);
    }

    public function generateReference(ProductVariation $variation): void
    {
        if ($variation->getReference() !== null) {
            return;
        }

        $productId = $variation->getProduct()?->getId();

        if ($productId === null) {
            throw new \LogicException(
                'Le produit doit être enregistré avant de générer la référence.'
            );
        }

        $slug = strtoupper(
            $this->slugger->slug((string) $variation->getLibelle())->toString()
        );
        $slug = $slug !== '' ? $slug : 'STANDARD';
        $prefix = sprintf('PRD-%06d-', $productId);
        $baseReference = $prefix . mb_substr($slug, 0, 100 - strlen($prefix));
        $reference = $baseReference;
        $suffix = 2;

        while ($this->variationRepository->findOneBy(['reference' => $reference])) {
            $suffixText = '-' . str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
            $reference = mb_substr(
                $baseReference,
                0,
                100 - strlen($suffixText)
            ) . $suffixText;
            ++$suffix;
        }

        $variation->setReference($reference);
    }

    public function isCsrfTokenValid(
        string $tokenId,
        mixed $tokenValue
    ): bool {
        return $this->csrfTokenManager->isTokenValid(
            new CsrfToken($tokenId, (string) $tokenValue)
        );
    }

    private function extractAttributs(Request $request): array
    {
        $noms = $request->request->all('attribut_nom');
        $valeurs = $request->request->all('attribut_valeur');

        $attributs = [];
        $nomsUtilises = [];

        foreach ($noms as $index => $nom) {
            $nom = trim((string) $nom);
            $valeur = trim((string) ($valeurs[$index] ?? ''));

            if ($nom === '' || $valeur === '') {
                continue;
            }

            $nomNormalise = mb_strtolower($nom);

            if (isset($nomsUtilises[$nomNormalise])) {
                continue;
            }

            $nomsUtilises[$nomNormalise] = true;
            $attributs[$nom] = $valeur;
        }

        return $attributs;
    }
}
