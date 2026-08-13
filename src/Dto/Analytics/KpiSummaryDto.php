<?php

namespace App\Dto\Analytics;

final readonly class KpiSummaryDto
{
    public function __construct(
        public int $orderCount,
        public int $cancelledOrderCount,
        public float $cancellationRate,
        public float $revenueHt,
        public float $revenueTtc,
        public int $orderedUnits,
        public float $averageOrderValueHt,
        public float $averageOrderValueTtc,
        public ?string $lastUpdatedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['order_count'],
            (int) $row['cancelled_order_count'],
            (float) $row['cancellation_rate'],
            (float) $row['revenue_ht'],
            (float) $row['revenue_ttc'],
            (int) $row['ordered_units'],
            (float) $row['average_order_value_ht'],
            (float) $row['average_order_value_ttc'],
            $row['last_updated_at'] !== null ? (string) $row['last_updated_at'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'orderCount' => $this->orderCount,
            'cancelledOrderCount' => $this->cancelledOrderCount,
            'cancellationRate' => $this->cancellationRate,
            'revenueHt' => $this->revenueHt,
            'revenueTtc' => $this->revenueTtc,
            'orderedUnits' => $this->orderedUnits,
            'averageOrderValueHt' => $this->averageOrderValueHt,
            'averageOrderValueTtc' => $this->averageOrderValueTtc,
            'lastUpdatedAt' => $this->lastUpdatedAt,
        ];
    }
}
