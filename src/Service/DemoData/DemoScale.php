<?php

namespace App\Service\DemoData;

final class DemoScale
{
    /** @return array{clients: int, orders: int, batch_size: int} */
    public static function configuration(string $scale): array
    {
        return match ($scale) {
            'small' => ['clients' => 30, 'orders' => 120, 'batch_size' => 40],
            'medium' => ['clients' => 200, 'orders' => 4800, 'batch_size' => 25],
            default => throw new \InvalidArgumentException('Échelle inconnue.'),
        };
    }
}
