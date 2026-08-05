<?php

namespace App\Tests\Service;

use App\Dto\Analytics\AnalyticsDateRange;
use App\Dto\Analytics\KpiSummaryDto;
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
