<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProductController extends AbstractController
{
    public function list(): Response
    {
        return $this->render('product/list.html.twig');
    }

    private function uploadImage(?UploadedFile $imageFile): ?string
    {
        if (!$imageFile || !$imageFile->isValid()) {
            return null;
        }

        $extension = $imageFile->guessExtension() ?? 'jpg';

        $newFilename =
            bin2hex(random_bytes(16))
            . '.'
            . $extension;
                
        if (!str_starts_with($imageFile->getMimeType(), 'image/')) {
            return null;
        }

        if ($imageFile->getSize() > 5 * 1024 * 1024) {
            return null;
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $imageFile->move($uploadDir, $newFilename);

        return 'uploads/products/' . $newFilename;
    }

    public function add(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository
    ): Response {

        if ($request->isMethod('POST')) {
            $product = new Product();

            $product->setLibelle($request->request->get('libelle'));
            $product->setDescription($request->request->get('description'));

            $imageFile = $request->files->get('image');

            if ($imageFile) {

                $imagePath = $this->uploadImage($imageFile);

                if ($imagePath === null) {
                    return new Response('invalid_image');
                }

                $product->setImage($imagePath);

            }

            $product->setPrix((float) $request->request->get('prix'));

            if ($this->isGranted('ROLE_ADMIN')) {
                $fournisseur = $userRepository->find($request->request->getInt('fournisseur'));
                $product->setFournisseur($fournisseur);
            } else {
                $product->setFournisseur($this->getUser());
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
        ProductRepository $productRepository
    ): Response {

        $product = $productRepository->find($id);

        if (!$product) {
            return new Response('product_not_found');
        }

        if (!$this->isGranted('ROLE_ADMIN') && $product->getFournisseur()?->getId() !== $this->getUser()?->getId()) {
            return new Response('forbidden', 403);
        }

        if ($request->isMethod('POST')) {
            $product->setLibelle($request->request->get('libelle'));
            $product->setDescription($request->request->get('description'));
            
            $imageFile = $request->files->get('image');

            if ($imageFile) {

                $imagePath = $this->uploadImage($imageFile);

                if ($imagePath === null) {
                    return new Response('invalid_image');
                }

                $product->setImage($imagePath);

            }

            $product->setPrix((float) $request->request->get('prix'));

            if ($this->isGranted('ROLE_ADMIN')) {
                $fournisseur = $userRepository->find($request->request->getInt('fournisseur'));
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

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        foreach ($result['rows'] as &$row) {
            if ($isAdmin) {
                $row['fournisseur'] =
                    $row['prenom']
                    . ' '
                    . $row['nom']
                    . ' ('
                    . $row['libelle']
                    . ')';

            }

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
        EntityManagerInterface $entityManager,
        ProductRepository $productRepository
    ): Response {

        $product = $productRepository->find($id);

        if (!$product) {
            return new Response('product_not_found');
        }

        if (!$this->isGranted('ROLE_ADMIN') && $product->getFournisseur()?->getId() !== $this->getUser()?->getId()) {
            return new Response('forbidden', 403);
        }

        $product->setIsDeleted(true);
        $entityManager->flush();

        return new Response('success');
    }
}