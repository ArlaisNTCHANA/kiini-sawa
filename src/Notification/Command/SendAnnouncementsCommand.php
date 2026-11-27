<?php

namespace App\Notification\Command;

use App\Notification\Entity\Announcement;
use App\Notification\Repository\AnnouncementRepository;
use App\Notification\Service\AnnouncementSender;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Termine les annonces dont l'envoi a été interrompu (page d'administration fermée trop tôt). Lancée par la tâche
 * planifiée des rappels (bin/ops/cron-rappels.sh) ; sans annonce en cours, elle ne fait rien.
 */
#[AsCommand(name: 'app:annonces:envoyer', description: 'Termine l\'envoi des annonces de l\'équipe en cours')]
class SendAnnouncementsCommand extends Command
{
    public function __construct(private readonly AnnouncementRepository $announcements, private readonly AnnouncementSender $sender)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->announcements->findBy(['status' => Announcement::STATUS_SENDING]) as $a) {
            $guard = 0;
            while (!$this->sender->sendBatch($a) && ++$guard < 400) {
            }
            $output->writeln(sprintf('Annonce « %s » : %d membre(s) prévenu(s)%s.', $a->getTitle(), $a->getReached(), Announcement::STATUS_SENT === $a->getStatus() ? ', terminée' : ', à reprendre'));
        }

        return Command::SUCCESS;
    }
}
