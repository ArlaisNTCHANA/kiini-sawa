<?php

namespace App\Academie\Service;

use App\Abonnement\Service\Entitlements;
use App\Academie\Entity\CourseEnrollment;
use App\Compte\Entity\User;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Rythme de l'Académie par e-mail, appelé chaque matin par app:reminders:send.
 *
 * - Pour chaque inscription en rythme « quotidien » ou « 3 par semaine » (lundi, mercredi, vendredi), non terminée et
 *   pas en pause : un e-mail avec la prochaine leçon non terminée, sa durée et un lien direct.
 * - Jamais deux e-mails le même jour pour la même inscription (date du dernier envoi + clé de notification).
 * - Après 3 e-mails sans leçon terminée, pas de 4e : le rythme passe en pause, avec un message bienveillant et un lien
 *   pour le reprendre en un clic depuis la page du parcours.
 * - Le membre n'est jamais bloqué : il peut lire les leçons suivantes en avance.
 */
final class AcademyPaceMailer
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Notifier $notifier,
        private readonly Entitlements $entitlements,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    /** @return array{lecons: int, pauses: int} */
    public function sendAll(\DateTimeImmutable $today): array
    {
        $sent = ['lecons' => 0, 'pauses' => 0];
        $enrollments = $this->em->getRepository(CourseEnrollment::class)->createQueryBuilder('e')
            ->where('e.paceMode != :free')->andWhere('e.pacePaused = false')->andWhere('e.completedAt IS NULL')
            ->setParameter('free', CourseEnrollment::PACE_FREE)->orderBy('e.id', 'ASC')->getQuery()->toIterable();
        foreach ($enrollments as $enrollment) {
            $result = $this->sendFor($enrollment, $today);
            if (null !== $result) {
                ++$sent[$result];
            }
        }
        $this->em->flush();

        return $sent;
    }

    /** @return 'lecons'|'pauses'|null ce qui a été envoyé pour cette inscription */
    public function sendFor(CourseEnrollment $enrollment, \DateTimeImmutable $today): ?string
    {
        $user = $enrollment->getUser();
        $course = $enrollment->getCourse();
        if (!$enrollment->isPaced() || !$enrollment->isPaceDay($today) || $enrollment->wasEmailedOn($today) || !$user->isVerified()) {
            return null;
        }
        if (!$course->isFree() && !$this->entitlements->isPro($user)) {
            return null; // parcours PRO et le membre ne l'est plus : rien à lui envoyer
        }
        $lesson = $enrollment->nextLesson();
        if (null === $lesson) {
            return null;
        }
        $key = 'academie-'.$enrollment->getId().'-'.$today->format('Y-m-d');

        if ($enrollment->getEmailsWithoutProgress() >= CourseEnrollment::MAX_EMAILS_WITHOUT_PROGRESS) {
            $enrollment->pausePace();
            $this->em->flush();
            $title = 'Votre rythme de l\'Académie est en pause';
            $this->notifier->notify($user, NotificationType::ACADEMY_LESSON, $title,
                'Nous mettons en pause les e-mails du parcours « '.$course->getTitle().' ». Reprenez quand vous le voulez.',
                $this->urls->generate('app_academie_course', ['slug' => $course->getSlug()]).'#rythme', $key.'-pause',
                ['template' => 'academie/email/pause.html.twig', 'context' => ['course' => $course, 'lesson' => $lesson]]);

            return 'pauses';
        }

        $url = $this->urls->generate('app_academie_lesson', ['slug' => $course->getSlug(), 'lessonSlug' => $lesson->getSlug()]);
        if ($course->isPlanJourney()) {
            // Parcours de plan (parcours guidés, 26/11/2026) : la séance du jour, ce qui a été posé la dernière fois, son verset
            $ok = $this->notifier->notify($user, NotificationType::ACADEMY_LESSON, 'Aujourd\'hui : '.mb_lcfirst($lesson->getTitle()),
                $course->getTitle().' · station '.($enrollment->progress()['done'] + 1).' sur '.$enrollment->progress()['total'].' · '.$lesson->getDurationMinutes().' min',
                $url, $key,
                ['template' => 'academie/email/seance.html.twig', 'context' => [
                    'course' => $course, 'lesson' => $lesson, 'progress' => $enrollment->progress(), 'pace' => $enrollment->getPaceMode(),
                    'last' => self::lastPosed($enrollment), 'verse' => self::verse((string) $lesson->getContentMarkdown()),
                ]]);
        } else {
            $ok = $this->notifier->notify($user, NotificationType::ACADEMY_LESSON, 'Votre leçon du jour : '.$lesson->getTitle(),
                $course->getTitle().' · environ '.$lesson->getDurationMinutes().' min', $url, $key,
                ['template' => 'academie/email/lecon.html.twig', 'context' => ['course' => $course, 'lesson' => $lesson, 'progress' => $enrollment->progress(), 'pace' => $enrollment->getPaceMode()]]);
        }
        if ($ok) {
            $enrollment->recordEmail($today);
            $this->em->flush();
        }

        return $ok ? 'lecons' : null;
    }

    /**
     * Ce que la personne a posé dans son plan à sa dernière séance terminée (« La dernière fois, vous avez posé… »).
     *
     * @return array{lesson: string, text: string}|null
     */
    public static function lastPosed(CourseEnrollment $enrollment): ?array
    {
        $last = null;
        foreach ($enrollment->getCompletions() as $c) {
            if ('' !== trim((string) ($c->getResponse()['title'] ?? '')) && (null === $last || $c->getUpdatedAt() > $last->getUpdatedAt())) {
                $last = $c;
            }
        }

        return null === $last ? null : ['lesson' => $last->getLesson()->getTitle(), 'text' => mb_strimwidth(trim((string) $last->getResponse()['title']), 0, 220, '…')];
    }

    /** Le verset du temps « Méditer » de la séance (première citation de l'encadré :::mediter), ou null. */
    public static function verse(string $markdown): ?string
    {
        if (!preg_match('/^:::mediter[^\n]*\n(.*?)^:::\s*$/msu', $markdown, $m)) {
            return null;
        }
        foreach (explode("\n", $m[1]) as $line) {
            if (str_starts_with(ltrim($line), '>')) {
                return trim(ltrim(trim($line), '> '));
            }
        }

        return null;
    }

    /** Lien de désinscription des e-mails de l'Académie : met en pause tous les rythmes du membre. */
    public function pauseAll(User $user): int
    {
        $count = 0;
        foreach ($this->em->getRepository(CourseEnrollment::class)->findBy(['user' => $user]) as $enrollment) {
            if ($enrollment->isPaced()) {
                $enrollment->pausePace();
                ++$count;
            }
        }
        $this->em->flush();

        return $count;
    }
}
