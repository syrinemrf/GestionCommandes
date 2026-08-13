<?php

namespace App\Dto\Analytics;

final readonly class StockTableRowDto
{
    public function __construct(
        public int $productId,
        public int $variationId,
        public string $productName,
        public ?string $variationName,
        public int $stockRegistered,
        public int $stockUsed,
        public int $stockReserved,
        public int $stockAvailable,
        public bool $currentlyOutOfStock,
        public string $risk,
        public bool $hasPrediction,
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
            (int) $row['stock_available'],
            (bool) $row['is_currently_out_of_stock'],
            (string) ($row['risk'] ?? 'INSUFFICIENT_DATA'),
            (bool) $row['has_prediction'],
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
            'stockAvailable' => $this->stockAvailable,
            'currentlyOutOfStock' => $this->currentlyOutOfStock,
            'risk' => $this->currentlyOutOfStock || $this->stockAvailable <= 0
                ? 'CURRENT_STOCKOUT'
                : $this->risk,
            'hasPrediction' => $this->hasPrediction,
            'lastUpdatedAt' => $this->lastUpdatedAt,
        ];
    }
}
