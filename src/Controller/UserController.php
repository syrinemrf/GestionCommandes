<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserController extends AbstractController
{
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


    public function add(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response
    {

        if ($request->isMethod('POST')) {
            
            $user = new User();

            $user->setNom(
                $request->request->get('nom')
            );

            $user->setPrenom(
                $request->request->get('prenom')
            );

            $user->setEmail(
                $request->request->get('email')
            );

            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $request->request->get('password')
            );

            $user->setPassword($hashedPassword);

            $user->setRole(
                $request->request->get('role')
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
            $email = $request->request->get('email');
            $existingUser = $userRepository->findOneBy([
                'email' => $email
            ]);

            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                return new Response('email_exists');
            }

            $user->setNom(
                $request->request->get('nom')
            );

            $user->setPrenom(
                $request->request->get('prenom')
            );

            $user->setEmail($email);

            if ($request->request->get('password')) {
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $request->request->get('password')
                );

                $user->setPassword($hashedPassword);
            }

            $user->setRole(
                $request->request->get('role')
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

    public function list(
        Request $request,
        UserRepository $userRepository
    ): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $users = $userRepository->findBy(
        ['isDeleted' => false],
        ['id' => 'DESC'],
        $limit,
        $offset
    );

    $totalUsers = $userRepository->count([
        'isDeleted' => false
    ]);

        $totalPages = max(1, ceil($totalUsers / $limit));

        return $this->render(
            'user/list.html.twig',
            [
                'users' => $users,
                'page' => $page,
                'totalPages' => $totalPages
            ]
        );
    }

    public function delete(
        int $id,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository
    ): Response
    {
        $user = $userRepository->find($id);

        if (!$user) {
            return new Response('user_not_found');
        }

        $user->setIsDeleted(true);

        $entityManager->flush();

        return new Response('success');
    }
}