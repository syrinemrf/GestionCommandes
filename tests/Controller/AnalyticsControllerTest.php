<?php

namespace App\Tests\Controller;

use App\Dto\Analytics\DashboardOverviewDto;
use App\Dto\Analytics\KpiSummaryDto;
use App\Dto\Analytics\OrderProcessingTimeDto;
use App\Dto\Analytics\ProductPerformanceComparisonDto;
use App\Dto\Analytics\StockRiskExplanationDto;
use App\Dto\Analytics\StockTableRowDto;
use App\Entity\User;
use App\Service\Analytics\SupplierAnalyticsService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class AnalyticsControllerTest extends WebTestCase
{
    private bool $testProviderConfigured = false;

    public function testAnonymousAccessReturnsJsonUnauthorized(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/analytics/summary');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame(
            'AUTHENTICATION_REQUIRED',
            $this->responseData($client->getResponse())['error']['code']
        );
    }

    public function testDashboardPagesRenderTheirBusinessSections(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->loginSupplier($client, $this->supplier(101));

        $crawler = $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#dashboard-evolution-chart'));
        self::assertCount(1, $crawler->filter('h2:contains("Commandes à traiter")'));
        self::assertCount(1, $crawler->filter('h2:contains("Performance des produits")'));
        self::assertSame(
            '/api/analytics/overview',
            $crawler->filter('#supplier-dashboard')->attr('data-overview-url')
        );
        self::assertCount(0, $crawler->filter('#dashboard-loader'));
        self::assertCount(4, $crawler->filter('.dashboard-section-loading'));
        self::assertSame(
            '/api/analytics/product-comparison',
            $crawler->filter('#supplier-dashboard')->attr('data-product-comparison-url'),
        );
        self::assertSame(
            '/commandes',
            $crawler->filter('#supplier-dashboard')->attr('data-orders-url'),
        );

        $crawler = $client->request('GET', '/dashboard/products');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#dashboard-stock-table'));
        self::assertCount(1, $crawler->filter('th:contains("Risque de rupture")'));
        self::assertCount(0, $crawler->filter('th:contains("Dernier mouvement")'));
        self::assertCount(1, $crawler->filter('#dashboard-stock-risk-dialog'));
        self::assertSame(
            '/api/analytics/stock-table',
            $crawler->filter('#supplier-products-dashboard')->attr('data-stock-table-url'),
        );
        self::assertCount(0, $crawler->filter('#dashboard-loader'));
        self::assertCount(1, $crawler->filter('.dashboard-section-loading'));

    }

    public function testPredictionsDashboardPageDoesNotExistAnymore(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->loginSupplier($client, $this->supplier(101));

        $client->request('GET', '/dashboard/predictions');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testOverviewUsesTheAuthenticatedSupplierAndReturnsComparison(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $analytics = $this->createMock(SupplierAnalyticsService::class);
        $analytics->expects(self::once())
            ->method('overview')
            ->with(self::callback(fn (User $user): bool => $user->getId() === 101))
            ->willReturn(new DashboardOverviewDto(
                $this->summary(12),
                new OrderProcessingTimeDto(4, 7200, 7000, 5000, 2200, null),
                $this->summary(10),
                new OrderProcessingTimeDto(3, 3600, 3500, 2500, 1100, null),
            ));
        static::getContainer()->set(SupplierAnalyticsService::class, $analytics);
        $this->loginSupplier($client, $this->supplier(101));

        $client->request('GET', '/api/analytics/overview?supplierId=202&from=2026-08-01&to=2026-08-04');

        self::assertResponseIsSuccessful();
        $data = $this->responseData($client->getResponse())['data'];
        self::assertEquals(20.0, $data['comparison']['orderCountPercent']);
        self::assertEquals(3600.0, $data['comparison']['processingTimeSecondsDelta']);
    }

    public function testInvalidDatesReturnBadRequest(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->loginSupplier($client, $this->supplier(101));
        $client->request(
            'GET',
            '/api/analytics/summary?from=2026-99-01&to=2026-08-04'
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame(
            'INVALID_QUERY_PARAMETERS',
            $this->responseData($client->getResponse())['error']['code']
        );
    }

    public function testStockDataTableUsesAuthenticatedSupplier(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $analytics = $this->createMock(SupplierAnalyticsService::class);
        $analytics->expects(self::once())
            ->method('stockDataTable')
            ->with(
                self::callback(fn (User $user): bool => $user->getId() === 101),
                0,
                10,
                'masque',
                4,
                'asc',
            )
            ->willReturn([
                'rows' => [new StockTableRowDto(
                    41, 91, 'Masque Velours', '30 ml',
                    20, 20, 0, 0, true, 'LOW', true,
                    '2026-08-11 08:00:00+00',
                )],
                'total' => 12,
                'filtered' => 1,
            ]);
        static::getContainer()->set(SupplierAnalyticsService::class, $analytics);
        $this->loginSupplier($client, $this->supplier(101));

        $client->request('GET', '/api/analytics/stock-table', [
            'draw' => 7,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => 'masque'],
            'order' => [['column' => 4, 'dir' => 'asc']],
            'supplierId' => 202,
        ]);

        self::assertResponseIsSuccessful();
        $data = $this->responseData($client->getResponse());
        self::assertSame(7, $data['draw']);
        self::assertSame(12, $data['recordsTotal']);
        self::assertSame(1, $data['recordsFiltered']);
        self::assertSame('CURRENT_STOCKOUT', $data['data'][0]['risk']);
    }

    public function testEmptyEvolutionReturnsAnEmptyArray(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $analytics = $this->createMock(SupplierAnalyticsService::class);
        $analytics->expects(self::once())->method('evolution')->willReturn([]);
        static::getContainer()->set(SupplierAnalyticsService::class, $analytics);

        $this->loginSupplier($client, $this->supplier(101));
        $client->request(
            'GET',
            '/api/analytics/evolution?from=2026-08-01&to=2026-08-04'
        );

        self::assertResponseIsSuccessful();
        $response = $this->responseData($client->getResponse());
        self::assertSame([], $response['data']);
        self::assertSame(0, $response['meta']['count']);
    }

    public function testProductComparisonUsesAuthenticatedSupplierAndPreviousPeriod(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $analytics = $this->createMock(SupplierAnalyticsService::class);
        $analytics->expects(self::once())
            ->method('productPerformanceComparison')
            ->with(
                self::callback(fn (User $user): bool => $user->getId() === 101),
                self::callback(fn ($range): bool => $range->toArray() === [
                    'from' => '2026-08-01',
                    'to' => '2026-08-04',
                ]),
                50,
            )
            ->willReturn([new ProductPerformanceComparisonDto(
                42, 'Sérum HydraGlow', 112, 100, 84, 80, 8, 7,
                '2026-08-11 08:00:00+00',
            )]);
        static::getContainer()->set(SupplierAnalyticsService::class, $analytics);
        $this->loginSupplier($client, $this->supplier(101));

        $client->request(
            'GET',
            '/api/analytics/product-comparison?supplierId=202&from=2026-08-01&to=2026-08-04',
        );

        self::assertResponseIsSuccessful();
        $response = $this->responseData($client->getResponse());
        self::assertEquals(12.0, $response['data'][0]['revenueChangePercent']);
        self::assertSame(
            ['from' => '2026-07-28', 'to' => '2026-07-31'],
            $response['meta']['previousPeriod'],
        );
    }

    public function testBrowserSupplierIdIsIgnoredForTwoSuppliers(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $seenSupplierIds = [];
        $analytics = $this->createMock(SupplierAnalyticsService::class);
        $analytics
            ->expects(self::exactly(2))
            ->method('summary')
            ->willReturnCallback(
                function (User $supplier) use (&$seenSupplierIds): KpiSummaryDto {
                    $seenSupplierIds[] = $supplier->getId();

                    return $this->summary((int) $supplier->getId());
                }
            );
        static::getContainer()->set(SupplierAnalyticsService::class, $analytics);

        $this->loginSupplier($client, $this->supplier(101));
        $client->request(
            'GET',
            '/api/analytics/summary?supplierId=202&from=2026-08-01&to=2026-08-04'
        );
        self::assertResponseIsSuccessful();
        self::assertSame(
            101,
            $this->responseData($client->getResponse())['data']['orderCount']
        );

        $this->loginSupplier($client, $this->supplier(202));
        $client->request(
            'GET',
            '/api/analytics/summary?supplierId=101&from=2026-08-01&to=2026-08-04'
        );
        self::assertResponseIsSuccessful();
        self::assertSame(
            202,
            $this->responseData($client->getResponse())['data']['orderCount']
        );
        self::assertSame([101, 202], $seenSupplierIds);
    }

    public function testRiskExplanationWithoutShapReturnsBusinessExplanation(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $analytics = $this->createMock(SupplierAnalyticsService::class);
        $analytics->expects(self::once())
            ->method('stockRiskExplanation')
            ->with(self::callback(fn (User $user): bool => $user->getId() === 101), 42)
            ->willReturn(new StockRiskExplanationDto(
                42, 'Produit démo', null, 3, 8.0, 12.0, 5,
                'HIGH', 9, [], 'model-v1', '2026-08-10T10:00:00+00:00'
            ));
        static::getContainer()->set(SupplierAnalyticsService::class, $analytics);

        $this->loginSupplier($client, $this->supplier(101));
        $client->request('GET', '/api/analytics/risks/42/explanation?supplierId=202');

        self::assertResponseIsSuccessful();
        $data = $this->responseData($client->getResponse())['data'];
        self::assertFalse($data['shapAvailable']);
        self::assertSame([], $data['factors']);
        self::assertSame(9, $data['recommendedQuantity']);
    }

    private function supplier(int $id): User
    {
        $supplier = (new User())
            ->setEmail(sprintf('supplier-%d@example.test', $id))
            ->setRole('ROLE_FOURNISSEUR')
            ->setIsDeleted(false);
        (new \ReflectionProperty($supplier, 'id'))->setValue($supplier, $id);

        return $supplier;
    }

    private function loginSupplier(KernelBrowser $client, User $supplier): void
    {
        if (!$this->testProviderConfigured) {
            $provider = $this->createMock(UserProviderInterface::class);
            $provider->method('supportsClass')->willReturn(true);
            $provider
                ->method('refreshUser')
                ->willReturnCallback(static fn (UserInterface $user): UserInterface => $user);
            static::getContainer()->set(
                'security.user.provider.concrete.app_user_provider',
                $provider
            );
            $this->testProviderConfigured = true;
        }
        $client->loginUser($supplier);
    }

    private function summary(int $orders): KpiSummaryDto
    {
        return new KpiSummaryDto(
            $orders,
            0,
            0.0,
            0.0,
            0.0,
            0,
            0.0,
            0.0,
            null,
        );
    }

    private function responseData(Response $response): array
    {
        return json_decode(
            (string) $response->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
