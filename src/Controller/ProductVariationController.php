<?php

namespace App\Controller;

use App\Entity\ProductVariation;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\ProductVariationRepository;
use App\Service\ProductVariationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProductVariationController extends AbstractController
{
    public function list(
        int $productId,
        Request $request,
        ProductRepository $productRepository,
        ProductVariationRepository $variationRepository,
    ): JsonResponse {
        $product = $productRepository->find($productId);

        if (!$product || $product->isDeleted()) {
            return $this->json(
                ['message' => 'Produit introuvable.'],
                Response::HTTP_NOT_FOUND
            );
        }

        $this->denyAccessUnlessProductOwner($product);

        $start = max(0, $request->query->getInt('start', 0));
        $length = min(100, max(1, $request->query->getInt('length', 5)));
        $search = trim((string) ($request->query->all('search')['value'] ?? ''));
        $order = $request->query->all('order')[0] ?? [];
        $orderColumn = (int) ($order['column'] ?? 0);
        $orderDirection = strtolower((string) ($order['dir'] ?? 'asc')) === 'desc'
            ? 'DESC'
            : 'ASC';
        $orderColumns = [
            0 => 'variation.libelle',
            2 => 'variation.reference',
            3 => 'variation.prixSupplement',
            4 => 'variation.prixSupplement',
            5 => 'variation.stock',
        ];

        $result = $variationRepository->findForDatatable(
            $product,
            $start,
            $length,
            $search,
            $orderColumns[$orderColumn] ?? 'variation.id',
            $orderDirection,
        );

        $rows = [];

        foreach ($result['rows'] as $variation) {
            $rows[] = [
                'id' => $variation->getId(),
                'libelle' => $variation->getLibelle(),
                'attributs' => $this->renderView(
                    'product/_variation_attributes.html.twig',
                    ['variation' => $variation]
                ),
                'reference' => $variation->getReference() ?: '—',
                'prixSupplement' => $variation->getPrixSupplement(),
                'prixFinal' => (float) $product->getPrix()
                    + (float) $variation->getPrixSupplement(),
                'stock' => $variation->getStock(),
                'actions' => $this->renderView(
                    'product/_variation_actions.html.twig',
                    ['variation' => $variation]
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

    public function add(
        int $productId,
        Request $request,
        ProductRepository $productRepository,
        ProductVariationRepository $variationRepository,
        ProductVariationService $variationService,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): JsonResponse {
        $product = $productRepository->find($productId);

        if (!$product || $product->isDeleted()) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Produit introuvable.',
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        $this->denyAccessUnlessProductOwner($product);

        if (!$variationService->isCsrfTokenValid(
            'product-variation-add-' . $product->getId(),
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

        $variation = new ProductVariation();
        $variation->setProduct($product);
        $variation->setIsDeleted(false);

        $variationService->fillFromRequest($variation, $request);
        $variationService->generateReference($variation);

        $errors = $validator->validate($variation);

        if (count($errors) > 0) {
            return $this->json(
                [
                    'success' => false,
                    'message' => $errors[0]->getMessage(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $entityManager->persist($variation);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Variation ajoutée avec succès.',
            'variationId' => $variation->getId(),
            'stockTotal' => $variationRepository->getTotalStock($product),
        ]);
    }

    public function edit(
        int $id,
        Request $request,
        ProductVariationRepository $variationRepository,
        ProductVariationService $variationService,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): JsonResponse {
        $variation = $variationRepository->find($id);

        if (!$variation || $variation->isDeleted()) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Variation introuvable.',
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        $product = $variation->getProduct();

        if (!$product || $product->isDeleted()) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Produit introuvable.',
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        $this->denyAccessUnlessProductOwner($product);

        if (!$variationService->isCsrfTokenValid(
            'product-variation-edit-' . $variation->getId(),
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

        $variationService->fillFromRequest($variation, $request);
        $variationService->generateReference($variation);

        $errors = $validator->validate($variation);

        if (count($errors) > 0) {
            return $this->json(
                [
                    'success' => false,
                    'message' => $errors[0]->getMessage(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Variation modifiée avec succès.',
            'variationId' => $variation->getId(),
            'stockTotal' => $variationRepository->getTotalStock($product),
        ]);
    }

    public function delete(
        int $id,
        Request $request,
        ProductVariationRepository $variationRepository,
        ProductVariationService $variationService,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $variation = $variationRepository->find($id);

        if (!$variation || $variation->isDeleted()) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Variation introuvable.',
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        $product = $variation->getProduct();

        if (!$product || $product->isDeleted()) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Produit introuvable.',
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        $this->denyAccessUnlessProductOwner($product);

        if (!$variationService->isCsrfTokenValid(
            'product-variation-delete-' . $variation->getId(),
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

        $variation->setIsDeleted(true);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Variation supprimée avec succès.',
            'variationId' => $variation->getId(),
            'stockTotal' => $variationRepository->getTotalStock($product),
        ]);
    }

    private function denyAccessUnlessProductOwner(
        \App\Entity\Product $product
    ): void {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        $user = $this->getUser();

        if (
            !$user instanceof User
            || $product->getFournisseur()?->getId() !== $user->getId()
        ) {
            throw $this->createAccessDeniedException(
                'Vous ne pouvez pas gérer les variations de ce produit.'
            );
        }
    }
}
