<?php

namespace App\Notification\Service;

use App\Compte\Entity\User;
use App\Compte\Entity\UserProfile;
use App\Compte\Service\BirthDate;
use App\Compte\Service\BirthdayTexts;
use App\Contenu\Service\SiteTexts;
use App\Notification\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Le jour de l'anniversaire : un e-mail et une notification « Une nouvelle année de vie commence… » qui invitent à relire
 * son Plan de Vie. Appelé chaque jour par app:reminders:send ; la clé « anniversaire AAAA » évite tout doublon dans l'année.
 * Désactivable dans les préférences (interrupteur « Mon anniversaire ») ou par le lien de désabonnement de l'e-mail.
 * La date de naissance n'apparaît jamais dans le message.
 */
final class BirthdayGreeter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Notifier $notifier,
        private readonly SiteTexts $texts,
    ) {
    }

    /** @return int nombre de personnes à qui le message a été envoyé */
    public function sendAll(\DateTimeImmutable $today): int
    {
        // Seulement deux colonnes : la liste reste légère, quel que soit le nombre de membres (filtre du jour en PHP, valable sur toutes les bases)
        $rows = $this->em->createQueryBuilder()->select('IDENTITY(p.user) AS userId', 'p.dateOfBirth AS birth')->from(UserProfile::class, 'p')
            ->join('p.user', 'u')->where('u.isVerified = true')->andWhere('p.dateOfBirth IS NOT NULL')
            ->getQuery()->getArrayResult();

        $sent = 0;
        foreach ($rows as $row) {
            if (!BirthDate::isBirthday($row['birth'], $today)) {
                continue;
            }
            $user = $this->em->find(User::class, $row['userId']);
            $sent += (int) ($user && $this->greet($user, $today));
        }

        return $sent;
    }

    public function greet(User $user, \DateTimeImmutable $today): bool
    {
        $firstName = $user->getUserProfile()?->getFirstName() ?: $user->getDisplayName();

        return $this->notifier->notify($user, NotificationType::BIRTHDAY,
            BirthdayTexts::text($this->texts, 'anniversaire.titre', ['prenom' => $firstName]),
            BirthdayTexts::text($this->texts, 'anniversaire.texte'),
            '/plan-de-vie', 'anniversaire '.$today->format('Y'),
            ['template' => 'notification/email/birthday.html.twig', 'context' => ['actionLabel' => BirthdayTexts::text($this->texts, 'anniversaire.bouton')]]);
    }
}
