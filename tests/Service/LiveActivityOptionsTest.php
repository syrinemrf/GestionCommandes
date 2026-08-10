<?php

namespace App\Tests\Service;

use App\Service\LiveActivity\LiveActivityOptions;
use App\Service\LiveActivity\LiveActivitySimulator;
use App\Service\CommandeService;
use App\Service\StockMovementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class LiveActivityOptionsTest extends TestCase
{
    public function testOptionsAcceptFiniteNonNegativeVolumes(): void
    {
        $options = new LiveActivityOptions(12, 7, 42, true);

        self::assertSame(12, $options->orders);
        self::assertSame(7, $options->statusUpdates);
        self::assertSame(42, $options->seed);
        self::assertTrue($options->dryRun);
    }

    public function testNegativeOrderCountIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LiveActivityOptions(-1, 0, 42);
    }

    public function testNegativeStatusUpdateCountIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LiveActivityOptions(0, -1, 42);
    }

    public function testOversizedBatchIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dépasser');

        new LiveActivityOptions(LiveActivityOptions::MAX_ORDERS + 1, 0, 42);
    }

    public function testSimulationIsAlwaysRejectedInProduction(): void
    {
        $simulator = new LiveActivitySimulator(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(CommandeService::class),
            $this->createMock(StockMovementService::class),
            'prod',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('production');

        $simulator->simulate(new LiveActivityOptions(0, 0, 42, true));
    }
}
