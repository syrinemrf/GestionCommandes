<?php

namespace App\Dto\Analytics;

final readonly class DailyKpiDto
{
    public function __construct(
        public string $date,
        public int $orderCount,
        public int $cancelledOrderCount,
        public float $revenueHt,
        public float $revenueTtc,
        public int $orderedUnits,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['calendar_date'],
            (int) $row['order_count'],
            (int) $row['cancelled_order_count'],
            (float) $row['revenue_ht'],
            (float) $row['revenue_ttc'],
            (int) $row['ordered_units'],
        );
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'orderCount' => $this->orderCount,
            'cancelledOrderCount' => $this->cancelledOrderCount,
            'revenueHt' => $this->revenueHt,
            'revenueTtc' => $this->revenueTtc,
            'orderedUnits' => $this->orderedUnits,
        ];
    }
}
