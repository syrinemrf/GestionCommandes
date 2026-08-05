<?php

namespace App\Controller;

use App\Dto\Analytics\AnalyticsDateRange;
use App\Entity\User;
use App\Service\Analytics\SupplierAnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_FOURNISSEUR')]
class AnalyticsController extends AbstractController
{
    public function dashboard(): Response
    {
        return $this->render('analytics/dashboard.html.twig');
    }

    public function summary(
        Request $request,
        SupplierAnalyticsService $analytics,
    ): JsonResponse {
        try {
            $range = AnalyticsDateRange::fromRequest($request);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError($exception);
        }

        return $this->json([
            'data' => $analytics
                ->summary($this->currentSupplier(), $range)
                ->toArray(),
            'meta' => ['period' => $range->toArray()],
        ]);
    }

    public function evolution(
        Request $request,
        SupplierAnalyticsService $analytics,
    ): JsonResponse {
        try {
            $range = AnalyticsDateRange::fromRequest($request);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError($exception);
        }

        $items = $analytics->evolution($this->currentSupplier(), $range);

        return $this->json([
            'data' => array_map(
                static fn ($item): array => $item->toArray(),
                $items
            ),
            'meta' => [
                'period' => $range->toArray(),
                'count' => count($items),
            ],
        ]);
    }

    public function products(
        Request $request,
        SupplierAnalyticsService $analytics,
    ): JsonResponse {
        try {
            $range = AnalyticsDateRange::fromRequest($request);
            $limit = AnalyticsDateRange::limitFromRequest($request, 50, 100);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError($exception);
        }

        $items = $analytics->productPerformance(
            $this->currentSupplier(),
            $range,
            $limit
        );

        return $this->json([
            'data' => array_map(
                static fn ($item): array => $item->toArray(),
                $items
            ),
            'meta' => [
                'period' => $range->toArray(),
                'count' => count($items),
                'limit' => $limit,
            ],
        ]);
    }

    public function statuses(
        SupplierAnalyticsService $analytics,
    ): JsonResponse {
        $items = $analytics->orderStatuses($this->currentSupplier());

        return $this->json([
            'data' => array_map(
                static fn ($item): array => $item->toArray(),
                $items
            ),
            'meta' => ['count' => count($items)],
        ]);
    }

    public function stock(
        Request $request,
        SupplierAnalyticsService $analytics,
    ): JsonResponse {
        try {
            $limit = AnalyticsDateRange::limitFromRequest($request, 100, 200);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError($exception);
        }

        $items = $analytics->stockOverview(
            $this->currentSupplier(),
            $limit
        );

        return $this->json([
            'data' => array_map(
                static fn ($item): array => $item->toArray(),
                $items
            ),
            'meta' => [
                'count' => count($items),
                'limit' => $limit,
            ],
        ]);
    }

    private function currentSupplier(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function validationError(
        \InvalidArgumentException $exception,
    ): JsonResponse {
        return $this->json([
            'error' => [
                'code' => 'INVALID_QUERY_PARAMETERS',
                'message' => $exception->getMessage(),
            ],
        ], Response::HTTP_BAD_REQUEST);
    }
}
