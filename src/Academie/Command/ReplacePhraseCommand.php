<?php

namespace App\Academie\Command;

use App\Academie\Entity\Course;
use App\Academie\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Remplace une phrase exacte dans un parcours, **même publié** : titre, sous-titre et présentation du parcours, titres
 * des modules et des séances, consignes d'écriture. Le texte des leçons n'est jamais touché (il cite le livre), ni les
 * adresses des séances : la progression, les notes et les exercices des membres restent intacts.
 *
 * Sans --enregistrer, la commande montre seulement ce qui changerait. La recherche respecte les majuscules.
 * Né le 27/11/2026 : « Votre But de Dieu » → « Le But de Dieu pour ma vie » dans « Mon Plan de Vie », déjà publié.
 */
#[AsCommand(name: 'app:academie:remplacer-phrase', description: 'Remplace une phrase dans les titres, la présentation et les consignes d\'un parcours (même publié)')]
class ReplacePhraseCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CourseRepository $courses,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('parcours', InputArgument::REQUIRED, 'Adresse du parcours (ex. mon-plan-de-vie)')
            ->addArgument('ancien', InputArgument::REQUIRED, 'Phrase à remplacer (majuscules comprises)')
            ->addArgument('nouveau', InputArgument::REQUIRED, 'Nouvelle phrase')
            ->addOption('enregistrer', null, InputOption::VALUE_NONE, 'Enregistre les changements (sinon : aperçu seulement)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $old = (string) $input->getArgument('ancien');
        $new = (string) $input->getArgument('nouveau');
        if ('' === trim($old) || $old === $new) {
            $io->error('Indiquez une phrase à remplacer, différente de la nouvelle.');

            return Command::FAILURE;
        }
        $course = $this->courses->findOneBy(['slug' => (string) $input->getArgument('parcours')]);
        if (!$course instanceof Course) {
            $io->error('Aucun parcours ne porte cette adresse.');

            return Command::FAILURE;
        }

        $changes = [];
        $plan = function (string $where, ?string $value, callable $set, int $max = 0) use ($old, $new, &$changes): void {
            if (null === $value || !str_contains($value, $old)) {
                return;
            }
            $changed = str_replace($old, $new, $value);
            if ($max > 0 && mb_strlen($changed) > $max) {
                throw new \LengthException($where.' dépasserait '.$max.' caractères.');
            }
            $changes[] = [$where, $value, $changed, $set];
        };

        try {
            $plan('Titre du parcours', $course->getTitle(), fn (string $v) => $course->setTitle($v), 160);
            $plan('Sous-titre du parcours', $course->getSubtitle(), fn (string $v) => $course->setSubtitle($v));
            $plan('Présentation du parcours', $course->getDescription(), fn (string $v) => $course->setDescription($v));
            foreach ($course->getModules() as $module) {
                $plan('Titre du module « '.$module->getTitle().' »', $module->getTitle(), fn (string $v) => $module->setTitle($v));
                foreach ($module->getLessons() as $lesson) {
                    $plan('Titre de la séance « '.$lesson->getTitle().' »', $lesson->getTitle(), fn (string $v) => $lesson->setTitle($v), 160);
                    $plan('Consigne d\'écriture de « '.$lesson->getTitle().' »', $lesson->getPracticePrompt(), fn (string $v) => $lesson->setPracticePrompt($v));
                }
            }
        } catch (\LengthException $e) {
            $io->error($e->getMessage().' Rien n\'a été modifié.');

            return Command::FAILURE;
        }

        if ([] === $changes) {
            $io->success('La phrase « '.$old.' » n\'apparaît dans aucun titre, présentation ou consigne de « '.$course->getTitle().' ». Rien à changer.');

            return Command::SUCCESS;
        }
        foreach ($changes as [$where, $before, $after]) {
            $io->section($where);
            $io->text(['Avant : '.$before, 'Après : '.$after]);
        }
        if (!$input->getOption('enregistrer')) {
            $io->note(\count($changes).' changement(s) possible(s), rien n\'est enregistré. Relancez avec --enregistrer pour les appliquer.');

            return Command::SUCCESS;
        }
        foreach ($changes as [, , $after, $set]) {
            $set($after);
        }
        $this->em->flush();
        $io->success(\count($changes).' changement(s) enregistré(s). Progression, notes et exercices des membres sont intacts.');

        return Command::SUCCESS;
    }
}
