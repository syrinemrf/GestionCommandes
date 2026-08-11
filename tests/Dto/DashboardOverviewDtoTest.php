<?php

namespace App\Tests\Dto;

use App\Dto\Analytics\AnalyticsDateRange;
use App\Dto\Analytics\DashboardOverviewDto;
use App\Dto\Analytics\KpiSummaryDto;
use App\Dto\Analytics\OrderProcessingTimeDto;
use PHPUnit\Framework\TestCase;

final class DashboardOverviewDtoTest extends TestCase
{
    public function testItCalculatesBusinessComparisons(): void
    {
        $overview = new DashboardOverviewDto(
            $this->kpis(120, 900, 50, .08),
            $this->processing(7200),
            $this->kpis(100, 1000, 40, .05),
            $this->processing(3600),
        );

        $comparison = $overview->toArray()['comparison'];
        self::assertSame(20.0, $comparison['orderCountPercent']);
        self::assertSame(-10.0, $comparison['revenueHtPercent']);
        self::assertSame(25.0, $comparison['averageOrderValueHtPercent']);
        self::assertSame(3.0, $comparison['cancellationRatePoints']);
        self::assertSame(100.0, $comparison['processingTimePercent']);
        self::assertSame(3600.0, $comparison['processingTimeSecondsDelta']);
    }

    public function testItHandlesZeroAndMissingReferenceValues(): void
    {
        $overview = new DashboardOverviewDto(
            $this->kpis(10, 0, 0, 0),
            new OrderProcessingTimeDto(0, null, null, null, null, null),
            $this->kpis(0, 0, 0, 0),
            new OrderProcessingTimeDto(0, null, null, null, null, null),
        );

        $comparison = $overview->toArray()['comparison'];
        self::assertNull($comparison['orderCountPercent']);
        self::assertSame(0.0, $comparison['revenueHtPercent']);
        self::assertNull($comparison['processingTimePercent']);
        self::assertNull($comparison['processingTimeSecondsDelta']);
    }

    public function testPreviousPeriodHasExactlyTheSameDuration(): void
    {
        $previous = (new AnalyticsDateRange(
            new \DateTimeImmutable('2026-08-01'),
            new \DateTimeImmutable('2026-08-04'),
        ))->previous();

        self::assertSame(['from' => '2026-07-28', 'to' => '2026-07-31'], $previous->toArray());
    }

    private function kpis(int $orders, float $revenue, float $basket, float $rate): KpiSummaryDto
    {
        return new KpiSummaryDto($orders, 0, $rate, $revenue, 0, 0, $basket, 0, null);
    }

    private function processing(float $seconds): OrderProcessingTimeDto
    {
        return new OrderProcessingTimeDto(10, $seconds, $seconds, $seconds * .7, $seconds * .3, null);
    }
}
