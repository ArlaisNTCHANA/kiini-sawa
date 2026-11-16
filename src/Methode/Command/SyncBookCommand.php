<?php

namespace App\Methode\Command;

use App\Methode\Service\BookLibrary;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Copie les chapitres du manuscrit (KIINI SAWA/docs/livre) vers content/livre, affiché dans l'application.
 */
#[AsCommand(name: 'app:livre:sync', description: 'Met à jour les chapitres du livre affichés dans l\'application')]
class SyncBookCommand extends Command
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $source = $this->projectDir.'/docs/livre';
        if (!is_dir($source)) {
            $io->error("Manuscrit introuvable : $source");

            return Command::FAILURE;
        }
        $copied = 0;
        foreach (array_keys(BookLibrary::CHAPTERS) as $file) {
            if (is_file("$source/$file")) {
                copy("$source/$file", $this->projectDir."/content/livre/$file");
                ++$copied;
            }
        }
        $io->success("$copied chapitre(s) mis à jour.");

        return Command::SUCCESS;
    }
}
