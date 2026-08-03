<?php

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Service\CommandeAccessService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CommandeAccessServiceTest extends TestCase
{
    public function testUnFournisseurAccedeAUneCommandeQuiLuiAppartient(): void
    {
        $fournisseur = $this->createUser(10);
        $commande = (new Commande())->setFournisseur($fournisseur);
        $service = $this->createServiceReturning($commande);

        self::assertSame(
            $commande,
            $service->getAccessibleCommande(1, $fournisseur, false)
        );
    }

    public function testUnFournisseurNePeutPasAccederAUneAutreCommande(): void
    {
        $commande = (new Commande())
            ->setFournisseur($this->createUser(10));
        $service = $this->createServiceReturning($commande);

        $this->expectException(AccessDeniedHttpException::class);

        $service->getAccessibleCommande(
            1,
            $this->createUser(20),
            false
        );
    }

    public function testUnAdministrateurAccedeAUneCommandeFournisseur(): void
    {
        $commande = (new Commande())
            ->setFournisseur($this->createUser(10));
        $service = $this->createServiceReturning($commande);

        self::assertSame(
            $commande,
            $service->getAccessibleCommande(
                1,
                $this->createUser(20),
                true
            )
        );
    }

    public function testUneCommandeSupprimeeNestPasAccessible(): void
    {
        $commande = (new Commande())
            ->setFournisseur($this->createUser(10))
            ->setIsDeleted(true);
        $service = $this->createServiceReturning($commande);

        $this->expectException(NotFoundHttpException::class);

        $service->getAccessibleCommande(
            1,
            $this->createUser(10),
            false
        );
    }

    private function createServiceReturning(
        ?Commande $commande,
    ): CommandeAccessService {
        $repository = $this->createMock(CommandeRepository::class);
        $repository->method('find')->with(1)->willReturn($commande);

        return new CommandeAccessService($repository);
    }

    private function createUser(int $id): User
    {
        $user = new User();
        $property = new \ReflectionProperty($user, 'id');
        $property->setValue($user, $id);

        return $user;
    }
}
