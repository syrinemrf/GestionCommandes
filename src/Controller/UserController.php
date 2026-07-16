<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
class UserController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    public function checkEmail(
        Request $request,
        UserRepository $userRepository
    ): Response
    {
        $email = $request->request->get('email');
        $id = $request->request->get('id');

        $existingUser = $userRepository->findOneBy([
            'email' => $email
        ]);

        if ($existingUser && $existingUser->getId() != $id) {
            return new Response('email_exists');
        }

        return new Response('email_available');
    }

    #[IsGranted('ROLE_ADMIN')]
    public function add(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
    ): Response
    {

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('user-add', $request->request->get('_token'))) {
                return new Response('invalid_csrf_token', 403);
            }

            $nom = trim((string) $request->request->get('nom'));
            $prenom = trim((string) $request->request->get('prenom'));
            $email = trim((string) $request->request->get('email'));
            $password = (string) $request->request->get('password');
            $role = (string) $request->request->get('role');

            if ($nom === '' || $prenom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return new Response('invalid_data', 422);
            }

            if (strlen($password) < 8) {
                return new Response('invalid_password', 422);
            }

            if (!in_array($role, ['ROLE_ADMIN', 'ROLE_FOURNISSEUR'], true)) {
                return new Response('invalid_role', 422);
            }

            if ($role === 'ROLE_FOURNISSEUR' && trim((string) $request->request->get('libelle')) === '') {
                return new Response('invalid_libelle', 422);
            }

            if ($userRepository->findOneBy(['email' => $email])) {
                return new Response('email_exists', 409);
            }
            
            $user = new User();

            $user->setNom(
                $nom
            );

            $user->setPrenom(
                $prenom
            );

            $user->setEmail(
                $email
            );

            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $password
            );

            $user->setPassword($hashedPassword);

            $user->setRole(
                $role
            );

            if ($user->getRole() === 'ROLE_FOURNISSEUR') {

                $user->setLibelle(
                    $request->request->get('libelle')
                );

            }

            $entityManager->persist($user);
            $entityManager->flush();

            return new Response('success');
        }

        $html = 'user/add.html.twig';

        return $this->render(
            $html,
            []
        );

    }

    #[IsGranted('ROLE_ADMIN')]
    public function edit(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository
    ): Response
    {
        $user = $userRepository->find($id);

        if (!$user) {
            return new Response('user_not_found');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('user-edit-' . $user->getId(), $request->request->get('_token'))) {
                return new Response('invalid_csrf_token', 403);
            }

            $email = $request->request->get('email');
            $nom = trim((string) $request->request->get('nom'));
            $prenom = trim((string) $request->request->get('prenom'));
            $role = (string) $request->request->get('role');

            if ($nom === '' || $prenom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return new Response('invalid_data', 422);
            }

            if (!in_array($role, ['ROLE_ADMIN', 'ROLE_FOURNISSEUR'], true)) {
                return new Response('invalid_role', 422);
            }

            if ($role === 'ROLE_FOURNISSEUR' && trim((string) $request->request->get('libelle')) === '') {
                return new Response('invalid_libelle', 422);
            }
            $existingUser = $userRepository->findOneBy([
                'email' => $email
            ]);

            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                return new Response('email_exists');
            }

            $user->setNom(
                $nom
            );

            $user->setPrenom(
                $prenom
            );

            $user->setEmail($email);

            if ($request->request->get('password')) {
                if (strlen((string) $request->request->get('password')) < 8) {
                    return new Response('invalid_password', 422);
                }

                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $request->request->get('password')
                );

                $user->setPassword($hashedPassword);
            }

            $user->setRole(
                $role
            );

            if ($user->getRole() === 'ROLE_FOURNISSEUR') {
                $user->setLibelle(
                    $request->request->get('libelle')
                );
            } else {
                $user->setLibelle(null);
            }

            $entityManager->flush();

            return new Response('success');
        }

        return $this->render(
            'user/edit.html.twig',
            [
                'user' => $user
            ]
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    public function list(): Response
    {
        return $this->render(
            'user/list.html.twig',
            []
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    public function data(Request $request, UserRepository $userRepository): JsonResponse
    {
        $start  = $request->query->getInt('start', 0);
        $length = $request->query->getInt('length', 20);
        $search = $request->query->all('search')['value'] ?? '';

        $result = $userRepository->findForDatatable($start, $length, $search);

        foreach ($result['rows'] as &$row) {

            $row['role_badge'] = $this->renderView(
                'user/_role_badge.html.twig',
                [
                    'role_class' => $row['role'] === 'ROLE_ADMIN'
                        ? 'role-admin'
                        : 'role-fournisseur',

                    'role_label' => $row['role'] === 'ROLE_ADMIN'
                        ? 'Administrateur'
                        : 'Fournisseur',
                ]
            );

            $row['actions'] = $this->renderView(
                'user/_row_actions.html.twig',
                [
                    'user' => [
                        'id' => $row['id']
                    ]
                ]
            );

        }
        unset($row);

        return $this->json([
            'draw' => $request->query->getInt('draw'),
            'recordsTotal' => $result['total'],
            'recordsFiltered' => $result['filtered'],
            'data' => $result['rows'],
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository
    ): Response
    {
        $user = $userRepository->find($id);

        if (!$user) {
            return new Response('user_not_found', 404);
        }

        if (!$this->isCsrfTokenValid('delete-user-' . $user->getId(), $request->request->get('_token'))) {
            return new Response('invalid_csrf_token', 403);
        }

        $user->setIsDeleted(true);

        $entityManager->flush();

        return new Response('success');
    }
}
