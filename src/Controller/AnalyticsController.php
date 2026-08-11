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
        return $this->render('analytics/overview.html.twig');
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

    public function dashboardProducts(): Response
    {
        return $this->render('analytics/products.html.twig');
    }

    public function overview(
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
                ->overview($this->currentSupplier(), $range)
                ->toArray(),
            'meta' => [
                'period' => $range->toArray(),
                'previousPeriod' => $range->previous()->toArray(),
            ],
        ]);
    }

    public function stockTable(
        Request $request,
        SupplierAnalyticsService $analytics,
    ): JsonResponse {
        $search = $request->query->all('search');
        $orders = $request->query->all('order');
        $order = is_array($orders[0] ?? null) ? $orders[0] : [];
        $orderColumn = filter_var(
            $order['column'] ?? 4,
            FILTER_VALIDATE_INT,
        );
        $result = $analytics->stockDataTable(
            $this->currentSupplier(),
            $request->query->getInt('start', 0),
            $request->query->getInt('length', 10),
            is_string($search['value'] ?? null) ? $search['value'] : '',
            $orderColumn === false ? 4 : $orderColumn,
            ($order['dir'] ?? 'asc') === 'asc' ? 'asc' : 'desc',
        );

        return $this->json([
            'draw' => max(0, $request->query->getInt('draw', 0)),
            'recordsTotal' => $result['total'],
            'recordsFiltered' => $result['filtered'],
            'data' => array_map(
                static fn ($item): array => $item->toArray(),
                $result['rows'],
            ),
        ]);
    }

    public function risks(
        Request $request,
        SupplierAnalyticsService $analytics,
    ): JsonResponse {
        try {
            $limit = AnalyticsDateRange::limitFromRequest($request, 100, 200);
            $risk = $request->query->getString('risk') ?: null;
            $items = $analytics->stockRisks($this->currentSupplier(), $risk, $limit);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError($exception);
        }

        return $this->json([
            'data' => array_map(static fn ($item): array => $item->toArray(), $items),
            'meta' => ['count' => count($items), 'limit' => $limit, 'risk' => $risk],
        ]);
    }

    public function riskExplanation(
        int $variationId,
        SupplierAnalyticsService $analytics,
    ): JsonResponse {
        return $this->json([
            'data' => $analytics
                ->stockRiskExplanation($this->currentSupplier(), $variationId)
                ->toArray(),
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
