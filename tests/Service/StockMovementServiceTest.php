<?php

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\MouvementStock;
use App\Entity\ProductVariation;
use App\Entity\User;
use App\Service\StockMovementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StockMovementServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private StockMovementService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new StockMovementService($this->entityManager);
    }

    public function testReapprovisionnerAugmenteLeStockEtCreeUnMouvement(): void
    {
        $variation = $this->createVariation(10);
        $actor = new User();
        $mouvement = null;

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(
                function (object $entity) use (&$mouvement): bool {
                    $mouvement = $entity;

                    return $entity instanceof MouvementStock;
                }
            ));

        $this->service->reapprovisionner(
            $variation,
            5,
            $actor,
            'Livraison fournisseur'
        );

        self::assertSame(15, $variation->getStock());
        self::assertSame(15, $variation->getStockDisponible());
        self::assertInstanceOf(MouvementStock::class, $mouvement);
        self::assertSame(
            MouvementStock::TYPE_ENTREE_APPROVISIONNEMENT,
            $mouvement->getType()
        );
        self::assertSame(5, $mouvement->getQuantite());
        self::assertSame(10, $mouvement->getStockAvant());
        self::assertSame(15, $mouvement->getStockApres());
        self::assertSame('Livraison fournisseur', $mouvement->getCommentaire());
    }

    public function testReservationPuisSortieConserventDesStocksCoherents(): void
    {
        $variation = $this->createVariation(10);
        $commande = $this->createCommande($variation, 3);
        $actor = new User();
        $mouvements = [];

        $this->entityManager
            ->method('persist')
            ->willReturnCallback(
                static function (object $entity) use (&$mouvements): void {
                    $mouvements[] = $entity;
                }
            );

        $this->service->reserverCommande($commande, $actor);

        self::assertSame(3, $variation->getStockReserve());
        self::assertSame(0, $variation->getStockUtilise());
        self::assertSame(7, $variation->getStockDisponible());
        self::assertSame(
            MouvementStock::TYPE_RESERVATION_COMMANDE,
            $mouvements[0]->getType()
        );

        $this->service->sortirCommande($commande, $actor);

        self::assertSame(0, $variation->getStockReserve());
        self::assertSame(3, $variation->getStockUtilise());
        self::assertSame(7, $variation->getStockPhysique());
        self::assertSame(7, $variation->getStockDisponible());
        self::assertSame(
            MouvementStock::TYPE_SORTIE_COMMANDE,
            $mouvements[1]->getType()
        );
        self::assertSame(10, $mouvements[1]->getStockAvant());
        self::assertSame(7, $mouvements[1]->getStockApres());
        self::assertSame(3, $mouvements[1]->getStockReserveAvant());
        self::assertSame(0, $mouvements[1]->getStockReserveApres());
    }

    public function testUneReservationSuperieureAuDisponibleEstRefusee(): void
    {
        $variation = $this->createVariation(2);
        $commande = $this->createCommande($variation, 3);

        $this->entityManager
            ->expects(self::never())
            ->method('persist');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Stock insuffisant');

        $this->service->reserverCommande($commande, new User());
    }

    public function testUnAjustementNePeutPasPasserSousLeStockEngage(): void
    {
        $variation = $this->createVariation(10)
            ->setStockUtilise(4)
            ->setStockReserve(3);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('quantités sorties et réservées');

        $this->service->ajusterStock(
            $variation,
            6,
            new User()
        );
    }

    private function createVariation(int $stock): ProductVariation
    {
        return (new ProductVariation())
            ->setLibelle('Standard')
            ->setStock($stock)
            ->setStockUtilise(0)
            ->setStockReserve(0);
    }

    private function createCommande(
        ProductVariation $variation,
        int $quantite,
    ): Commande {
        $ligne = (new LigneCommande())
            ->setVariation($variation)
            ->setQuantite($quantite);

        return (new Commande())->addLigne($ligne);
    }
}
