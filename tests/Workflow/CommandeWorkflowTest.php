<?php

namespace App\Tests\Workflow;

use App\Entity\Commande;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\WorkflowInterface;

final class CommandeWorkflowTest extends KernelTestCase
{
    private WorkflowInterface $workflow;

    protected function setUp(): void
    {
        self::bootKernel();

        $workflow = static::getContainer()->get('state_machine.commande');
        self::assertInstanceOf(WorkflowInterface::class, $workflow);
        $this->workflow = $workflow;
    }

    #[DataProvider('transitionsAutorisees')]
    public function testLesTransitionsAutorisees(
        string $statutInitial,
        string $transition,
        string $statutFinal,
    ): void {
        $commande = (new Commande())->setStatut($statutInitial);

        self::assertTrue($this->workflow->can($commande, $transition));

        $this->workflow->apply($commande, $transition);

        self::assertSame($statutFinal, $commande->getStatut());
    }

    public static function transitionsAutorisees(): iterable
    {
        yield 'confirmation' => [
            Commande::STATUT_EN_ATTENTE_CONFIRMATION,
            'confirmer',
            Commande::STATUT_EN_PREPARATION,
        ];
        yield 'annulation avant confirmation' => [
            Commande::STATUT_EN_ATTENTE_CONFIRMATION,
            'annuler',
            Commande::STATUT_ANNULEE,
        ];
        yield 'annulation pendant la preparation' => [
            Commande::STATUT_EN_PREPARATION,
            'annuler',
            Commande::STATUT_ANNULEE,
        ];
        yield 'commande prete' => [
            Commande::STATUT_EN_PREPARATION,
            'preparer',
            Commande::STATUT_PRETE,
        ];
        yield 'expedition' => [
            Commande::STATUT_PRETE,
            'expedier',
            Commande::STATUT_EXPEDIEE,
        ];
        yield 'mise en livraison' => [
            Commande::STATUT_EXPEDIEE,
            'mettre_en_livraison',
            Commande::STATUT_EN_LIVRAISON,
        ];
        yield 'livraison terminee' => [
            Commande::STATUT_EN_LIVRAISON,
            'livrer',
            Commande::STATUT_LIVREE,
        ];
    }

    #[DataProvider('transitionsInterdites')]
    public function testLesTransitionsInterdites(
        string $statut,
        string $transition,
    ): void {
        $commande = (new Commande())->setStatut($statut);

        self::assertFalse($this->workflow->can($commande, $transition));
    }

    public static function transitionsInterdites(): iterable
    {
        yield 'preparer sans confirmer' => [
            Commande::STATUT_EN_ATTENTE_CONFIRMATION,
            'preparer',
        ];
        yield 'livrer pendant la preparation' => [
            Commande::STATUT_EN_PREPARATION,
            'livrer',
        ];
        yield 'annuler apres expedition' => [
            Commande::STATUT_EXPEDIEE,
            'annuler',
        ];
        yield 'modifier une commande livree' => [
            Commande::STATUT_LIVREE,
            'confirmer',
        ];
    }
}
