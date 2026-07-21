<?php

namespace App\Service;

use App\Entity\ProductVariation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ProductVariationService
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function fillFromRequest(
        ProductVariation $variation,
        Request $request
    ): void {
        $variation->setLibelle(
            trim((string) $request->request->get('libelle'))
        );

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

        $reference = trim(
            (string) $request->request->get('reference')
        );

        $variation->setReference(
            $reference !== '' ? $reference : null
        );

        $variation->setAttributs(
            $this->extractAttributs($request)
        );
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