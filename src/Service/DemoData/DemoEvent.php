<?php

namespace App\Service\DemoData;

final class DemoEvent
{
    public int $sequence = 0;

    public function __construct(
        public string $type,
        public int $timestamp,
        public ?int $commandeId = null,
        public ?string $status = null,
        public ?int $variationId = null,
        public int $quantity = 0,
    ) {
    }
}
