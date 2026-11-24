<?php

namespace App\Academie\Service;

use App\Academie\Entity\Lesson;
use App\Academie\Entity\LessonNote;
use App\Compte\Entity\User;
use App\Journal\Entity\JournalCollection;
use App\Journal\Entity\ReflectionNote;
use App\Journal\Enum\ReflectionType;
use App\PlanDeVie\Service\PlanCatalog;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Les notes prises pendant une leçon vont directement dans le Journal, dans un carnet « Académie » créé au besoin
 * (décision du fondateur, 24/11/2026 : pas de carnet séparé). Chaque note garde la leçon et le plan auxquels elle se
 * rapporte (LessonNote). Les comptes gratuits peuvent écrire ces notes même si le Journal est réservé à PRO.
 */
final class AcademyJournal
{
    public const COLLECTION = 'Académie';
    public const MAX_LENGTH = 5000;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function collection(User $user): JournalCollection
    {
        $collection = $this->em->getRepository(JournalCollection::class)->findOneBy(['user' => $user, 'name' => self::COLLECTION]);
        if (null === $collection) {
            $collection = (new JournalCollection())->setUser($user)->setName(self::COLLECTION)->setIcon('graduation-cap')->setColor('#2E6B53');
            $this->em->persist($collection);
        }

        return $collection;
    }

    /** Nouvelle note du carnet « Académie » (non enregistrée : à l'appelant de la remplir). */
    public function newNote(User $user): ReflectionNote
    {
        return (new ReflectionNote())->setUser($user)->setCollection($this->collection($user))->setType(ReflectionType::INSIGHT);
    }

    public function addLessonNote(User $user, Lesson $lesson, string $content, ?string $planKey): LessonNote
    {
        $content = trim(mb_substr($content, 0, self::MAX_LENGTH));
        if ('' === $content) {
            throw new \InvalidArgumentException('Écrivez votre note.');
        }
        $planKey = isset(PlanCatalog::PLANS[$planKey ?? '']) ? $planKey : null;
        $note = $this->newNote($user)->setContent($content);
        $link = new LessonNote($note, $lesson, $planKey);
        $this->em->persist($note);
        $this->em->persist($link);
        $this->em->flush();

        return $link;
    }

    /** Supprime la note du Journal (et son lien à la leçon). */
    public function delete(LessonNote $link): void
    {
        $note = $link->getNote();
        $this->em->remove($link);
        $this->em->remove($note);
        $this->em->flush();
    }
}
