<?php

namespace App\Dto\Analytics;

final readonly class OrderProcessingTimeDto
{
    public function __construct(
        public int $completedOrderCount,
        public ?float $averageSeconds,
        public ?float $medianSeconds,
        public ?float $preparationToReadySeconds,
        public ?float $readyToShippedSeconds,
        public ?string $lastUpdatedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) ($row['completed_order_count'] ?? 0),
            self::nullableFloat($row['average_seconds'] ?? null),
            self::nullableFloat($row['median_seconds'] ?? null),
            self::nullableFloat($row['preparation_to_ready_seconds'] ?? null),
            self::nullableFloat($row['ready_to_shipped_seconds'] ?? null),
            isset($row['last_updated_at']) ? (string) $row['last_updated_at'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'completedOrderCount' => $this->completedOrderCount,
            'averageProcessingSeconds' => $this->averageSeconds,
            'medianProcessingSeconds' => $this->medianSeconds,
            'preparationToReadySeconds' => $this->preparationToReadySeconds,
            'readyToShippedSeconds' => $this->readyToShippedSeconds,
            'processingLastUpdatedAt' => $this->lastUpdatedAt,
        ];
    }

    private static function nullableFloat(mixed $value): ?float
    {
        return $value !== null ? (float) $value : null;
    }
}
