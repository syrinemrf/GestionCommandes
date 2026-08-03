<?php

namespace App\Tests\Entity;

use App\Entity\Commande;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandeDocumentAvailabilityTest extends TestCase
{
    #[DataProvider('statutsDocuments')]
    public function testDisponibiliteDesDocumentsSelonLeStatut(
        string $statut,
        bool $disponible,
    ): void {
        $commande = (new Commande())->setStatut($statut);

        self::assertSame(
            $disponible,
            $commande->canGenerateDocuments()
        );
    }

    public static function statutsDocuments(): iterable
    {
        yield 'attente de confirmation' => [
            Commande::STATUT_EN_ATTENTE_CONFIRMATION,
            false,
        ];
        yield 'preparation' => [
            Commande::STATUT_EN_PREPARATION,
            false,
        ];
        yield 'prete' => [Commande::STATUT_PRETE, true];
        yield 'expediee' => [Commande::STATUT_EXPEDIEE, true];
        yield 'en livraison' => [Commande::STATUT_EN_LIVRAISON, true];
        yield 'livree' => [Commande::STATUT_LIVREE, true];
        yield 'annulee' => [Commande::STATUT_ANNULEE, false];
    }
}
