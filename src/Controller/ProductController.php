<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\ProductVariationRepository;
use App\Service\ProductImageUploader;
use App\Service\ProductService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProductController extends AbstractController
{
    public function list(
        Request $request,
        ProductRepository $productRepository,
    ): Response
    {
        if ($request->query->getBoolean('datatable')) {
            $start = $request->query->getInt('start', 0);
            $length = $request->query->getInt('length', 20);
            $search = $request->query->all('search')['value'] ?? '';

            $fournisseur = $this->isGranted('ROLE_ADMIN')
                ? null
                : $this->getUser();

            $result = $productRepository->findForDatatable(
                $start,
                $length,
                $search,
                $fournisseur,
            );

            foreach ($result['rows'] as &$row) {
                $row['actions'] = $this->renderView(
                    'product/_row_actions.html.twig',
                    ['product' => ['id' => $row['id']]]
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

        return $this->render('product/list.html.twig');
    }

    public function add(
        Request $request,
        EntityManagerInterface $entityManager,
        ProductService $productService,
        ProductImageUploader $imageUploader,
        ValidatorInterface $validator
    ): Response {

        if ($request->isMethod('POST')) {
            if (!$productService->isCsrfTokenValid('product-add', $request->request->get('_token'))) {
                return $this->json(['success' => false, 'message' => 'Votre session a expiré. Rechargez la page.'], Response::HTTP_FORBIDDEN);
            }

            $product = new Product();
            $productService->fillFromRequest($product, $request);

            $errors = $validator->validate($product);
            if (count($errors) > 0) {
                return $this->json(['success' => false, 'message' => $errors[0]->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($this->isGranted('ROLE_ADMIN')) {
                $fournisseur = $productService->findActiveFournisseur(
                    $request->request->getInt('fournisseur')
                );

                if (!$fournisseur) {
                    return $this->json(['success' => false, 'message' => 'Le fournisseur sélectionné est invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $product->setFournisseur($fournisseur);
            } else {
                $user = $this->getUser();
                if (!$user instanceof \App\Entity\User || $user->getRole() !== 'ROLE_FOURNISSEUR') {
                    throw $this->createAccessDeniedException('Vous ne pouvez pas ajouter de produit.');
                }
                $product->setFournisseur($user);
            }

            $imageFile = $request->files->get('image');

            if ($imageFile) {
                $imagePath = $imageUploader->upload(
                    $imageFile,
                    $product->getFournisseur()->getId(),
                );

                if ($imagePath === null) {
                    return $this->json(['success' => false, 'message' => 'L’image doit être au format JPEG, PNG ou WebP et ne pas dépasser 5 Mo.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $product->setImage($imagePath);
            }

            $product->setIsDeleted(false);

            $entityManager->persist($product);
            $entityManager->flush();

            return $this->json(['success' => true, 'message' => 'Produit ajouté avec succès.']);
        }

        $fournisseurs = [];

        if ($this->isGranted('ROLE_ADMIN')) {
            $fournisseurs = $productService->findActiveFournisseurs();
        }

        return $this->render('product/add.html.twig', [
            'fournisseurs' => $fournisseurs,
        ]);
    }

    public function edit(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        ProductService $productService,
        ProductRepository $productRepository,
        ProductVariationRepository $variationRepository,
        ProductImageUploader $imageUploader,
        ValidatorInterface $validator
    ): Response {

        $product = $productRepository->find($id);

        if (!$product) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        if (!$this->isGranted('ROLE_ADMIN') && $product->getFournisseur()?->getId() !== $this->getUser()?->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce produit.');
        }

        if ($request->isMethod('POST')) {
            if (!$productService->isCsrfTokenValid('product-edit-' . $product->getId(), $request->request->get('_token'))) {
                return $this->json(['success' => false, 'message' => 'Votre session a expiré. Rechargez la page.'], Response::HTTP_FORBIDDEN);
            }

            $productService->fillFromRequest($product, $request);

            $errors = $validator->validate($product);
            if (count($errors) > 0) {
                return $this->json(['success' => false, 'message' => $errors[0]->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($this->isGranted('ROLE_ADMIN')) {
                $fournisseur = $productService->findActiveFournisseur(
                    $request->request->getInt('fournisseur')
                );

                if (!$fournisseur) {
                    return $this->json(['success' => false, 'message' => 'Le fournisseur sélectionné est invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $product->setFournisseur($fournisseur);
            } else {
                $product->setFournisseur($this->getUser());
            }

            $oldImage = $product->getImage();

            if ($request->request->getBoolean('remove_image')) {
                $product->setImage(null);
            }

            $imageFile = $request->files->get('image');

            if ($imageFile) {
                $imagePath = $imageUploader->upload(
                    $imageFile,
                    $product->getFournisseur()->getId(),
                );

                if ($imagePath === null) {
                    return $this->json(['success' => false, 'message' => 'L’image doit être au format JPEG, PNG ou WebP et ne pas dépasser 5 Mo.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $product->setImage($imagePath);
                
            }

            $entityManager->flush();

            if ($oldImage && $oldImage !== $product->getImage()) {
                $imageUploader->delete($oldImage);
            }

            return $this->json([
                'success' => true,
                'message' => 'Produit modifié avec succès.',
                'redirectUrl' => $this->generateUrl('product_list'),
            ]);        
        }
        $fournisseurs = [];

        if ($this->isGranted('ROLE_ADMIN')) {
            $fournisseurs = $productService->findActiveFournisseurs();
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'fournisseurs' => $fournisseurs,
            'stockTotal' => $variationRepository->getTotalStock($product),
        ]);
    }

    public function delete(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        ProductRepository $productRepository,
        ProductService $productService,
    ): Response {

        $product = $productRepository->find($id);

        if (!$product) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        if (!$this->isGranted('ROLE_ADMIN') && $product->getFournisseur()?->getId() !== $this->getUser()?->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce produit.');
        }

        if (!$productService->isCsrfTokenValid('delete-product-' . $product->getId(), $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Votre session a expiré. Rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        $product->setIsDeleted(true);
        $entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Produit supprimé avec succès.']);
    }
}
