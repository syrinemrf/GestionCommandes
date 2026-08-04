<?php

namespace App\Command;

use App\Service\DemoData\DemoImageDownloader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:download-demo-images',
    description: 'Télécharge les images Pexels du catalogue de démonstration ou crée des placeholders.',
)]
final class DownloadDemoImagesCommand extends Command
{
    public function __construct(private readonly DemoImageDownloader $downloader)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->downloader->download();
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success('Préparation des images de démonstration terminée.');
        $io->definitionList(
            ['Téléchargées depuis Pexels' => (string) $result['downloaded']],
            ['Déjà présentes' => (string) $result['existing']],
            ['Placeholders utilisés' => (string) $result['placeholders']],
            ['Manifeste' => $result['manifest']],
        );

        return Command::SUCCESS;
    }
}
