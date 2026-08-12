<?php

namespace App\Service\Analytics;

use App\Dto\Analytics\AnalyticsDateRange;
use App\Dto\Analytics\DashboardOverviewDto;
use App\Dto\Analytics\KpiSummaryDto;
use App\Dto\Analytics\StockRiskExplanationDto;
use App\Entity\User;
use App\Repository\Analytics\SupplierAnalyticsRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SupplierAnalyticsService
{
    public function __construct(
        private SupplierAnalyticsRepository $repository,
    ) {
    }

    public function summary(
        User $supplier,
        AnalyticsDateRange $range,
    ): KpiSummaryDto {
        return $this->repository->summary(
            $this->supplierId($supplier),
            $range
        );
    }

    public function overview(
        User $supplier,
        AnalyticsDateRange $range,
    ): DashboardOverviewDto {
        $supplierId = $this->supplierId($supplier);
        $previous = $range->previous();

        return new DashboardOverviewDto(
            $this->repository->summary($supplierId, $range),
            $this->repository->processingTime($supplierId, $range),
            $this->repository->summary($supplierId, $previous),
            $this->repository->processingTime($supplierId, $previous),
        );
    }

    public function evolution(User $supplier, AnalyticsDateRange $range): array
    {
        return $this->repository->evolution(
            $this->supplierId($supplier),
            $range
        );
    }

    public function productPerformance(
        User $supplier,
        AnalyticsDateRange $range,
        int $limit,
    ): array {
        return $this->repository->productPerformance(
            $this->supplierId($supplier),
            $range,
            $limit
        );
    }

    public function productPerformanceComparison(
        User $supplier,
        AnalyticsDateRange $range,
        int $limit,
    ): array {
        return $this->repository->productPerformanceComparison(
            $this->supplierId($supplier),
            $range,
            min(100, max(5, $limit)),
        );
    }

    public function orderStatuses(User $supplier): array
    {
        return $this->repository->orderStatuses(
            $this->supplierId($supplier)
        );
    }

    public function stockOverview(User $supplier, int $limit): array
    {
        return $this->repository->stockOverview(
            $this->supplierId($supplier),
            $limit
        );
    }

    public function stockDataTable(
        User $supplier,
        int $start,
        int $length,
        string $search,
        int $orderColumn,
        string $orderDirection,
    ): array {
        return $this->repository->stockDataTable(
            $this->supplierId($supplier),
            max(0, $start),
            min(100, max(5, $length)),
            mb_substr(trim($search), 0, 100),
            $orderColumn,
            strtolower($orderDirection) === 'asc' ? 'asc' : 'desc',
        );
    }

    public function stockRisks(
        User $supplier,
        ?string $risk,
        int $limit,
    ): array {
        $allowed = ['HIGH', 'MEDIUM', 'LOW', 'INSUFFICIENT_DATA'];
        if ($risk !== null && !in_array($risk, $allowed, true)) {
            throw new \InvalidArgumentException('Niveau de risque invalide.');
        }

        return $this->repository->stockRisks(
            $this->supplierId($supplier),
            $risk,
            $limit
        );
    }

    public function stockRiskExplanation(User $supplier, int $variationId): StockRiskExplanationDto
    {
        if ($variationId < 1) {
            throw new \InvalidArgumentException('Variation invalide.');
        }
        $explanation = $this->repository->stockRiskExplanation(
            $this->supplierId($supplier),
            $variationId
        );
        if ($explanation === null) {
            throw new NotFoundHttpException('Aucune prédiction disponible pour cette variation.');
        }

        return $explanation;
    }

    private function supplierId(User $supplier): int
    {
        if (
            $supplier->getRole() !== 'ROLE_FOURNISSEUR'
            || $supplier->isDeleted()
            || $supplier->getId() === null
        ) {
            throw new AccessDeniedHttpException(
                'Only an active supplier can access analytics.'
            );
        }

        return $supplier->getId();
    }
}
