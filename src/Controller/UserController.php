<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class UserController extends AbstractController
{

    public function add(
        Request $request,
        EntityManagerInterface $entityManager
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

            $user->setPassword(
                $request->request->get('password')
            );

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


            return $this->redirectToRoute('user_add');

        }


        return $this->render('user/add.html.twig');
    }
}