<?php

namespace App\Command;

use App\Service\LiveActivity\LiveActivityOptions;
use App\Service\LiveActivity\LiveActivityResult;
use App\Service\LiveActivity\LiveActivitySimulator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:simulate-live-activity',
    description: 'Ajoute un lot fini de commandes et de transitions aux données existantes.',
)]
final class SimulateLiveActivityCommand extends Command
{
    public function __construct(private readonly LiveActivitySimulator $simulator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('orders', null, InputOption::VALUE_REQUIRED, 'Nombre de nouvelles commandes', '10')
            ->addOption('status-updates', null, InputOption::VALUE_REQUIRED, 'Nombre de commandes existantes à faire avancer', '10')
            ->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Graine aléatoire', '20260810')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche le lot prévu sans écrire en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->simulator->simulate(new LiveActivityOptions(
                orders: $this->integerOption($input, 'orders'),
                statusUpdates: $this->integerOption($input, 'status-updates'),
                seed: $this->integerOption($input, 'seed'),
                dryRun: (bool) $input->getOption('dry-run'),
            ));
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->displayResult($io, $result);

        return Command::SUCCESS;
    }

    private function integerOption(InputInterface $input, string $name): int
    {
        return filter_var($input->getOption($name), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
            ?? throw new \InvalidArgumentException(sprintf('L’option --%s doit être un entier.', $name));
    }

    private function displayResult(SymfonyStyle $io, LiveActivityResult $result): void
    {
        if ($result->dryRun) {
            $io->note('Simulation uniquement : aucune écriture en base.');
        } else {
            $io->success('Le lot d’activité simulée a été créé avec succès.');
        }

        $ordersLabel = $result->dryRun ? 'Commandes prévues' : 'Commandes prévues / créées';
        $ordersValue = $result->dryRun
            ? (string) $result->requestedOrders
            : sprintf('%d / %d', $result->requestedOrders, $result->createdOrders);
        $statusesLabel = $result->dryRun ? 'Statuts modifiables prévus' : 'Statuts prévus / modifiés';
        $statusesValue = $result->dryRun
            ? (string) $result->updatedStatuses
            : sprintf('%d / %d', $result->requestedStatusUpdates, $result->updatedStatuses);

        $io->definitionList(
            [$ordersLabel => $ordersValue],
            ['Lignes créées' => $result->dryRun ? 'calculées lors de l’exécution' : (string) $result->createdLines],
            [$statusesLabel => $statusesValue],
            ['Réapprovisionnements' => $result->dryRun ? 'calculés lors de l’exécution' : (string) $result->replenishments],
            ['Transitions' => $result->statusChanges === [] ? '-' : json_encode($result->statusChanges, JSON_UNESCAPED_UNICODE)],
            ['Début du lot' => $result->startsAt?->format('Y-m-d H:i:s') ?? '-'],
            ['Fin du lot' => $result->endsAt?->format('Y-m-d H:i:s') ?? '-'],
            ['Graine' => (string) $result->seed],
        );
    }
}
