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
 *
 * Plusieurs remplacements d'un coup : `--fichier` (YAML `pairs: [[ancien, nouveau], …]`), et `tous` à la place de
 * l'adresse pour traiter tous les parcours. `--lecons` étend la recherche au texte des leçons : seulement pour des
 * phrases exactes qui *présentent* le livre (règle « la Bible et le livre KIINI »), jamais pour réécrire le livre cité.
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
        $this->addArgument('parcours', InputArgument::REQUIRED, 'Adresse du parcours (ex. mon-plan-de-vie), ou « tous »')
            ->addArgument('ancien', InputArgument::OPTIONAL, 'Phrase à remplacer (majuscules comprises)')
            ->addArgument('nouveau', InputArgument::OPTIONAL, 'Nouvelle phrase')
            ->addOption('fichier', null, InputOption::VALUE_REQUIRED, 'Fichier YAML de remplacements (pairs: [[ancien, nouveau], …])')
            ->addOption('lecons', null, InputOption::VALUE_NONE, 'Cherche aussi dans le texte des leçons (phrases exactes seulement)')
            ->addOption('enregistrer', null, InputOption::VALUE_NONE, 'Enregistre les changements (sinon : aperçu seulement)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $pairs = [];
        if (null !== $input->getOption('fichier')) {
            $file = (string) $input->getOption('fichier');
            $data = is_file($file) ? \Symfony\Component\Yaml\Yaml::parseFile($file) : null;
            foreach (\is_array($data) ? ($data['pairs'] ?? []) : [] as $pair) {
                if (\is_array($pair) && 2 === \count($pair)) {
                    $pairs[] = [(string) $pair[0], (string) $pair[1]];
                }
            }
            if ([] === $pairs) {
                $io->error('Fichier introuvable ou sans remplacement (pairs).');

                return Command::FAILURE;
            }
        } else {
            $pairs[] = [(string) $input->getArgument('ancien'), (string) $input->getArgument('nouveau')];
        }
        foreach ($pairs as [$old, $new]) {
            if ('' === trim($old) || $old === $new) {
                $io->error('Indiquez une phrase à remplacer, différente de la nouvelle.');

                return Command::FAILURE;
            }
        }
        $slug = (string) $input->getArgument('parcours');
        $courses = 'tous' === $slug ? $this->courses->findBy([], ['sortOrder' => 'ASC']) : array_filter([$this->courses->findOneBy(['slug' => $slug])]);
        if ([] === $courses) {
            $io->error('Aucun parcours ne porte cette adresse.');

            return Command::FAILURE;
        }
        $withLessons = (bool) $input->getOption('lecons');

        $changes = [];
        $plan = function (string $where, ?string $value, callable $set, int $max = 0) use ($pairs, &$changes): void {
            if (null === $value) {
                return;
            }
            $changed = $value;
            foreach ($pairs as [$old, $new]) {
                $changed = str_replace($old, $new, $changed);
            }
            if ($changed === $value) {
                return;
            }
            if ($max > 0 && mb_strlen($changed) > $max) {
                throw new \LengthException($where.' dépasserait '.$max.' caractères.');
            }
            $changes[] = [$where, $value, $changed, $set];
        };

        try {
            foreach ($courses as $course) {
                $c = '« '.$course->getTitle().' » : ';
                $plan($c.'titre du parcours', $course->getTitle(), fn (string $v) => $course->setTitle($v), 160);
                $plan($c.'sous-titre', $course->getSubtitle(), fn (string $v) => $course->setSubtitle($v));
                $plan($c.'présentation', $course->getDescription(), fn (string $v) => $course->setDescription($v));
                foreach ($course->getModules() as $module) {
                    $plan($c.'titre du module « '.$module->getTitle().' »', $module->getTitle(), fn (string $v) => $module->setTitle($v));
                    foreach ($module->getLessons() as $lesson) {
                        $plan($c.'titre de la séance « '.$lesson->getTitle().' »', $lesson->getTitle(), fn (string $v) => $lesson->setTitle($v), 160);
                        $plan($c.'consigne d\'écriture de « '.$lesson->getTitle().' »', $lesson->getPracticePrompt(), fn (string $v) => $lesson->setPracticePrompt($v));
                        if ($withLessons) {
                            $plan($c.'texte de la leçon « '.$lesson->getTitle().' »', $lesson->getContentMarkdown(), fn (string $v) => $lesson->setContentMarkdown($v));
                        }
                    }
                }
            }
        } catch (\LengthException $e) {
            $io->error($e->getMessage().' Rien n\'a été modifié.');

            return Command::FAILURE;
        }

        if ([] === $changes) {
            $io->success('Aucune de ces phrases n\'apparaît'.($withLessons ? '' : ' dans les titres, présentations ou consignes').'. Rien à changer.');

            return Command::SUCCESS;
        }
        foreach ($changes as [$where, $before, $after]) {
            $io->section($where);
            if (mb_strlen($before) > 400) {
                foreach ($pairs as [$old, $new]) {
                    if (str_contains($before, $old)) {
                        $io->text(['Avant : … '.$old.' …', 'Après : … '.$new.' …']);
                    }
                }
            } else {
                $io->text(['Avant : '.$before, 'Après : '.$after]);
            }
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
