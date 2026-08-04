<?php

namespace App\Service\DemoData;

final class DemoDataResult
{
    /** @param array<string, int> $ordersByStatus */
    public function __construct(
        public string $batchId,
        public int $suppliers = 0,
        public int $clients = 0,
        public int $products = 0,
        public int $variations = 0,
        public int $orders = 0,
        public int $lines = 0,
        public int $movements = 0,
        public int $statusTransitions = 0,
        public ?\DateTimeImmutable $minDate = null,
        public ?\DateTimeImmutable $maxDate = null,
        public array $ordersByStatus = [],
        public int $stockoutVariations = 0,
        public float $durationSeconds = 0.0,
        public bool $alreadyExists = false,
        public bool $dryRun = false,
    ) {
    }
}
