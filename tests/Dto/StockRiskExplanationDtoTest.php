<?php

namespace App\Tests\Dto;

use App\Dto\Analytics\StockRiskExplanationDto;
use PHPUnit\Framework\TestCase;

final class StockRiskExplanationDtoTest extends TestCase
{
    public function testItTurnsModelFactorsIntoBusinessLanguage(): void
    {
        $dto = new StockRiskExplanationDto(
            91,
            'Masque Velours',
            '30 ml',
            0,
            8.0,
            12.0,
            8,
            'HIGH',
            12,
            [[
                'name' => 'rolling_sum_7d',
                'value' => 42,
                'contribution' => 1.284,
                'direction' => 'INCREASES',
            ]],
            'model-v1',
            '2026-08-10T10:00:00+00:00',
            array_map(
                static fn (int $quantity): array => ['quantity' => $quantity],
                [1, 1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 2, 2, 2],
            ),
        );

        $data = $dto->toArray();

        self::assertSame(
            'Le niveau et le rythme des ventes récentes contribue à augmenter la prévision.',
            $data['insights'][0],
        );
        self::assertStringNotContainsString('1.284', $data['insights'][0]);
        self::assertStringStartsWith('Demande en hausse', $data['recentTrend']);
        self::assertSame('Réapprovisionner rapidement d’environ 12 unités.', $data['recommendedAction']);
    }

    public function testItHandlesMissingHistoryAndExplanation(): void
    {
        $data = (new StockRiskExplanationDto(
            91,
            'Masque Velours',
            null,
            3,
            0,
            0,
            0,
            'INSUFFICIENT_DATA',
            0,
            [],
            'model-v1',
            '2026-08-10T10:00:00+00:00',
        ))->toArray();

        self::assertFalse($data['shapAvailable']);
        self::assertSame([], $data['insights']);
        self::assertSame('Historique récent insuffisant', $data['recentTrend']);
        self::assertSame('Non disponible', $data['variability']);
    }
}
