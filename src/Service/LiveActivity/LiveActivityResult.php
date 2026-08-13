<?php

namespace App\Service\LiveActivity;

final class LiveActivityResult
{
    /** @param array<string, int> $statusChanges */
    public function __construct(
        public int $seed,
        public int $requestedOrders,
        public int $createdOrders = 0,
        public int $createdLines = 0,
        public int $requestedStatusUpdates = 0,
        public int $updatedStatuses = 0,
        public int $replenishments = 0,
        public array $statusChanges = [],
        public ?\DateTimeImmutable $startsAt = null,
        public ?\DateTimeImmutable $endsAt = null,
        public bool $dryRun = false,
    ) {
    }
}
