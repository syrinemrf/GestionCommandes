<?php

namespace App\Dto\Analytics;

final readonly class ProductPerformanceComparisonDto
{
    public function __construct(
        public int $productId,
        public string $productName,
        public float $currentRevenueHt,
        public float $previousRevenueHt,
        public int $currentUnitsSold,
        public int $previousUnitsSold,
        public int $currentOrderCount,
        public int $previousOrderCount,
        public string $lastUpdatedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['source_product_id'],
            (string) $row['product_name'],
            (float) $row['current_revenue_ht'],
            (float) $row['previous_revenue_ht'],
            (int) $row['current_units_sold'],
            (int) $row['previous_units_sold'],
            (int) $row['current_order_count'],
            (int) $row['previous_order_count'],
            (string) $row['last_updated_at'],
        );
    }

    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'productName' => $this->productName,
            'currentRevenueHt' => $this->currentRevenueHt,
            'previousRevenueHt' => $this->previousRevenueHt,
            'currentUnitsSold' => $this->currentUnitsSold,
            'previousUnitsSold' => $this->previousUnitsSold,
            'currentOrderCount' => $this->currentOrderCount,
            'previousOrderCount' => $this->previousOrderCount,
            'revenueChangePercent' => self::relativeChange(
                $this->currentRevenueHt,
                $this->previousRevenueHt,
            ),
            'unitsChangePercent' => self::relativeChange(
                $this->currentUnitsSold,
                $this->previousUnitsSold,
            ),
            'lastUpdatedAt' => $this->lastUpdatedAt,
        ];
    }

    private static function relativeChange(
        float|int $current,
        float|int $previous,
    ): ?float {
        if (abs((float) $previous) < 0.000001) {
            return abs((float) $current) < 0.000001 ? 0.0 : null;
        }

        return round(
            (((float) $current - (float) $previous) / (float) $previous) * 100,
            2,
        );
    }
}
