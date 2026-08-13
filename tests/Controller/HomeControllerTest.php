<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Service\HomeActivityService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class HomeControllerTest extends WebTestCase
{
    public function testAnonymousUserSeesPublicLandingPage(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.public-header'));
        self::assertCount(1, $crawler->filter('#fonctionnalites'));
        self::assertCount(0, $crawler->filter('.sidebar'));
        self::assertCount(0, $crawler->filter('.authenticated-home'));
    }

    public function testAuthenticatedSupplierSeesInternalHome(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $supplier = (new User())
            ->setPrenom('Syrin')
            ->setNom('Fournisseur')
            ->setEmail('supplier-home@demo.comdely.test')
            ->setPassword('unused')
            ->setRole('ROLE_FOURNISSEUR')
            ->setLibelle('Atelier Démo');
        (new \ReflectionProperty($supplier, 'id'))->setValue($supplier, 101);

        $homeActivity = $this->createMock(HomeActivityService::class);
        $homeActivity->expects(self::once())
            ->method('build')
            ->with($supplier, false)
            ->willReturn([
                'activities' => [],
            ]);

        static::getContainer()->set(HomeActivityService::class, $homeActivity);
        $provider = $this->createMock(UserProviderInterface::class);
        $provider->method('supportsClass')->willReturn(true);
        $provider->method('refreshUser')->willReturnCallback(
            static fn (UserInterface $user): UserInterface => $user,
        );
        static::getContainer()->set(
            'security.user.provider.concrete.app_user_provider',
            $provider,
        );
        $client->loginUser($supplier);

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.sidebar'));
        self::assertCount(1, $crawler->filter('.navbar'));
        self::assertCount(1, $crawler->filter('.authenticated-home'));
        self::assertCount(1, $crawler->filter('h1:contains("Bonjour Syrin")'));
        self::assertCount(0, $crawler->filter('.public-header'));
        self::assertCount(0, $crawler->filter('#fonctionnalites'));
    }
}
