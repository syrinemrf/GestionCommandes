<?php

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Parametre;
use App\Entity\Product;
use App\Entity\ProductVariation;
use App\Entity\User;
use App\Repository\ParametreRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariationRepository;
use App\Service\CommandeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

final class CommandeServiceTest extends KernelTestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private ParametreRepository&MockObject $parametreRepository;
    private ProductRepository&MockObject $productRepository;
    private ProductVariationRepository&MockObject $variationRepository;
    private CsrfTokenManagerInterface&MockObject $csrfTokenManager;
    private CommandeService $service;

    protected function setUp(): void
    {
        self::bootKernel();

        $workflow = static::getContainer()->get('state_machine.commande');
        self::assertInstanceOf(WorkflowInterface::class, $workflow);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->parametreRepository = $this->createMock(ParametreRepository::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->variationRepository = $this->createMock(ProductVariationRepository::class);
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);

        $this->service = new CommandeService(
            $this->entityManager,
            $this->parametreRepository,
            $this->productRepository,
            $this->variationRepository,
            $this->csrfTokenManager,
            $workflow,
        );
    }

    public function testCreerUneCommandeReserveLeStock(): void
    {
        [$actor, $fournisseur, $product, $variation] =
            $this->createOrderContext(10);
        $parametre = (new Parametre())
            ->setNumeroCommande(42)
            ->setTva('19.000');

        $this->parametreRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with([])
            ->willReturn($parametre);
        $this->productRepository
            ->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($product);
        $this->variationRepository
            ->expects(self::once())
            ->method('find')
            ->with(10)
            ->willReturn($variation);
        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(Commande::class));
        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $commande = new Commande();
        $this->service->saveFromRequest(
            $commande,
            $this->createOrderRequest(3),
            $actor,
            $fournisseur,
            true,
        );

        self::assertSame(42, $commande->getNumero());
        self::assertSame(43, $parametre->getNumeroCommande());
        self::assertSame('75.000', $commande->getTotalHt());
        self::assertSame('89.250', $commande->getTotalTtc());
        self::assertSame(3, $variation->getStockUtilise());
        self::assertCount(1, $commande->getLignes());
    }

    public function testCreerUneCommandeRefuseUnStockInsuffisant(): void
    {
        [$actor, $fournisseur, $product, $variation] =
            $this->createOrderContext(2);

        $this->parametreRepository
            ->method('findOneBy')
            ->willReturn(new Parametre());
        $this->productRepository
            ->method('find')
            ->willReturn($product);
        $this->variationRepository
            ->method('find')
            ->willReturn($variation);
        $this->entityManager
            ->expects(self::never())
            ->method('flush');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Stock insuffisant');

        try {
            $this->service->saveFromRequest(
                new Commande(),
                $this->createOrderRequest(3),
                $actor,
                $fournisseur,
                true,
            );
        } finally {
            self::assertSame(0, $variation->getStockUtilise());
        }
    }

    public function testAnnulerUneCommandeLibereLeStock(): void
    {
        [, , , $variation] = $this->createOrderContext(10);
        $variation->setStockUtilise(3);

        $ligne = (new LigneCommande())
            ->setVariation($variation)
            ->setQuantite(3);
        $commande = (new Commande())
            ->setStatut(Commande::STATUT_EN_ATTENTE_CONFIRMATION)
            ->addLigne($ligne);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $this->service->updateStatus(
            $commande,
            Commande::STATUT_ANNULEE,
        );

        self::assertSame(Commande::STATUT_ANNULEE, $commande->getStatut());
        self::assertSame(0, $variation->getStockUtilise());
    }

    public function testUnSautDeStatutEstRefuse(): void
    {
        $commande = new Commande();

        $this->entityManager
            ->expects(self::never())
            ->method('flush');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('statut');

        $this->service->updateStatus(
            $commande,
            Commande::STATUT_PRETE,
        );
    }

    /**
     * @return array{User, User, Product, ProductVariation}
     */
    private function createOrderContext(int $stock): array
    {
        $actor = new User();
        $this->setEntityId($actor, 100);

        $fournisseur = new User();
        $this->setEntityId($fournisseur, 200);

        $product = (new Product())
            ->setLibelle('Produit test')
            ->setPrix('20.000')
            ->setFournisseur($fournisseur)
            ->setIsDeleted(false);
        $this->setEntityId($product, 1);

        $variation = (new ProductVariation())
            ->setProduct($product)
            ->setLibelle('Standard')
            ->setPrixSupplement('5.000')
            ->setStock($stock)
            ->setStockUtilise(0)
            ->setIsDeleted(false);
        $this->setEntityId($variation, 10);

        return [$actor, $fournisseur, $product, $variation];
    }

    private function createOrderRequest(int $quantite): Request
    {
        return new Request([], [
            'produit' => ['1'],
            'variation' => ['10'],
            'quantite' => [(string) $quantite],
            'note' => 'Commande de test',
        ]);
    }

    private function setEntityId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
