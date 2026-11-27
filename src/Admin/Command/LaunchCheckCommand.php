<?php

namespace App\Admin\Command;

use App\Admin\Service\LaunchChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Contrôle avant (et après) la mise en ligne : liste ce qui manque, en français, avec la marche à suivre.
 * Code de sortie 1 s'il reste un point bloquant. Les contrôles sont dans LaunchChecker (aussi affichés dans
 * Admin › Réglages › Santé du site).
 */
#[AsCommand(name: 'app:launch:check', description: 'Vérifie que tout est prêt pour la mise en ligne')]
class LaunchCheckCommand extends Command
{
    public function __construct(private readonly LaunchChecker $checker)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('KIINI SAWA : contrôle avant mise en ligne');
        $rows = [];
        $blocking = 0;
        foreach ($this->checker->run() as $c) {
            $rows[] = [$c['ok'] ? '<info>✓</info>' : ($c['blocking'] ? '<error>✗</error>' : '<comment>!</comment>'), $c['label'], $c['advice']];
            $blocking += (int) (!$c['ok'] && $c['blocking']);
        }
        $io->table(['', 'Point', 'À faire'], $rows);
        if ($blocking > 0) {
            $io->error(sprintf('%d point(s) bloquant(s) avant la mise en ligne.', $blocking));

            return Command::FAILURE;
        }
        $io->success('Tout est prêt pour la mise en ligne. Les points marqués « ! » sont recommandés.');

        return Command::SUCCESS;
    }
}
