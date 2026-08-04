<?php

namespace App\Command;

use App\Service\DemoData\DemoDataGenerator;
use App\Service\DemoData\DemoDataOptions;
use App\Service\DemoData\DemoDataResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-demo-data',
    description: 'Génère un jeu de données métier synthétique, reproductible et isolé.',
)]
final class GenerateDemoDataCommand extends Command
{
    public function __construct(private readonly DemoDataGenerator $generator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Graine aléatoire', '20260804')
            ->addOption('start-date', null, InputOption::VALUE_REQUIRED, 'Date de début incluse', '2024-08-04')
            ->addOption('end-date', null, InputOption::VALUE_REQUIRED, 'Date de fin incluse', '2026-08-04')
            ->addOption('scale', null, InputOption::VALUE_REQUIRED, 'Volume : small ou medium', 'small')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les volumes prévus sans écrire en base')
            ->addOption('reset-demo', null, InputOption::VALUE_NONE, 'Supprime exclusivement les anciennes données de démonstration avant génération');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            if ((string) $input->getOption('scale') === 'medium') {
                $this->ensureMemoryLimit('512M');
            }
            $options = new DemoDataOptions(
                seed: filter_var($input->getOption('seed'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
                    ?? throw new \InvalidArgumentException('La graine doit être un entier.'),
                startDate: $this->parseDate((string) $input->getOption('start-date'), false),
                endDate: $this->parseDate((string) $input->getOption('end-date'), true),
                scale: (string) $input->getOption('scale'),
                dryRun: (bool) $input->getOption('dry-run'),
                resetDemo: (bool) $input->getOption('reset-demo'),
            );
            $result = $this->generator->generate($options);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->displayResult($io, $result);

        return Command::SUCCESS;
    }

    private function ensureMemoryLimit(string $requiredLimit): void
    {
        $current = (string) ini_get('memory_limit');
        if ($current === '-1' || $this->memoryToBytes($current) >= $this->memoryToBytes($requiredLimit)) {
            return;
        }

        if (ini_set('memory_limit', $requiredLimit) === false) {
            throw new \RuntimeException(sprintf(
                'Le dataset moyen nécessite une limite mémoire PHP d’au moins %s.',
                $requiredLimit,
            ));
        }
    }

    private function memoryToBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return $number * match (strtolower(substr($value, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };
    }

    private function parseDate(string $value, bool $endOfDay): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new \InvalidArgumentException(sprintf('Date invalide : %s (format attendu : YYYY-MM-DD).', $value));
        }

        return $endOfDay ? $date->setTime(23, 59, 59) : $date->setTime(0, 0);
    }

    private function displayResult(SymfonyStyle $io, DemoDataResult $result): void
    {
        if ($result->dryRun) {
            $io->note('Simulation uniquement : aucune écriture en base.');
        } elseif ($result->alreadyExists) {
            $io->note('Ce lot existe déjà : aucune donnée dupliquée.');
        } else {
            $io->success('Données de démonstration générées avec succès.');
        }

        $io->definitionList(
            ['Lot' => $result->batchId],
            ['Fournisseurs' => (string) $result->suppliers],
            ['Clients' => (string) $result->clients],
            ['Produits / variations' => sprintf('%d / %d', $result->products, $result->variations)],
            ['Commandes / lignes' => sprintf('%d / %d', $result->orders, $result->lines)],
            ['Mouvements de stock' => (string) $result->movements],
            ['Transitions de statut' => (string) $result->statusTransitions],
            ['Date minimale' => $result->minDate?->format('Y-m-d H:i:s') ?? '-'],
            ['Date maximale' => $result->maxDate?->format('Y-m-d H:i:s') ?? '-'],
            ['Commandes par statut' => $result->ordersByStatus === [] ? '-' : json_encode($result->ordersByStatus, JSON_UNESCAPED_UNICODE)],
            ['Variations ayant connu une rupture' => (string) $result->stockoutVariations],
            ['Durée' => number_format($result->durationSeconds, 2).' s'],
        );
    }
}
