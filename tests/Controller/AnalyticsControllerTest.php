<?php

namespace App\Tests\Controller;

use App\Dto\Analytics\KpiSummaryDto;
use App\Entity\User;
use App\Service\Analytics\SupplierAnalyticsService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AnalyticsControllerTest extends WebTestCase
{
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
        $client->loginUser($this->supplier(101));
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

        $client->loginUser($this->supplier(101));
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

        $client->loginUser($this->supplier(101));
        $client->request(
            'GET',
            '/api/analytics/summary?supplierId=202&from=2026-08-01&to=2026-08-04'
        );
        self::assertResponseIsSuccessful();
        self::assertSame(
            101,
            $this->responseData($client->getResponse())['data']['orderCount']
        );

        $client->loginUser($this->supplier(202));
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

    private function supplier(int $id): User
    {
        $supplier = (new User())
            ->setEmail(sprintf('supplier-%d@example.test', $id))
            ->setRole('ROLE_FOURNISSEUR')
            ->setIsDeleted(false);
        (new \ReflectionProperty($supplier, 'id'))->setValue($supplier, $id);

        return $supplier;
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
