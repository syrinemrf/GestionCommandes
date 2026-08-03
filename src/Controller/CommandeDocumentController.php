<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\CommandeAccessService;
use App\Service\CommandeDocumentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CommandeDocumentController extends AbstractController
{
    public function bonLivraison(
        int $id,
        CommandeAccessService $commandeAccessService,
        CommandeDocumentService $documentService,
    ): Response {
        $commande = $commandeAccessService->getAccessibleCommande(
            $id,
            $this->getCurrentUser(),
            $this->isGranted('ROLE_ADMIN')
        );

        try {
            return $documentService->createBonLivraisonResponse($commande);
        } catch (\DomainException $exception) {
            throw new ConflictHttpException(
                $exception->getMessage(),
                $exception
            );
        }
    }

    public function etiquetteColis(
        int $id,
        CommandeAccessService $commandeAccessService,
        CommandeDocumentService $documentService,
    ): Response {
        $commande = $commandeAccessService->getAccessibleCommande(
            $id,
            $this->getCurrentUser(),
            $this->isGranted('ROLE_ADMIN')
        );

        try {
            return $documentService->createEtiquetteColisResponse($commande);
        } catch (\DomainException $exception) {
            throw new ConflictHttpException(
                $exception->getMessage(),
                $exception
            );
        }
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
