<?php

namespace App\Service;

use App\Entity\Reclamation;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ReclamationService
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function fillFromRequest(
        Reclamation $reclamation,
        Request $request,
        User $fournisseur
    ): void {
        $reclamation
            ->setFournisseur($fournisseur)
            ->setObjet(trim((string) $request->request->get('objet')))
            ->setDescription(
                trim((string) $request->request->get('description'))
            )
            ->setCategorie(
                (string) $request->request->get('categorie')
            )
            ->setPriorite(
                (string) $request->request->get('priorite')
            )
            ->setStatut(Reclamation::STATUT_OUVERTE);
    }

    public function isCsrfTokenValid(
        string $tokenId,
        mixed $tokenValue
    ): bool {
        return $this->csrfTokenManager->isTokenValid(
            new CsrfToken($tokenId, (string) $tokenValue)
        );
    }
}
