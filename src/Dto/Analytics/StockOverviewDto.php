<?php

namespace App\Dto\Analytics;

final readonly class StockOverviewDto
{
    public function __construct(
        public int $productId,
        public int $variationId,
        public string $productName,
        public ?string $variationName,
        public int $stockRegistered,
        public int $stockUsed,
        public int $stockReserved,
        public int $stockPhysical,
        public int $stockAvailable,
        public bool $currentlyOutOfStock,
        public bool $observedStockout,
        public ?string $lastMovementType,
        public ?int $lastMovementQuantity,
        public ?string $lastMovementAt,
        public bool $productDeleted,
        public bool $variationDeleted,
        public string $lastUpdatedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['source_product_id'],
            (int) $row['source_variation_id'],
            (string) $row['product_name'],
            $row['variation_name'] !== null ? (string) $row['variation_name'] : null,
            (int) $row['stock_registered'],
            (int) $row['stock_used'],
            (int) $row['stock_reserved'],
            (int) $row['stock_physical'],
            (int) $row['stock_available'],
            (bool) $row['is_currently_out_of_stock'],
            (bool) $row['has_observed_stockout'],
            $row['last_movement_type'] !== null ? (string) $row['last_movement_type'] : null,
            $row['last_movement_quantity'] !== null ? (int) $row['last_movement_quantity'] : null,
            $row['last_movement_at'] !== null ? (string) $row['last_movement_at'] : null,
            (bool) $row['product_is_deleted'],
            (bool) $row['variation_is_deleted'],
            (string) $row['last_updated_at'],
        );
    }

    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'variationId' => $this->variationId,
            'productName' => $this->productName,
            'variationName' => $this->variationName,
            'stockRegistered' => $this->stockRegistered,
            'stockUsed' => $this->stockUsed,
            'stockReserved' => $this->stockReserved,
            'stockPhysical' => $this->stockPhysical,
            'stockAvailable' => $this->stockAvailable,
            'currentlyOutOfStock' => $this->currentlyOutOfStock,
            'observedStockout' => $this->observedStockout,
            'lastMovementType' => $this->lastMovementType,
            'lastMovementQuantity' => $this->lastMovementQuantity,
            'lastMovementAt' => $this->lastMovementAt,
            'productDeleted' => $this->productDeleted,
            'variationDeleted' => $this->variationDeleted,
            'lastUpdatedAt' => $this->lastUpdatedAt,
        ];
    }
}
