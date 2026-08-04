<?php

namespace App\Service\DemoData;

final readonly class DemoDataOptions
{
    public function __construct(
        public int $seed,
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
        public string $scale,
        public bool $dryRun = false,
        public bool $resetDemo = false,
    ) {
        if (!in_array($scale, ['small', 'medium'], true)) {
            throw new \InvalidArgumentException('L’échelle doit être "small" ou "medium".');
        }

        if ($startDate > $endDate) {
            throw new \InvalidArgumentException('La date de début doit précéder la date de fin.');
        }
    }

    public function batchId(): string
    {
        return substr(hash('sha256', implode('|', [
            $this->seed,
            $this->startDate->format('Y-m-d'),
            $this->endDate->format('Y-m-d'),
            $this->scale,
        ])), 0, 32);
    }
}
