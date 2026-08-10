<?php

namespace App\Tests\Service;

use App\Dto\Analytics\AnalyticsDateRange;
use App\Dto\Analytics\KpiSummaryDto;
use App\Dto\Analytics\StockRiskExplanationDto;
use App\Entity\User;
use App\Repository\Analytics\SupplierAnalyticsRepository;
use App\Service\Analytics\SupplierAnalyticsService;
use PHPUnit\Framework\TestCase;

final class SupplierAnalyticsServiceTest extends TestCase
{
    public function testTwoSuppliersUseTheirOwnMariaDbIdentifier(): void
    {
        $range = new AnalyticsDateRange(
            new \DateTimeImmutable('2026-08-01'),
            new \DateTimeImmutable('2026-08-04')
        );
        $seenSupplierIds = [];
        $repository = $this->createMock(SupplierAnalyticsRepository::class);
        $repository
            ->expects(self::exactly(2))
            ->method('summary')
            ->willReturnCallback(
                function (int $supplierId) use (&$seenSupplierIds): KpiSummaryDto {
                    $seenSupplierIds[] = $supplierId;

                    return $this->summary($supplierId === 101 ? 11 : 22);
                }
            );

        $service = new SupplierAnalyticsService($repository);

        self::assertSame(
            11,
            $service->summary($this->supplier(101), $range)->orderCount
        );
        self::assertSame(
            22,
            $service->summary($this->supplier(202), $range)->orderCount
        );
        self::assertSame([101, 202], $seenSupplierIds);
    }

    public function testRiskExplanationIsIsolatedForTwoSuppliers(): void
    {
        $seenSupplierIds = [];
        $repository = $this->createMock(SupplierAnalyticsRepository::class);
        $repository
            ->expects(self::exactly(2))
            ->method('stockRiskExplanation')
            ->willReturnCallback(function (int $supplierId, int $variationId) use (&$seenSupplierIds): StockRiskExplanationDto {
                $seenSupplierIds[] = $supplierId;

                return new StockRiskExplanationDto(
                    $variationId,
                    sprintf('Produit %d', $supplierId),
                    null,
                    4,
                    8.0,
                    12.0,
                    4,
                    'HIGH',
                    8,
                    [],
                    'model-v1',
                    '2026-08-10T10:00:00+00:00',
                );
            });
        $service = new SupplierAnalyticsService($repository);

        $first = $service->stockRiskExplanation($this->supplier(101), 501);
        $second = $service->stockRiskExplanation($this->supplier(202), 501);

        self::assertSame('Produit 101', $first->productName);
        self::assertSame('Produit 202', $second->productName);
        self::assertFalse($first->toArray()['shapAvailable']);
        self::assertSame([], $first->toArray()['factors']);
        self::assertSame([101, 202], $seenSupplierIds);
    }

    public function testInvalidRiskFilterIsRejectedBeforeQueryingTheDw(): void
    {
        $repository = $this->createMock(SupplierAnalyticsRepository::class);
        $repository->expects(self::never())->method('stockRisks');
        $service = new SupplierAnalyticsService($repository);

        $this->expectException(\InvalidArgumentException::class);
        $service->stockRisks($this->supplier(101), 'CRITICAL', 100);
    }

    private function supplier(int $id): User
    {
        $supplier = (new User())
            ->setEmail(sprintf('supplier-%d@example.test', $id))
            ->setRole('ROLE_FOURNISSEUR')
            ->setIsDeleted(false);
        (new \ReflectionProperty($supplier, 'id'))->setValue($supplier, $id);

        return $supplier;
    }

    private function summary(int $orders): KpiSummaryDto
    {
        return new KpiSummaryDto(
            $orders,
            0,
            0.0,
            0.0,
            0.0,
            0,
            0.0,
            0.0,
            null,
        );
    }
}
