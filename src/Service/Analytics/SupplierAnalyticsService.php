<?php

namespace App\Service\Analytics;

use App\Dto\Analytics\AnalyticsDateRange;
use App\Dto\Analytics\KpiSummaryDto;
use App\Entity\User;
use App\Repository\Analytics\SupplierAnalyticsRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
