<?php

namespace App\Tests\Entity;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use PHPUnit\Framework\TestCase;

final class CommandeTest extends TestCase
{
    public function testUneNouvelleCommandeEstModifiable(): void
    {
        $commande = new Commande();

        self::assertSame(
            Commande::STATUT_EN_ATTENTE_CONFIRMATION,
            $commande->getStatut()
        );
        self::assertTrue($commande->isModifiable());
    }

    public function testUneCommandeConfirmeeNestPlusModifiable(): void
    {
        $commande = (new Commande())
            ->setStatut(Commande::STATUT_EN_PREPARATION);

        self::assertFalse($commande->isModifiable());
    }

    public function testLeTotalTtcEstCalculeAPartirDuTotalHtEtDeLaTva(): void
    {
        $commande = (new Commande())
            ->setTotalHt('100.000')
            ->setTauxTva('19.000');

        self::assertSame('119.000', $commande->getTotalTtc());
    }

    public function testAjouterEtRetirerUneLigneMaintientLaRelation(): void
    {
        $commande = new Commande();
        $ligne = new LigneCommande();

        $commande->addLigne($ligne);

        self::assertCount(1, $commande->getLignes());
        self::assertSame($commande, $ligne->getCommande());

        $commande->removeLigne($ligne);

        self::assertCount(0, $commande->getLignes());
        self::assertNull($ligne->getCommande());
    }
}
