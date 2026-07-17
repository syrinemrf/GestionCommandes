<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ProductService
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UserRepository $userRepository,
    ) {
    }

    public function isCsrfTokenValid(string $tokenId, mixed $tokenValue): bool
    {
        return $this->csrfTokenManager->isTokenValid(
            new CsrfToken($tokenId, (string) $tokenValue)
        );
    }

    public function fillFromRequest(Product $product, Request $request): void
    {
        $product->setLibelle(trim((string) $request->request->get('libelle')));
        $product->setDescription(trim((string) $request->request->get('description')));
        $product->setPrix(
            str_replace(',', '.', trim((string) $request->request->get('prix')))
        );
    }

    public function findActiveFournisseur(int $id): ?User
    {
        return $this->userRepository->findOneBy([
            'id' => $id,
            'role' => 'ROLE_FOURNISSEUR',
            'isDeleted' => false,
        ]);
    }

    /** @return User[] */
    public function findActiveFournisseurs(): array
    {
        return $this->userRepository->findBy([
            'role' => 'ROLE_FOURNISSEUR',
            'isDeleted' => false,
        ]);
    }
}
