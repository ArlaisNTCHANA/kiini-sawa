<?php

namespace App\Notification\Command;

use App\Notification\Service\ReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Envoie les rappels du jour. À lancer une fois par jour, par exemple à 8 h :
 *   0 8 * * * cd /chemin/vers/web && php bin/console app:reminders:send
 * Relancer la commande le même jour n'envoie pas de doublon.
 */
#[AsCommand(name: 'app:reminders:send', description: 'Envoie les rappels du jour (revues, rituels, échéances, partenaires, leçons de l\'Académie, feedback nourricier, anniversaires, « Saviez-vous que… »)')]
class SendRemindersCommand extends Command
{
    public function __construct(
        private readonly ReminderService $reminders,
        private readonly \Psr\Cache\CacheItemPoolInterface $cacheApp,
        private readonly \App\Rapide\Repository\SyncOperationRepository $syncOperations,
        private readonly \App\Mobile\Repository\MobileTokenRepository $mobileTokens,
        private readonly \App\Social\Service\AccountabilityService $accountability,
        private readonly \App\Academie\Service\AcademyPaceMailer $academy,
        private readonly \App\Feedback\Service\FeedbackService $feedback,
        private readonly \App\Notification\Service\BirthdayGreeter $birthdays,
        private readonly \App\Notification\Service\TipSender $tips,
        private readonly \App\Notification\Service\Notifier $notifier,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('date', null, InputOption::VALUE_REQUIRED, 'Date simulée (AAAA-MM-JJ), pour les tests');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $date = $input->getOption('date') ? \DateTimeImmutable::createFromFormat('!Y-m-d', $input->getOption('date')) : new \DateTimeImmutable('today');
        if (!$date) {
            $io->error('Date invalide : utilisez AAAA-MM-JJ.');

            return Command::INVALID;
        }

        // Académie d'abord : le jour d'une séance (ou d'une leçon), c'est le seul e-mail de rappel ; les autres rappels
        // de ces membres restent dans l'application (Notifier::HELD_ON_SESSION_DAYS, parcours guidés, 26/11/2026)
        $academy = $this->academy->sendAll($date);
        $this->notifier->holdRemindersForSessionDay($this->academy->recipients());

        $sent = $this->reminders->sendAll($date);
        // Redevabilité : alertes automatiques au partenaire (avec copie à la personne) et reprises à 72 heures
        $accountability = $this->accountability->runDailyChecks($date);
        $sent['redevabilité (alertes)'] = $accountability['alertes'];
        // Académie (envoyée plus haut) : leçon du jour selon le rythme choisi, pause automatique après 3 e-mails sans avancer
        $sent['académie (leçons)'] = $academy['lecons'];
        $sent['académie (pauses)'] = $academy['pauses'];
        // Feedback nourricier : lien arrivé à son terme, rappel s'il manque des réponses après quelques jours
        $feedback = $this->feedback->runDailyChecks($date);
        $sent['feedback (fin du lien)'] = $feedback['fin'];
        $sent['feedback (relances)'] = $feedback['relance'];
        // Anniversaire : une nouvelle année de vie, invitation à relire son Plan de Vie (une fois par an)
        $sent['anniversaires'] = $this->birthdays->sendAll($date);
        // « Saviez-vous que… » : en dernier, pour laisser passer les e-mails importants du jour
        $sent['saviez-vous'] = $this->tips->sendAll($date);
        // Ménage quotidien : les identifiants de synchronisation du mode rapide de plus de 60 jours ne servent plus
        $this->syncOperations->purgeOlderThan($date->modify('-60 days'));
        $this->mobileTokens->purgeExpired($date->modify('-60 days'));
        // Trace de la dernière exécution, vérifiée par app:launch:check (tâche planifiée bien en place ?)
        $this->cacheApp->save($this->cacheApp->getItem('reminders_last_run')->set(time()));
        $io->table(['Rappel', 'Envoyés'], array_map(fn ($k, $v) => [$k, $v], array_keys($sent), $sent));
        $io->success(array_sum($sent).' rappel(s) envoyé(s) pour le '.$date->format('d/m/Y').'.');

        return Command::SUCCESS;
    }
}
