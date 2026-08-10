<?php

namespace App\Service\LiveActivity;

final readonly class LiveActivityOptions
{
    public const MAX_ORDERS = 100;
    public const MAX_STATUS_UPDATES = 200;

    public function __construct(
        public int $orders,
        public int $statusUpdates,
        public int $seed,
        public bool $dryRun = false,
    ) {
        if ($orders < 0) {
            throw new \InvalidArgumentException('Le nombre de commandes doit être positif ou nul.');
        }
        if ($orders > self::MAX_ORDERS) {
            throw new \InvalidArgumentException(sprintf(
                'Le nombre de commandes ne peut pas dépasser %d par lot.',
                self::MAX_ORDERS,
            ));
        }

        if ($statusUpdates < 0) {
            throw new \InvalidArgumentException('Le nombre de mises à jour de statut doit être positif ou nul.');
        }
        if ($statusUpdates > self::MAX_STATUS_UPDATES) {
            throw new \InvalidArgumentException(sprintf(
                'Le nombre de mises à jour de statut ne peut pas dépasser %d par lot.',
                self::MAX_STATUS_UPDATES,
            ));
        }
    }
}
