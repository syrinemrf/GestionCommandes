<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class UserService
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function isCsrfTokenValid(string $tokenId, mixed $tokenValue): bool
    {
        return $this->csrfTokenManager->isTokenValid(
            new CsrfToken($tokenId, (string) $tokenValue)
        );
    }

    public function fillFromRequest(User $user, Request $request): void
    {
        $role = (string) $request->request->get('role');

        $user->setNom(trim((string) $request->request->get('nom')));
        $user->setPrenom(trim((string) $request->request->get('prenom')));
        $user->setEmail(trim((string) $request->request->get('email')));
        $user->setRole($role);
        $user->setLibelle(
            $role === 'ROLE_FOURNISSEUR'
                ? trim((string) $request->request->get('libelle'))
                : null
        );
    }
}
