<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Entity\ReclamationMessage;
use App\Entity\User;
use App\Repository\ReclamationRepository;
use App\Repository\UserRepository;
use App\Service\ReclamationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ReclamationController extends AbstractController
{
    public function index(
        ReclamationRepository $reclamationRepository,
    ): Response {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->render('support/list.html.twig', [
                'adminView' => true,
                'statuts' => $this->getStatusLabels(),
                'priorites' => $this->getPriorityLabels(),
                'categories' => $this->getCategoryLabels(),
            ]);
        }

        $fournisseur = $this->getCurrentUser();

        return $this->render('support/index.html.twig', [
            'statuts' => $this->getStatusLabels(),
            'categories' => $this->getCategoryLabels(),
            'recentes' => $reclamationRepository->findBy(
                ['fournisseur' => $fournisseur],
                ['updatedAt' => 'DESC'],
                3
            ),
            'nombreOuvertes' => $reclamationRepository
                ->countBySupplierAndStatuses($fournisseur, [
                    Reclamation::STATUT_OUVERTE,
                ]),
            'nombreEnCours' => $reclamationRepository
                ->countBySupplierAndStatuses($fournisseur, [
                    Reclamation::STATUT_EN_COURS,
                    Reclamation::STATUT_EN_ATTENTE_FOURNISSEUR,
                ]),
            'nombreResolues' => $reclamationRepository
                ->countBySupplierAndStatuses($fournisseur, [
                    Reclamation::STATUT_RESOLUE,
                    Reclamation::STATUT_FERMEE,
                ]),
        ]);
    }

    public function data(
        Request $request,
        ReclamationRepository $reclamationRepository,
    ): JsonResponse {
        $statuts = $this->getStatusLabels();
        $priorites = $this->getPriorityLabels();
        $categories = $this->getCategoryLabels();
        $statut = trim((string) $request->query->get('statut'));
        $priorite = trim((string) $request->query->get('priorite'));
        $categorie = trim((string) $request->query->get('categorie'));

        $statut = isset($statuts[$statut]) ? $statut : null;
        $priorite = isset($priorites[$priorite]) ? $priorite : null;
        $categorie = isset($categories[$categorie]) ? $categorie : null;
        $fournisseur = $this->isGranted('ROLE_ADMIN')
            ? null
            : $this->getCurrentUser();

        $result = $reclamationRepository->findForDatatable(
            max(0, $request->query->getInt('start', 0)),
            min(100, max(1, $request->query->getInt('length', 10))),
            trim((string) ($request->query->all('search')['value'] ?? '')),
            $fournisseur,
            $statut,
            $priorite,
            $categorie,
        );

        $rows = [];

        foreach ($result['rows'] as $reclamation) {
            $fournisseurTicket = $reclamation->getFournisseur();
            $adminAssigne = $reclamation->getAdminAssigne();

            $rows[] = [
                'id' => $reclamation->getId(),
                'numero' => sprintf('SUP-%06d', $reclamation->getId()),
                'objet' => $reclamation->getObjet(),
                'categorie' => $categories[$reclamation->getCategorie()]
                    ?? $reclamation->getCategorie(),
                'priorite' => $this->renderView(
                    'support/_badge.html.twig',
                    [
                        'type' => 'priorite',
                        'value' => $reclamation->getPriorite(),
                        'label' => $priorites[$reclamation->getPriorite()]
                            ?? $reclamation->getPriorite(),
                    ]
                ),
                'statut' => $this->renderView(
                    'support/_badge.html.twig',
                    [
                        'type' => 'statut',
                        'value' => $reclamation->getStatut(),
                        'label' => $statuts[$reclamation->getStatut()]
                            ?? $reclamation->getStatut(),
                    ]
                ),
                'updatedAt' => $reclamation
                    ->getUpdatedAt()
                    ->format('d/m/Y H:i'),
                'fournisseur' => $fournisseurTicket
                    ? trim(
                        $fournisseurTicket->getPrenom()
                        . ' '
                        . $fournisseurTicket->getNom()
                    )
                    : '—',
                'admin' => $adminAssigne
                    ? trim(
                        $adminAssigne->getPrenom()
                        . ' '
                        . $adminAssigne->getNom()
                    )
                    : 'Non assigné',
                'actions' => $this->renderView(
                    'support/_row_actions.html.twig',
                    ['reclamation' => $reclamation]
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

    public function myRequests(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('support_home');
        }

        return $this->render('support/list.html.twig', [
            'adminView' => false,
            'statuts' => $this->getStatusLabels(),
            'priorites' => $this->getPriorityLabels(),
            'categories' => $this->getCategoryLabels(),
        ]);
    }

    public function add(
        Request $request,
        ReclamationService $reclamationService,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): Response {
        $fournisseur = $this->getCurrentUser();

        if ($fournisseur->getRole() !== 'ROLE_FOURNISSEUR') {
            throw $this->createAccessDeniedException();
        }

        $categories = $this->getCategoryLabels();
        $priorites = $this->getPriorityLabels();

        if ($request->isMethod('POST')) {
            if (!$reclamationService->isCsrfTokenValid(
                'support-add',
                $request->request->get('_token')
            )) {
                return $this->json(
                    [
                        'success' => false,
                        'message' => 'Votre session a expiré. Rechargez la page.',
                    ],
                    Response::HTTP_FORBIDDEN
                );
            }

            $reclamation = new Reclamation();
            $reclamationService->fillFromRequest(
                $reclamation,
                $request,
                $fournisseur
            );

            $errors = $validator->validate($reclamation);

            if (count($errors) > 0) {
                return $this->json(
                    [
                        'success' => false,
                        'message' => $errors[0]->getMessage(),
                    ],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $entityManager->persist($reclamation);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Votre demande a été envoyée.',
                'redirectUrl' => $this->generateUrl(
                    'support_details',
                    ['id' => $reclamation->getId()]
                ),
            ]);
        }

        $categorie = (string) $request->query->get('categorie');

        return $this->render('support/add.html.twig', [
            'categories' => $categories,
            'priorites' => $priorites,
            'categorieSelectionnee' => isset($categories[$categorie])
                ? $categorie
                : Reclamation::CATEGORIE_AUTRE,
        ]);
    }

    public function details(
        int $id,
        ReclamationRepository $reclamationRepository,
        UserRepository $userRepository,
    ): Response {
        return $this->render('support/details.html.twig', [
            'reclamation' => $this->getAccessibleReclamation(
                $id,
                $reclamationRepository
            ),
            'statuts' => $this->getStatusLabels(),
            'priorites' => $this->getPriorityLabels(),
            'categories' => $this->getCategoryLabels(),
            'admins' => $this->isGranted('ROLE_ADMIN')
                ? $userRepository->findBy(
                    ['role' => 'ROLE_ADMIN', 'isDeleted' => false],
                    ['nom' => 'ASC']
                )
                : [],
        ]);
    }

    public function reply(
        int $id,
        Request $request,
        ReclamationRepository $reclamationRepository,
        ReclamationService $reclamationService,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): JsonResponse {
        $reclamation = $this->getAccessibleReclamation(
            $id,
            $reclamationRepository
        );

        if ($reclamation->getStatut() === Reclamation::STATUT_FERMEE) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Cette demande est fermée.',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (!$reclamationService->isCsrfTokenValid(
            'support-reply-' . $reclamation->getId(),
            $request->request->get('_token')
        )) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Votre session a expiré. Rechargez la page.',
                ],
                Response::HTTP_FORBIDDEN
            );
        }

        $auteur = $this->getCurrentUser();
        $message = new ReclamationMessage();
        $message
            ->setAuteur($auteur)
            ->setContenu(trim((string) $request->request->get('contenu')));
        $reclamation->addMessage($message);

        $errors = $validator->validate($message);

        if (count($errors) > 0) {
            return $this->json(
                [
                    'success' => false,
                    'message' => $errors[0]->getMessage(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            if ($reclamation->getAdminAssigne() === null) {
                $reclamation->setAdminAssigne($auteur);
            }

            if ($reclamation->getStatut() === Reclamation::STATUT_OUVERTE) {
                $reclamation->setStatut(Reclamation::STATUT_EN_COURS);
            }
        } elseif (in_array(
            $reclamation->getStatut(),
            [
                Reclamation::STATUT_EN_ATTENTE_FOURNISSEUR,
                Reclamation::STATUT_RESOLUE,
            ],
            true
        )) {
            $reclamation->setStatut(Reclamation::STATUT_EN_COURS);
            $reclamation->setResolvedAt(null);
        }

        $reclamation->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->persist($message);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Votre réponse a été envoyée.',
        ]);
    }

    public function update(
        int $id,
        Request $request,
        ReclamationRepository $reclamationRepository,
        ReclamationService $reclamationService,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $reclamation = $this->getAccessibleReclamation(
            $id,
            $reclamationRepository
        );

        if (!$reclamationService->isCsrfTokenValid(
            'support-update-' . $reclamation->getId(),
            $request->request->get('_token')
        )) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Votre session a expiré. Rechargez la page.',
                ],
                Response::HTTP_FORBIDDEN
            );
        }

        $statut = (string) $request->request->get('statut');
        $priorite = (string) $request->request->get('priorite');

        if (
            !isset($this->getStatusLabels()[$statut])
            || !isset($this->getPriorityLabels()[$priorite])
        ) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Les informations sélectionnées sont invalides.',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $adminId = $request->request->getInt('admin');
        $admin = $adminId > 0
            ? $userRepository->findOneBy([
                'id' => $adminId,
                'role' => 'ROLE_ADMIN',
                'isDeleted' => false,
            ])
            : null;

        if ($adminId > 0 && !$admin) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'L’administrateur sélectionné est invalide.',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $reclamation
            ->setStatut($statut)
            ->setPriorite($priorite)
            ->setAdminAssigne($admin)
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setResolvedAt(
                in_array(
                    $statut,
                    [
                        Reclamation::STATUT_RESOLUE,
                        Reclamation::STATUT_FERMEE,
                    ],
                    true
                )
                    ? ($reclamation->getResolvedAt() ?? new \DateTimeImmutable())
                    : null
            );

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'La demande a été mise à jour.',
        ]);
    }

    private function getAccessibleReclamation(
        int $id,
        ReclamationRepository $reclamationRepository,
    ): Reclamation {
        $reclamation = $reclamationRepository->find($id);

        if (!$reclamation) {
            throw $this->createNotFoundException('Demande introuvable.');
        }

        if (
            !$this->isGranted('ROLE_ADMIN')
            && $reclamation->getFournisseur()?->getId()
                !== $this->getCurrentUser()->getId()
        ) {
            throw $this->createAccessDeniedException();
        }

        return $reclamation;
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function getStatusLabels(): array
    {
        return [
            Reclamation::STATUT_OUVERTE => 'Ouverte',
            Reclamation::STATUT_EN_COURS => 'En cours',
            Reclamation::STATUT_EN_ATTENTE_FOURNISSEUR =>
                'En attente du fournisseur',
            Reclamation::STATUT_RESOLUE => 'Résolue',
            Reclamation::STATUT_FERMEE => 'Fermée',
        ];
    }

    private function getPriorityLabels(): array
    {
        return [
            Reclamation::PRIORITE_NORMALE => 'Normale',
            Reclamation::PRIORITE_IMPORTANTE => 'Importante',
            Reclamation::PRIORITE_URGENTE => 'Urgente',
        ];
    }

    private function getCategoryLabels(): array
    {
        return [
            Reclamation::CATEGORIE_COMMANDE => 'Commande',
            Reclamation::CATEGORIE_PRODUIT => 'Produit ou variation',
            Reclamation::CATEGORIE_STOCK => 'Stock',
            Reclamation::CATEGORIE_COMPTE => 'Compte et accès',
            Reclamation::CATEGORIE_TECHNIQUE => 'Problème technique',
            Reclamation::CATEGORIE_AUTRE => 'Autre demande',
        ];
    }
}
