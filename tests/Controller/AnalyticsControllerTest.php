<?php

namespace App\Tests\Controller;

use App\Dto\Analytics\KpiSummaryDto;
use App\Dto\Analytics\StockRiskExplanationDto;
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
