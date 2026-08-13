<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\HomeActivityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class HomeController extends AbstractController
{
    public function index(HomeActivityService $homeActivity): Response
    {
        $user = $this->getUser();

        if ($user instanceof User) {
            return $this->render('home/authenticated.html.twig', [
                'home' => $homeActivity->build(
                    $user,
                    $this->isGranted('ROLE_ADMIN'),
                ),
            ]);
        }

        return $this->render('home/index.html.twig');
    }
}
