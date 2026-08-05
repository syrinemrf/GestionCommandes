<?php

namespace App\Dto\Analytics;

final readonly class ProductPerformanceDto
{
    public function __construct(
        public string $periodStart,
        public int $productId,
        public int $variationId,
        public string $productName,
        public ?string $variationName,
        public int $unitsSold,
        public float $revenueHt,
        public float $revenueTtc,
        public int $orderCount,
        public int $productRank,
        public bool $productDeleted,
        public bool $variationDeleted,
        public string $lastUpdatedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['period_start'],
            (int) $row['source_product_id'],
            (int) $row['source_variation_id'],
            (string) $row['product_name'],
            $row['variation_name'] !== null ? (string) $row['variation_name'] : null,
            (int) $row['units_sold'],
            (float) $row['revenue_ht'],
            (float) $row['revenue_ttc'],
            (int) $row['order_count'],
            (int) $row['product_rank'],
            (bool) $row['product_is_deleted'],
            (bool) $row['variation_is_deleted'],
            (string) $row['last_updated_at'],
        );
    }

    public function toArray(): array
    {
        return [
            'periodStart' => $this->periodStart,
            'productId' => $this->productId,
            'variationId' => $this->variationId,
            'productName' => $this->productName,
            'variationName' => $this->variationName,
            'unitsSold' => $this->unitsSold,
            'revenueHt' => $this->revenueHt,
            'revenueTtc' => $this->revenueTtc,
            'orderCount' => $this->orderCount,
            'productRank' => $this->productRank,
            'productDeleted' => $this->productDeleted,
            'variationDeleted' => $this->variationDeleted,
            'lastUpdatedAt' => $this->lastUpdatedAt,
        ];
    }
}
