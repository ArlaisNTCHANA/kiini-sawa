<?php

namespace App\Couple\Service;

use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\User;
use App\Couple\Entity\Couple;
use App\Couple\Entity\CoupleItem;
use App\Couple\Entity\CoupleReview;
use App\Couple\Repository\CoupleRepository;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use App\Personnalite\Entity\ProfileTest;
use App\Personnalite\Psychometrie\AssessmentService;
use App\Personnalite\Psychometrie\EnneagramBank;
use App\Personnalite\Psychometrie\Interpretations;
use App\Personnalite\Psychometrie\IpipItemBank;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Espace couple : invitation, accès, éléments communs, revue, rapport croisé.
 *
 * Accès (décision du 15/11/2026) : réservé aux couples où les deux conjoints sont PRO,
 * par exemple grâce à l'abonnement Duo (un paiement pour les deux).
 */
final class CoupleService
{
    /** Thèmes de croissance proposés au couple. */
    public const THEMES = [
        'COMMUNICATION' => ['Communication', 'message-circle', 'S\'écouter vraiment, exprimer ses besoins, gérer les désaccords.', 'Un temps d\'échange de 20 minutes par semaine, sans écran.'],
        'SPIRITUALITE' => ['Vie spirituelle', 'flame', 'Prier, lire la Bible et servir ensemble.', 'Un moment de prière à deux, chaque jour ou chaque semaine.'],
        'FINANCES' => ['Finances', 'wallet', 'Budget, épargne, générosité et projets : décider ensemble.', 'Un point budget mensuel, chiffres en main.'],
        'INTIMITE' => ['Intimité et tendresse', 'heart', 'Cultiver la tendresse, la complicité et la vie intime.', 'Un rendez-vous amoureux chaque mois.'],
        'PARENTALITE' => ['Parentalité', 'baby', 'Éduquer d\'un même cœur, transmettre des valeurs.', 'Un point régulier sur chaque enfant.'],
        'FAMILLE' => ['Familles et belles-familles', 'users', 'Honorer ses parents tout en protégeant le couple.', 'Décider ensemble des visites et des limites.'],
        'TEMPS' => ['Temps et rythme', 'clock', 'Équilibrer travail, repos et temps à deux.', 'Bloquer un soir par semaine pour le couple.'],
        'PROJETS' => ['Projets et vocation', 'compass', 'Porter ensemble une vision et soutenir la vocation de chacun.', 'Relire votre vision commune chaque trimestre.'],
        'SANTE' => ['Santé et énergie', 'activity', 'Prendre soin de son corps et de celui de l\'autre.', 'Une activité physique partagée.'],
        'SERVICE' => ['Service et hospitalité', 'gift', 'Ouvrir sa maison et servir ensemble.', 'Accueillir quelqu\'un à table une fois par mois.'],
    ];

    public const PROJECT_CATEGORIES = ['BE' => 'Être', 'DO' => 'Faire', 'HAVE' => 'Avoir', 'GIVE' => 'Donner'];
    public const RITUAL_FREQUENCIES = ['DAILY' => 'Chaque jour', 'WEEKLY' => 'Chaque semaine', 'MONTHLY' => 'Chaque mois'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CoupleRepository $couples,
        private readonly Entitlements $entitlements,
        private readonly Notifier $notifier,
        private readonly AssessmentService $assessments,
    ) {
    }

    public function coupleOf(User $user): ?Couple
    {
        return $this->couples->findFor($user);
    }

    /** L'espace couple est ouvert si les deux conjoints sont PRO. */
    public function canUse(Couple $couple): bool
    {
        return $couple->isActive() && $this->entitlements->isPro($couple->getPartnerA()) && $this->entitlements->isPro($couple->getPartnerB());
    }

    public function create(User $user, ?string $invitedEmail): Couple
    {
        if ($this->coupleOf($user)) {
            throw new \DomainException('Vous avez déjà un espace couple.');
        }
        $couple = new Couple($user, $invitedEmail ? mb_strtolower(trim($invitedEmail)) : null);
        $this->em->persist($couple);
        $this->em->flush();

        return $couple;
    }

    public function join(User $user, Couple $couple, bool $shareTests): void
    {
        if ($couple->isActive()) {
            throw new \DomainException('Cet espace couple est déjà complet.');
        }
        if ($couple->getPartnerA() === $user) {
            throw new \DomainException('Vous ne pouvez pas accepter votre propre invitation : envoyez le lien à votre conjoint.');
        }
        $existing = $this->coupleOf($user);
        if ($existing) {
            throw new \DomainException('Vous faites déjà partie d\'un espace couple.');
        }
        $couple->join($user, $shareTests);
        $this->em->flush();
        $this->notifier->notify($couple->getPartnerA(), NotificationType::CONNECTION_ACCEPTED, $user->getDisplayName().' a rejoint votre espace couple',
            'Votre espace couple est prêt : posez ensemble votre vision, vos valeurs et vos projets communs.', '/couple', 'couple '.$couple->getId());
    }

    /** Quitter : l'espace commun est supprimé (les espaces personnels ne sont pas touchés). */
    public function dissolve(Couple $couple): void
    {
        $this->em->remove($couple);
        $this->em->flush();
    }

    public function addItem(Couple $couple, User $by, string $kind, array $data): CoupleItem
    {
        if (!isset(CoupleItem::KINDS[$kind])) {
            throw new \InvalidArgumentException('Type d\'élément inconnu.');
        }
        $title = trim((string) ($data['title'] ?? ''));
        if ('THEME' === $kind && isset(self::THEMES[$data['category'] ?? ''])) {
            $title = $title ?: self::THEMES[$data['category']][0];
        }
        if ('' === $title) {
            throw new \InvalidArgumentException('Donnez un titre.');
        }
        $item = new CoupleItem($couple, $kind, mb_substr($title, 0, 255), $by);
        $this->apply($item, $data);
        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }

    public function updateItem(CoupleItem $item, array $data): void
    {
        if (isset($data['title']) && '' !== trim((string) $data['title'])) {
            $item->setTitle(mb_substr(trim($data['title']), 0, 255));
        }
        $this->apply($item, $data);
        $this->em->flush();
    }

    private function apply(CoupleItem $item, array $data): void
    {
        if (\array_key_exists('details', $data)) {
            $item->setDetails(trim((string) $data['details']) ?: null);
        }
        $category = $data['category'] ?? null;
        $allowed = match ($item->getKind()) {
            'PROJECT' => self::PROJECT_CATEGORIES,
            'THEME' => self::THEMES,
            'RITUAL' => self::RITUAL_FREQUENCIES,
            default => [],
        };
        if (null !== $category && isset($allowed[$category])) {
            $item->setCategory($category);
        }
        if (\array_key_exists('deadline', $data)) {
            $item->setDeadline(\DateTimeImmutable::createFromFormat('!Y-m-d', (string) $data['deadline']) ?: null);
        }
        if (isset($data['progress']) && is_numeric($data['progress'])) {
            $item->setProgress((int) $data['progress']);
        }
    }

    public function reviewFor(Couple $couple, \DateTimeImmutable $month): ?CoupleReview
    {
        return $this->em->getRepository(CoupleReview::class)->findOneBy(['couple' => $couple, 'month' => $month]);
    }

    public function saveReview(Couple $couple, User $by, array $answers): CoupleReview
    {
        $month = new \DateTimeImmutable('first day of this month midnight');
        $review = $this->reviewFor($couple, $month) ?? new CoupleReview($couple, $month);
        $clean = [];
        foreach (array_keys(CoupleReview::QUESTIONS) as $k) {
            $clean[$k] = mb_substr(trim((string) ($answers[$k] ?? '')), 0, 3000);
        }
        $review->setAnswers($clean);
        $this->em->persist($review);
        $this->em->flush();
        if ($partner = $couple->partnerOf($by)) {
            $this->notifier->notify($partner, NotificationType::MONTHLY_REVIEW_REMINDER, 'Revue de couple mise à jour',
                $by->getDisplayName().' a complété votre revue de couple du mois. Prenez un moment pour la relire ensemble.', '/couple#revue', 'revue-couple '.$review->getId().' '.date('Y-m-d'));
        }

        return $review;
    }

    /** @return CoupleReview[] */
    public function reviews(Couple $couple): array
    {
        return $this->em->getRepository(CoupleReview::class)->findBy(['couple' => $couple], ['month' => 'DESC'], 12);
    }

    // ── Rapport croisé ────────────────────────────────────────────────

    /**
     * Comparaison des profils des deux conjoints (Big Five, type, style, ennéagramme), avec des pistes pour le couple.
     * Uniquement si chacun a accepté de partager ses résultats de tests.
     */
    public function crossReport(Couple $couple): array
    {
        $a = $couple->getPartnerA();
        $b = $couple->getPartnerB();
        $people = [];
        foreach ([$a, $b] as $u) {
            $profile = $couple->sharesTests($u) ? $this->assessments->currentProfile($u) : null;
            $enn = $couple->sharesTests($u) ? $this->latest($u, 'ENNEAGRAM') : null;
            $people[] = ['user' => $u, 'shares' => $couple->sharesTests($u), 'profile' => $profile, 'enneagram' => $enn ? (json_decode($enn->getResult(), true)['type'] ?? null) : null];
        }

        $domains = [];
        if ($people[0]['profile'] && $people[1]['profile']) {
            foreach (IpipItemBank::DOMAINS as $d => $label) {
                $sa = $people[0]['profile']['domains'][$d]['score'] ?? null;
                $sb = $people[1]['profile']['domains'][$d]['score'] ?? null;
                if (null === $sa || null === $sb) {
                    continue;
                }
                $gap = abs($sa - $sb);
                $domains[$d] = ['label' => $label, 'a' => $sa, 'b' => $sb, 'gap' => $gap, 'tip' => $gap >= 25 ? self::DOMAIN_TIPS[$d] : null];
            }
        }

        $styles = null;
        $sa = $people[0]['profile']['style'] ?? null;
        $sb = $people[1]['profile']['style'] ?? null;
        if ($sa && $sb) {
            $styles = [
                'a' => Interpretations::STYLES[$sa['primary']] + ['code' => $sa['primary']],
                'b' => Interpretations::STYLES[$sb['primary']] + ['code' => $sb['primary']],
                'same' => $sa['primary'] === $sb['primary'],
            ];
        }
        $types = null;
        $ta = $people[0]['profile']['type'] ?? null;
        $tb = $people[1]['profile']['type'] ?? null;
        if ($ta && $tb) {
            $shared = 0;
            foreach (str_split($ta['code']) as $i => $l) {
                $shared += $l === $tb['code'][$i] ? 1 : 0;
            }
            $types = ['a' => $ta['code'], 'b' => $tb['code'], 'aName' => Interpretations::TYPES[$ta['code']][0], 'bName' => Interpretations::TYPES[$tb['code']][0], 'shared' => $shared,
                'axes' => array_map(fn ($k) => ['axis' => $k, 'a' => $ta['axes'][$k]['letter'], 'b' => $tb['axes'][$k]['letter'], 'tip' => $ta['axes'][$k]['letter'] !== $tb['axes'][$k]['letter'] ? self::AXIS_TIPS[$k] : null], array_keys($ta['axes']))];
        }
        $enneagram = null;
        if ($people[0]['enneagram'] && $people[1]['enneagram']) {
            $enneagram = ['a' => EnneagramBank::TYPES[$people[0]['enneagram']] + ['n' => $people[0]['enneagram']], 'b' => EnneagramBank::TYPES[$people[1]['enneagram']] + ['n' => $people[1]['enneagram']]];
        }

        return ['people' => $people, 'domains' => $domains, 'styles' => $styles, 'types' => $types, 'enneagram' => $enneagram];
    }

    private function latest(User $u, string $type): ?ProfileTest
    {
        return $this->em->getRepository(ProfileTest::class)->findOneBy(['user' => $u, 'type' => $type], ['date' => 'DESC', 'id' => 'DESC']);
    }

    /** Pistes quand les deux conjoints diffèrent nettement sur une dimension. */
    public const DOMAIN_TIPS = [
        'N' => 'L\'un vit les tensions plus intensément que l\'autre. Le plus serein peut offrir du calme sans minimiser ; le plus sensible peut nommer ce qu\'il ressent avant que cela déborde.',
        'E' => 'L\'un se ressource avec les autres, l\'autre dans le calme. Planifiez à la fois des sorties et des soirées à deux, et respectez le besoin de recharge de chacun.',
        'O' => 'L\'un aime la nouveauté, l\'autre les repères. Alternez découvertes et traditions, et présentez les changements en expliquant leur sens.',
        'A' => 'L\'un cherche l\'harmonie, l\'autre la franchise. Convenez d\'une manière de dire les désaccords : franche sur le fond, douce dans la forme.',
        'C' => 'L\'un aime planifier, l\'autre improviser. Répartissez les responsabilités selon les forces de chacun et gardez des espaces de spontanéité.',
    ];

    public const AXIS_TIPS = [
        'EI' => 'Vos énergies diffèrent : prévoyez du temps social et du temps calme.',
        'SN' => 'L\'un part des faits, l\'autre des idées : les deux regards sont utiles pour décider.',
        'TF' => 'L\'un décide par la logique, l\'autre par les valeurs : écoutez les deux avant de trancher.',
        'JP' => 'L\'un aime conclure, l\'autre garder des options : fixez ensemble ce qui doit être décidé, et quand.',
    ];
}
