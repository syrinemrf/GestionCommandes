<?php

namespace App\Tests\DemoData;

use App\Service\CommandeService;
use App\Service\DemoData\DemandProfile;
use App\Service\DemoData\DemoCatalog;
use App\Service\DemoData\DemoDataGenerator;
use App\Service\DemoData\DemoDataOptions;
use App\Service\DemoData\DemoDataResetter;
use App\Service\StockMovementService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class DemoDataSafetyTest extends TestCase
{
    public function testGenerationIsRejectedInProductionEvenInDryRun(): void
    {
        $connection = $this->createMock(Connection::class);
        $generator = new DemoDataGenerator(
            $this->createMock(EntityManagerInterface::class),
            $connection,
            new DemoCatalog(dirname(__DIR__, 2).'/config/demo/catalog.yaml'),
            new DemoDataResetter($connection),
            new DemandProfile(),
            $this->createMock(CommandeService::class),
            $this->createMock(StockMovementService::class),
            'prod',
            sys_get_temp_dir(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('interdite en production');

        $generator->generate(new DemoDataOptions(
            1,
            new \DateTimeImmutable('2024-08-04'),
            new \DateTimeImmutable('2026-08-04 23:59:59'),
            'small',
            dryRun: true,
        ));
    }

    public function testBusinessGeneratorHasNoNetworkDependency(): void
    {
        $constructor = (new \ReflectionClass(DemoDataGenerator::class))->getConstructor();
        self::assertNotNull($constructor);

        foreach ($constructor->getParameters() as $parameter) {
            self::assertNotSame(
                \App\Service\DemoData\DemoImageRemoteClientInterface::class,
                (string) $parameter->getType(),
            );
        }
    }
}
