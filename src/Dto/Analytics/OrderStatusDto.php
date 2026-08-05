<?php

namespace App\Dto\Analytics;

final readonly class OrderStatusDto
{
    public function __construct(
        public string $status,
        public int $currentOrderCount,
        public float $currentOrderShare,
        public int $measuredTransitionCount,
        public ?float $averageTransitionDurationSeconds,
        public string $lastUpdatedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['order_status'],
            (int) $row['current_order_count'],
            (float) $row['current_order_share'],
            (int) $row['measured_transition_count'],
            $row['average_transition_duration_seconds'] !== null
                ? (float) $row['average_transition_duration_seconds']
                : null,
            (string) $row['last_updated_at'],
        );
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'currentOrderCount' => $this->currentOrderCount,
            'currentOrderShare' => $this->currentOrderShare,
            'measuredTransitionCount' => $this->measuredTransitionCount,
            'averageTransitionDurationSeconds' => $this->averageTransitionDurationSeconds,
            'lastUpdatedAt' => $this->lastUpdatedAt,
        ];
    }
}
