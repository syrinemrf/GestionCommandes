<?php

namespace App\Dto\Analytics;

final readonly class DashboardOverviewDto
{
    public function __construct(
        public KpiSummaryDto $currentKpis,
        public OrderProcessingTimeDto $currentProcessing,
        public KpiSummaryDto $previousKpis,
        public OrderProcessingTimeDto $previousProcessing,
    ) {
    }

    public function toArray(): array
    {
        return [
            'current' => array_merge(
                $this->currentKpis->toArray(),
                $this->currentProcessing->toArray(),
            ),
            'previous' => array_merge(
                $this->previousKpis->toArray(),
                $this->previousProcessing->toArray(),
            ),
            'comparison' => [
                'orderCountPercent' => self::relativeChange(
                    $this->currentKpis->orderCount,
                    $this->previousKpis->orderCount,
                ),
                'revenueHtPercent' => self::relativeChange(
                    $this->currentKpis->revenueHt,
                    $this->previousKpis->revenueHt,
                ),
                'averageOrderValueHtPercent' => self::relativeChange(
                    $this->currentKpis->averageOrderValueHt,
                    $this->previousKpis->averageOrderValueHt,
                ),
                'cancellationRatePoints' => round(
                    ($this->currentKpis->cancellationRate - $this->previousKpis->cancellationRate) * 100,
                    2,
                ),
                'processingTimePercent' => self::nullableRelativeChange(
                    $this->currentProcessing->averageSeconds,
                    $this->previousProcessing->averageSeconds,
                ),
                'processingTimeSecondsDelta' => self::nullableDifference(
                    $this->currentProcessing->averageSeconds,
                    $this->previousProcessing->averageSeconds,
                ),
            ],
            'lastUpdatedAt' => $this->lastUpdatedAt(),
        ];
    }

    private static function relativeChange(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.000001) {
            return abs($current) < 0.000001 ? 0.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    private static function nullableRelativeChange(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        return self::relativeChange($current, $previous);
    }

    private static function nullableDifference(?float $current, ?float $previous): ?float
    {
        return $current !== null && $previous !== null
            ? round($current - $previous, 2)
            : null;
    }

    private function lastUpdatedAt(): ?string
    {
        $timestamps = array_filter([
            $this->currentKpis->lastUpdatedAt,
            $this->currentProcessing->lastUpdatedAt,
        ]);

        return $timestamps === [] ? null : max($timestamps);
    }
}
