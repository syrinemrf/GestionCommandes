<?php

namespace App\Tests\Dto;

use App\Dto\Analytics\ProductPerformanceComparisonDto;
use PHPUnit\Framework\TestCase;

final class ProductPerformanceComparisonDtoTest extends TestCase
{
    public function testItCalculatesPositiveAndNegativeChanges(): void
    {
        $growth = $this->dto(112.0, 100.0, 84, 80)->toArray();
        $decline = $this->dto(92.0, 100.0, 60, 80)->toArray();

        self::assertSame(12.0, $growth['revenueChangePercent']);
        self::assertSame(5.0, $growth['unitsChangePercent']);
        self::assertSame(-8.0, $decline['revenueChangePercent']);
        self::assertSame(-25.0, $decline['unitsChangePercent']);
    }

    public function testItHandlesZeroReferenceWithoutDivisionByZero(): void
    {
        self::assertNull(
            $this->dto(100.0, 0.0, 10, 0)
                ->toArray()['revenueChangePercent']
        );
        self::assertSame(
            0.0,
            $this->dto(0.0, 0.0, 0, 0)
                ->toArray()['revenueChangePercent']
        );
    }

    private function dto(
        float $currentRevenue,
        float $previousRevenue,
        int $currentUnits,
        int $previousUnits,
    ): ProductPerformanceComparisonDto {
        return new ProductPerformanceComparisonDto(
            42,
            'Sérum HydraGlow',
            $currentRevenue,
            $previousRevenue,
            $currentUnits,
            $previousUnits,
            8,
            7,
            '2026-08-11 08:00:00+00',
        );
    }
}
