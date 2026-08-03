<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\User;
use App\Repository\CommandeRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CommandeAccessService
{
    public function __construct(
        private CommandeRepository $commandeRepository,
    ) {
    }

    public function getAccessibleCommande(
        int $id,
        User $user,
        bool $isAdmin,
    ): Commande {
        $commande = $this->commandeRepository->find($id);

        if (!$commande || $commande->isDeleted()) {
            throw new NotFoundHttpException('Commande introuvable.');
        }

        if (
            !$isAdmin
            && $commande->getFournisseur()?->getId() !== $user->getId()
        ) {
            throw new AccessDeniedHttpException(
                'Vous ne pouvez pas accéder à cette commande.'
            );
        }

        return $commande;
    }
}
