<?php

namespace App\Personnalite\Service;

use App\Compte\Entity\User;
use App\Feedback\Service\FeedbackService;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use App\Personnalite\Entity\ConfirmationAnswer;
use App\Personnalite\Entity\ConfirmationRequest;
use App\Personnalite\Psychometrie\Content;
use App\Personnalite\Psychometrie\DiscBank;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Confirmation des résultats par les proches : le DISC (quelle description vous ressemble le plus / le moins) et les
 * dons spirituels (jusqu'à 5 dons reconnus). Détail visible à partir de 3 réponses (anonymat).
 */
final class ConfirmationService
{
    public const INSTRUMENTS = ['disc' => 'DISC', 'dons-spirituels' => 'Dons spirituels'];
    public const MIN_FOR_DETAIL = 3;
    public const MAX_ANSWERS = 50;
    public const MAX_GIFTS = 5;

    public function __construct(private readonly EntityManagerInterface $em, private readonly Notifier $notifier)
    {
    }

    public function current(User $user, string $instrument): ?ConfirmationRequest
    {
        return $this->em->getRepository(ConfirmationRequest::class)->findOneBy(['user' => $user, 'instrument' => $instrument], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    public function open(User $user, string $instrument): ConfirmationRequest
    {
        if (!isset(self::INSTRUMENTS[$instrument])) {
            throw new \InvalidArgumentException('Test inconnu.');
        }
        $request = $this->current($user, $instrument);
        if ($request) {
            $request->reopen();
        } else {
            $request = new ConfirmationRequest($user, $instrument);
            $this->em->persist($request);
        }
        $this->em->flush();

        return $request;
    }

    public function close(User $user, string $instrument): void
    {
        $this->current($user, $instrument)?->close();
        $this->em->flush();
    }

    public function findOpen(string $token): ?ConfirmationRequest
    {
        $r = $this->em->getRepository(ConfirmationRequest::class)->findOneBy(['token' => $token]);

        return $r && $r->isOpen() ? $r : null;
    }

    /** Descriptions des quatre dimensions, sans lettre ni nom (pour ne pas orienter la réponse). */
    public static function discChoices(string $token): array
    {
        $out = Content::get('disc.observed');
        // Ordre propre à chaque lien, stable d'une visite à l'autre
        uksort($out, fn ($a, $b) => crc32($token.$a) <=> crc32($token.$b));

        return $out;
    }

    /** @return list<string> erreurs */
    public function submit(ConfirmationRequest $request, array $data): array
    {
        $errors = [];
        if ($request->getAnswers()->count() >= self::MAX_ANSWERS) {
            return ['Cette demande a reçu le nombre maximal de réponses. Merci !'];
        }
        $relationship = isset(FeedbackService::RELATIONSHIPS[$data['relationship'] ?? '']) ? $data['relationship'] : 'autre';
        if ('disc' === $request->getInstrument()) {
            $most = (string) ($data['most'] ?? '');
            $least = (string) ($data['least'] ?? '');
            if (!\in_array($most, DiscBank::LETTERS, true) || !\in_array($least, DiscBank::LETTERS, true)) {
                $errors[] = 'Choisissez la description qui ressemble le plus et celle qui ressemble le moins.';
            } elseif ($most === $least) {
                $errors[] = 'Choisissez deux descriptions différentes.';
            }
            $payload = ['most' => $most, 'least' => $least];
        } else {
            $known = array_keys(Content::get('gifts.gifts'));
            $gifts = array_values(array_unique(array_intersect((array) ($data['gifts'] ?? []), $known)));
            if (!$gifts) {
                $errors[] = 'Cochez au moins un don que vous reconnaissez.';
            } elseif (\count($gifts) > self::MAX_GIFTS) {
                $errors[] = sprintf('Cochez au plus %d dons.', self::MAX_GIFTS);
            }
            $payload = ['gifts' => $gifts];
        }
        if ($errors) {
            return $errors;
        }
        $answer = new ConfirmationAnswer($request, $relationship, $payload, $data['name'] ?? null);
        $request->getAnswers()->add($answer);
        $this->em->persist($answer);
        $this->em->flush();

        $owner = $request->getUser();
        $this->notifier->notify($owner, NotificationType::FEEDBACK_RECEIVED, 'Un proche a répondu à votre demande de confirmation',
            sprintf('Une nouvelle réponse pour votre %s (%d au total).', self::INSTRUMENTS[$request->getInstrument()], $request->getAnswers()->count()),
            '/mes-tests/');

        return [];
    }

    /**
     * Synthèse pour le rapport.
     *
     * @param array $report sortie de ReportBuilder::build()
     */
    public function summary(?ConfirmationRequest $request, array $report): ?array
    {
        if (!$request) {
            return null;
        }
        $answers = $request->getAnswers()->toArray();
        $n = \count($answers);
        $out = ['request' => $request, 'count' => $n, 'enough' => $n >= self::MIN_FOR_DETAIL, 'min' => self::MIN_FOR_DETAIL, 'instrument' => $request->getInstrument()];
        if (!$out['enough']) {
            return $out;
        }
        if ('disc' === $request->getInstrument()) {
            $most = array_fill_keys(DiscBank::LETTERS, 0);
            foreach ($answers as $a) {
                ++$most[$a->getPayload()['most']];
            }
            arsort($most);
            $top = array_key_first($most);
            $dims = Content::get('disc.dimensions');
            $mine = $report['disc']['primary'];
            $adapted = $report['disc']['adaptedPrimary'];
            $reading = match (true) {
                $top === $mine => sprintf('Vos proches vous voient d\'abord %s, comme votre profil naturel : votre nature se voit.', mb_strtolower($dims[$top]['name'])),
                $top === $adapted => sprintf('Vos proches vous voient d\'abord %s, comme votre profil adapté : ils connaissent surtout la manière dont vous vous adaptez, peut-être moins votre nature %s.', mb_strtolower($dims[$top]['name']), mb_strtolower($dims[$mine]['name'])),
                default => sprintf('Vos proches vous voient d\'abord %s, alors que votre test indique %s : une belle question à explorer avec eux.', mb_strtolower($dims[$top]['name']), mb_strtolower($dims[$mine]['name'])),
            };
            $out += ['most' => array_map(fn ($c, $l) => ['letter' => $l, 'name' => $dims[$l]['name'], 'color' => $dims[$l]['color'], 'count' => $c], $most, array_keys($most)),
                'reading' => $reading, 'agree' => $top === $mine];
        } else {
            $cited = [];
            foreach ($answers as $a) {
                foreach ($a->getPayload()['gifts'] as $g) {
                    $cited[$g] = ($cited[$g] ?? 0) + 1;
                }
            }
            arsort($cited);
            $names = array_map(fn ($g) => $g['name'], Content::get('gifts.gifts'));
            $mine = array_column($report['gifts']['top'], 'code');
            $out += [
                'confirmed' => array_values(array_map(fn ($g) => ['name' => $names[$g], 'count' => $cited[$g]], array_filter($mine, fn ($g) => ($cited[$g] ?? 0) > 0))),
                'unseen' => array_values(array_map(fn ($g) => $names[$g], array_filter($mine, fn ($g) => !isset($cited[$g])))),
                'others' => array_slice(array_values(array_map(fn ($g) => ['name' => $names[$g], 'count' => $cited[$g]], array_filter(array_keys($cited), fn ($g) => !\in_array($g, $mine, true) && $cited[$g] >= 2))), 0, 4),
            ];
        }

        return $out;
    }
}
