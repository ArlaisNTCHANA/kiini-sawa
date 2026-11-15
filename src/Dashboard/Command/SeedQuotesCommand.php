<?php

namespace App\Dashboard\Command;

use App\Dashboard\Entity\Quote;
use App\Dashboard\Repository\QuoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Citations du jour par défaut : versets bibliques en lien avec la méthode
 * (raison d'être, vision, croissance, transmission, hygiène de vie).
 *
 * Texte : Bible Louis Segond 1910, domaine public. Idempotent : une citation
 * déjà présente (même texte) n'est pas recréée. Les citations se gèrent ensuite
 * dans l'administration (/admin/citations).
 */
#[AsCommand(name: 'app:quotes:seed', description: 'Ajoute les citations du jour par défaut (versets LSG 1910)')]
class SeedQuotesCommand extends Command
{
    public const QUOTES = [
        ['Car je connais les projets que j\'ai formés sur vous, dit l\'Éternel, projets de paix et non de malheur, afin de vous donner un avenir et de l\'espérance.', 'Jérémie 29:11'],
        ['Recommande à l\'Éternel tes œuvres, et tes projets réussiront.', 'Proverbes 16:3'],
        ['Écris la prophétie : grave-la sur des tables, afin qu\'on la lise couramment.', 'Habakuk 2:2'],
        ['Car nous sommes son ouvrage, ayant été créés en Jésus-Christ pour de bonnes œuvres, que Dieu a préparées d\'avance, afin que nous les pratiquions.', 'Éphésiens 2:10'],
        ['Ne vous conformez pas au siècle présent, mais soyez transformés par le renouvellement de l\'intelligence, afin que vous discerniez quelle est la volonté de Dieu, ce qui est bon, agréable et parfait.', 'Romains 12:2'],
        ['Garde ton cœur plus que toute autre chose, car de lui viennent les sources de la vie.', 'Proverbes 4:23'],
        ['Cherchez premièrement le royaume et la justice de Dieu ; et toutes ces choses vous seront données par-dessus.', 'Matthieu 6:33'],
        ['Ne nous lassons pas de faire le bien ; car nous moissonnerons au temps convenable, si nous ne nous relâchons pas.', 'Galates 6:9'],
        ['Tout ce que vous faites, faites-le de bon cœur, comme pour le Seigneur et non pour des hommes.', 'Colossiens 3:23'],
        ['Les projets de l\'homme diligent ne mènent qu\'à l\'abondance, mais celui qui agit avec précipitation n\'arrive qu\'à la disette.', 'Proverbes 21:5'],
        ['Car, lequel de vous, s\'il veut bâtir une tour, ne s\'assied d\'abord pour calculer la dépense et voir s\'il a de quoi la terminer ?', 'Luc 14:28'],
        ['Comme de bons dispensateurs des diverses grâces de Dieu, que chacun de vous mette au service des autres le don qu\'il a reçu.', '1 Pierre 4:10'],
        ['Et ce que tu as entendu de moi en présence de beaucoup de témoins, confie-le à des hommes fidèles, qui soient capables de l\'enseigner aussi à d\'autres.', '2 Timothée 2:2'],
        ['Comme le fer aiguise le fer, ainsi un homme aiguise la figure d\'un autre homme.', 'Proverbes 27:17'],
        ['Enseigne-nous à bien compter nos jours, afin que nous appliquions notre cœur à la sagesse.', 'Psaume 90:12'],
        ['Je suis persuadé que celui qui a commencé en vous cette bonne œuvre la rendra parfaite pour le jour de Jésus-Christ.', 'Philippiens 1:6'],
        ['Mettez en pratique la parole, et ne vous bornez pas à l\'écouter, en vous trompant vous-mêmes par de faux raisonnements.', 'Jacques 1:22'],
        ['Celui qui fréquente les sages devient sage, mais celui qui se plaît avec les insensés s\'en trouve mal.', 'Proverbes 13:20'],
        ['Recommande ton sort à l\'Éternel, mets en lui ta confiance, et il agira.', 'Psaume 37:5'],
        ['Je puis tout par celui qui me fortifie.', 'Philippiens 4:13'],
        ['Deux valent mieux qu\'un, parce qu\'ils retirent un bon salaire de leur travail.', 'Ecclésiaste 4:9'],
        ['Instruis l\'enfant selon la voie qu\'il doit suivre ; et quand il sera vieux, il ne s\'en détournera pas.', 'Proverbes 22:6'],
    ];

    public function __construct(private readonly EntityManagerInterface $em, private readonly QuoteRepository $quotes)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $added = 0;
        foreach (self::QUOTES as [$content, $reference]) {
            if ($this->quotes->findOneBy(['content' => $content])) {
                continue;
            }
            $this->em->persist((new Quote())->setContent($content)->setAuthor($reference.' (LSG)')->setCategory('VERSET'));
            ++$added;
        }
        $this->em->flush();
        (new SymfonyStyle($input, $output))->success(sprintf('%d citation(s) ajoutée(s), %d déjà présente(s).', $added, \count(self::QUOTES) - $added));

        return Command::SUCCESS;
    }
}
