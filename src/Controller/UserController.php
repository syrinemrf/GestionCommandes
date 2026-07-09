<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserController extends AbstractController
{
    public function add(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
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

            return new Response();

        }

        $html = 'user/add.html.twig';

        return $this->render(
            $html,
            []
        );

    }
}