<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
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
            return $this->json(['available' => false, 'message' => 'Cet email est déjà utilisé.']);
        }

        return $this->json(['available' => true]);
    }

    #[IsGranted('ROLE_ADMIN')]
    public function add(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
        UserService $userService,
    ): Response
    {

        if ($request->isMethod('POST')) {
            if (!$userService->isCsrfTokenValid('user-add', $request->request->get('_token'))) {
                return $this->json(['success' => false, 'message' => 'Votre session a expiré. Rechargez la page.'], Response::HTTP_FORBIDDEN);
            }

            $password = (string) $request->request->get('password');

            $user = new User();
            $userService->fillFromRequest($user, $request);

            $errors = $validator->validate($user);
            if (count($errors) > 0) {
                return $this->json(['success' => false, 'message' => $errors[0]->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $passwordErrors = $validator->validate($password, [
                new Assert\NotBlank(message: 'Le mot de passe est obligatoire.'),
                new Assert\Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
            ]);
            if (count($passwordErrors) > 0) {
                return $this->json(['success' => false, 'message' => $passwordErrors[0]->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $password
            );

            $user->setPassword($hashedPassword);

            $entityManager->persist($user);
            $entityManager->flush();

            return $this->json(['success' => true, 'message' => 'Utilisateur ajouté avec succès.']);
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
        UserRepository $userRepository,
        ValidatorInterface $validator,
        UserService $userService,
    ): Response
    {
        $user = $userRepository->find($id);

        if (!$user) {
            return new Response('user_not_found');
        }

        if ($request->isMethod('POST')) {
            if (!$userService->isCsrfTokenValid('user-edit-' . $user->getId(), $request->request->get('_token'))) {
                return $this->json(['success' => false, 'message' => 'Votre session a expiré. Rechargez la page.'], Response::HTTP_FORBIDDEN);
            }

            $userService->fillFromRequest($user, $request);

            $errors = $validator->validate($user);
            if (count($errors) > 0) {
                return $this->json(['success' => false, 'message' => $errors[0]->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($request->request->get('password')) {
                $passwordErrors = $validator->validate(
                    (string) $request->request->get('password'),
                    new Assert\Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.')
                );
                if (count($passwordErrors) > 0) {
                    return $this->json(['success' => false, 'message' => $passwordErrors[0]->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $request->request->get('password')
                );

                $user->setPassword($hashedPassword);
            }

            $entityManager->flush();

            return $this->json(['success' => true, 'message' => 'Utilisateur modifié avec succès.']);
        }

        return $this->render(
            'user/edit.html.twig',
            [
                'user' => $user
            ]
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    public function list(
        Request $request,
        UserRepository $userRepository,
    ): Response
    {
        if ($request->query->getBoolean('datatable')) {
            $start = $request->query->getInt('start', 0);
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
                    ['user' => ['id' => $row['id']]]
                );
            }
            unset($row);

            return $this->json([
                'draw' => $request->query->getInt('draw', 1),
                'recordsTotal' => (int) $result['total'],
                'recordsFiltered' => (int) $result['filtered'],
                'data' => $result['rows'],
            ]);
        }

        return $this->render(
            'user/list.html.twig',
            []
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserService $userService,
    ): Response
    {
        $user = $userRepository->find($id);

        if (!$user) {
            return new Response('user_not_found', 404);
        }

        if (!$userService->isCsrfTokenValid('delete-user-' . $user->getId(), $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Votre session a expiré. Rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        $user->setIsDeleted(true);

        $entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Utilisateur supprimé avec succès.']);
    }
}
