<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Service\ProductImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProductController extends AbstractController
{
    public function list(): Response
    {
        return $this->render('product/list.html.twig');
    }

    public function add(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        ProductImageUploader $imageUploader
    ): Response {

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('product-add', $request->request->get('_token'))) {
                return new Response('invalid_csrf_token', 403);
            }

            $libelle = trim((string) $request->request->get('libelle'));
            $description = trim((string) $request->request->get('description'));
            $prix = str_replace(',', '.', trim((string) $request->request->get('prix')));

            if ($libelle === '' || $description === '' || !is_numeric($prix) || (float) $prix < 0) {
                return new Response('invalid_data', 422);
            }

            $product = new Product();

            $product->setLibelle($libelle);
            $product->setDescription($description);

            $imageFile = $request->files->get('image');

            if ($imageFile) {

                $imagePath = $imageUploader->upload($imageFile);

                if ($imagePath === null) {
                    return new Response('invalid_image');
                }

                $product->setImage($imagePath);

            }

            $product->setPrix($prix);

            if ($this->isGranted('ROLE_ADMIN')) {
                $fournisseur = $userRepository->findOneBy([
                    'id' => $request->request->getInt('fournisseur'),
                    'role' => 'ROLE_FOURNISSEUR',
                    'isDeleted' => false,
                ]);

                if (!$fournisseur) {
                    return new Response('invalid_fournisseur', 422);
                }
                $product->setFournisseur($fournisseur);
            } else {
                $user = $this->getUser();
                if (!$user instanceof \App\Entity\User || $user->getRole() !== 'ROLE_FOURNISSEUR') {
                    return new Response('forbidden', 403);
                }
                $product->setFournisseur($user);
            }

            $product->setIsDeleted(false);

            $entityManager->persist($product);
            $entityManager->flush();

            return new Response('success');
        }

        $fournisseurs = [];

        if ($this->isGranted('ROLE_ADMIN')) {
            $fournisseurs = $userRepository->findBy([
                'role' => 'ROLE_FOURNISSEUR',
                'isDeleted' => false,
            ]);
        }

        return $this->render('product/add.html.twig', [
            'fournisseurs' => $fournisseurs,
        ]);
    }

    public function edit(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        ProductRepository $productRepository,
        ProductImageUploader $imageUploader
    ): Response {

        $product = $productRepository->find($id);

        if (!$product) {
            return new Response('product_not_found', 404);
        }

        if (!$this->isGranted('ROLE_ADMIN') && $product->getFournisseur()?->getId() !== $this->getUser()?->getId()) {
            return new Response('forbidden', 403);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('product-edit-' . $product->getId(), $request->request->get('_token'))) {
                return new Response('invalid_csrf_token', 403);
            }

            $libelle = trim((string) $request->request->get('libelle'));
            $description = trim((string) $request->request->get('description'));
            $prix = str_replace(',', '.', trim((string) $request->request->get('prix')));

            if ($libelle === '' || $description === '' || !is_numeric($prix) || (float) $prix < 0) {
                return new Response('invalid_data', 422);
            }

            $product->setLibelle($libelle);
            $product->setDescription($description);
            
            $imageFile = $request->files->get('image');

            if ($imageFile) {

                $imagePath = $imageUploader->upload($imageFile);

                if ($imagePath === null) {
                    return new Response('invalid_image');
                }

                $product->setImage($imagePath);

            }

            $product->setPrix($prix);

            if ($this->isGranted('ROLE_ADMIN')) {
                $fournisseur = $userRepository->findOneBy([
                    'id' => $request->request->getInt('fournisseur'),
                    'role' => 'ROLE_FOURNISSEUR',
                    'isDeleted' => false,
                ]);

                if (!$fournisseur) {
                    return new Response('invalid_fournisseur', 422);
                }
                $product->setFournisseur($fournisseur);
            } else {
                $product->setFournisseur($this->getUser());
            }

            $entityManager->flush();

            return new Response('success');
        }

        $fournisseurs = [];

        if ($this->isGranted('ROLE_ADMIN')) {
            $fournisseurs = $userRepository->findBy([
                'role' => 'ROLE_FOURNISSEUR',
                'isDeleted' => false,
            ]);
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'fournisseurs' => $fournisseurs,
        ]);
    }

    public function data(
        Request $request,
        ProductRepository $productRepository
    ): JsonResponse {
        $start = $request->query->getInt('start', 0);
        $length = $request->query->getInt('length', 20);
        $search = $request->query->all('search')['value'] ?? '';

        $fournisseur = null;

        if (!$this->isGranted('ROLE_ADMIN')) {
            $fournisseur = $this->getUser();
        }

        $result = $productRepository->findForDatatable($start, $length, $search, $fournisseur);

        foreach ($result['rows'] as &$row) {
            $row['actions'] = $this->renderView('product/_row_actions.html.twig', [
                'product' => [
                    'id' => $row['id'],
                ],
            ]);
        }
        unset($row);

        return $this->json([
            'draw' => $request->query->getInt('draw', 1),
            'recordsTotal' => (int) $result['total'],
            'recordsFiltered' => (int) $result['filtered'],
            'data' => $result['rows'],
        ]);
    }

    public function delete(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        ProductRepository $productRepository
    ): Response {

        $product = $productRepository->find($id);

        if (!$product) {
            return new Response('product_not_found', 404);
        }

        if (!$this->isGranted('ROLE_ADMIN') && $product->getFournisseur()?->getId() !== $this->getUser()?->getId()) {
            return new Response('forbidden', 403);
        }

        if (!$this->isCsrfTokenValid('delete-product-' . $product->getId(), $request->request->get('_token'))) {
            return new Response('invalid_csrf_token', 403);
        }

        $product->setIsDeleted(true);
        $entityManager->flush();

        return new Response('success');
    }
}
