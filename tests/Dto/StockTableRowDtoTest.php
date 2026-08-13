<?php

namespace App\Tests\Dto;

use App\Dto\Analytics\StockTableRowDto;
use PHPUnit\Framework\TestCase;

final class StockTableRowDtoTest extends TestCase
{
    public function testObservedStockoutHasPriorityOverPrediction(): void
    {
        $row = new StockTableRowDto(
            42,
            91,
            'Masque Velours',
            '30 ml',
            20,
            20,
            0,
            0,
            true,
            'LOW',
            true,
            '2026-08-11 08:00:00+00',
        );

        self::assertSame('CURRENT_STOCKOUT', $row->toArray()['risk']);
        self::assertTrue($row->toArray()['hasPrediction']);
    }
}
