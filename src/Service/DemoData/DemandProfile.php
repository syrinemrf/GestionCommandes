<?php

namespace App\Service\DemoData;

final class DemandProfile
{
    public function weight(
        string $profile,
        \DateTimeImmutable $date,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): float {
        $totalDays = max(1, (int) $start->diff($end)->format('%a'));
        $elapsed = max(0, (int) $start->diff($date)->format('%a'));
        $progress = min(1.0, $elapsed / $totalDays);

        return match ($profile) {
            'low' => 0.45,
            'high' => 2.1,
            'intermittent' => ((int) $date->format('z') % 9 < 2) ? 1.4 : 0.08,
            'growing' => 0.45 + 2.2 * $progress,
            'declining' => 2.5 - 2.0 * $progress,
            'weekly' => in_array((int) $date->format('N'), [5, 6], true) ? 2.2 : 0.7,
            'annual' => in_array((int) $date->format('n'), [1, 6, 7, 8, 12], true) ? 2.0 : 0.65,
            'spiky' => ((int) $date->format('z') % 47 < 3) ? 4.5 : 0.55,
            default => 1.0,
        };
    }
}
