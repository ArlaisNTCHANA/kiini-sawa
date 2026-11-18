<?php

namespace App\Bilan\Service;

use App\Abonnement\Service\Entitlements;
use App\Bilan\Controller\MonthlyReviewController;
use App\PlanDHygiene\Entity\DailyLog;
use App\Bilan\Entity\MonthlyReview;
use App\Bilan\Entity\WeeklyReview;
use App\Compte\Entity\User;
use App\PlanDeVie\Entity\Season;
use Doctrine\ORM\EntityManagerInterface;

/**
 * « Mes rythmes » : les cinq rendez-vous de la méthode (jour, semaine, mois, saison, année) avec, pour chacun, ce qui est fait,
 * ce qui est à faire maintenant et ce qui est en retard. Aucune nouvelle donnée : tout est lu dans les revues existantes.
 *
 * États : « done » (fait), « todo » (à faire maintenant), « late » (la période précédente n'a pas été revue alors que l'on a
 * déjà l'habitude de ce rythme : on ne culpabilise pas un débutant), « later » (pas encore l'heure).
 */
final class Rhythms
{
    public const EVENING_HOUR = 18;

    public function __construct(private readonly EntityManagerInterface $em, private readonly Entitlements $entitlements)
    {
    }

    /** @return list<array{key: string, label: string, icon: string, state: string, status: string, text: string, cta: string, route: string, locked: bool}> */
    public function forUser(User $user, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        return [$this->day($user, $now), $this->week($user, $now), $this->month($user, $now), $this->season($user, $now), $this->year()];
    }

    private function day(User $user, \DateTimeImmutable $now): array
    {
        $plan = $user->getPlanDHygiene();
        $log = fn (string $date) => $plan ? $this->em->getRepository(DailyLog::class)->findOneBy(['planDHygiene' => $plan, 'date' => new \DateTime($date)]) : null;
        $done = null !== $log($now->format('Y-m-d'));
        $evening = (int) $now->format('G') >= self::EVENING_HOUR;
        $hadAny = $plan && null !== $this->em->getRepository(DailyLog::class)->findOneBy(['planDHygiene' => $plan]);
        $late = !$done && $hadAny && null === $log($now->modify('-1 day')->format('Y-m-d'));

        [$state, $status] = match (true) {
            $done => ['done', 'Fait aujourd\'hui'],
            $late => ['late', 'En retard : hier n\'a pas été relu'],
            $evening => ['todo', 'À faire ce soir'],
            default => ['later', 'Ce soir, à partir de 18 h'],
        };

        return $this->item('day', 'Le jour', 'moon', $state, $status, 'Cinq minutes pour relire votre journée : votre intention, votre énergie, un motif de gratitude.', 'Faire mon bilan du jour', 'app_daily_review');
    }

    private function week(User $user, \DateTimeImmutable $now): array
    {
        $monday = $now->modify('monday this week')->setTime(0, 0);
        $repo = $this->em->getRepository(WeeklyReview::class);
        $done = null !== $repo->findOneBy(['user' => $user, 'weekStart' => $monday]);
        $previous = null !== $repo->findOneBy(['user' => $user, 'weekStart' => $monday->modify('-7 days')]);
        $hadAny = [] !== $repo->findBy(['user' => $user], null, 1);
        $weekday = (int) $now->format('N');
        $weekend = 7 === $weekday || (6 === $weekday && (int) $now->format('G') >= self::EVENING_HOUR);

        [$state, $status] = match (true) {
            $done => ['done', 'Faite cette semaine'],
            $hadAny && !$previous && $weekday <= 5 => ['late', 'En retard : la semaine dernière n\'a pas été relue'],
            $weekend => ['todo', 'À faire maintenant'],
            default => ['later', 'Samedi soir ou dimanche'],
        };

        return $this->item('week', 'La semaine', 'calendar-check', $state, $status, 'Relire la semaine qui s\'achève pour préparer la suivante : ce qui a avancé, ce qui a coincé.', 'Faire ma revue de la semaine', 'app_weekly_review');
    }

    private function month(User $user, \DateTimeImmutable $now): array
    {
        $reviewed = MonthlyReviewController::reviewedMonth($now);
        $repo = $this->em->getRepository(MonthlyReview::class);
        $has = fn (\DateTimeImmutable $m) => null !== $repo->findOneBy(['user' => $user, 'month' => \DateTime::createFromImmutable($m)]);
        $done = $has($reviewed);
        $hadAny = [] !== $repo->findBy(['user' => $user], null, 1);
        $day = (int) $now->format('j');
        $endOfMonth = $day >= (int) $now->format('t') - 2;

        [$state, $status] = match (true) {
            $done => ['done', 'Faite pour '.$this->monthName($reviewed)],
            $hadAny && $day > 7 && !$has($reviewed->modify('-1 month')) => ['late', 'En retard : '.$this->monthName($reviewed->modify('-1 month')).' n\'a pas été relu'],
            $day <= 7 || $endOfMonth => ['todo', 'À faire : '.$this->monthName($reviewed)],
            default => ['later', 'Fin du mois ou première semaine du suivant'],
        };

        return $this->item('month', 'Le mois', 'bar-chart-2', $state, $status, 'Ce qui a progressé, ce qui stagne, le blocage principal, et une décision pour le mois suivant.', 'Faire ma revue du mois', 'app_monthly_review', !$this->entitlements->can($user, 'monthly_review'));
    }

    private function season(User $user, \DateTimeImmutable $now): array
    {
        $season = $this->em->getRepository(Season::class)->findOneBy(['user' => $user], ['startDate' => 'DESC']);
        $today = $now->setTime(0, 0);

        if (!$season) {
            return $this->item('season', 'La saison', 'sprout', 'todo', 'Pas encore de saison', 'Une saison dure environ trois mois et porte un thème dominant. La nommer, c\'est savoir à quoi dire « pas maintenant ».', 'Commencer ma saison', 'app_season');
        }
        $end = \DateTimeImmutable::createFromInterface($season->getEndDate())->setTime(0, 0);
        $label = (Seasons::KINDS[$season->getKind()]['label'] ?? 'Ma saison').($season->getTheme() ? ' · '.$season->getTheme() : '');

        [$state, $status] = match (true) {
            null !== $season->getReviewedAt() => ['done', 'Revue faite : '.$label],
            $end < $today => ['late', 'En retard : la saison « '.$label.' » est terminée, sans revue'],
            $end <= $today->modify('+14 days') => ['todo', 'Fin de saison le '.$end->format('d/m').' : '.$label],
            default => ['later', $label.' · jusqu\'au '.$end->format('d/m/Y')],
        };

        return $this->item('season', 'La saison', 'sprout', $state, $status, 'Regarder le thème, les jalons, et choisir la saison suivante.', 'Ma saison', 'app_season');
    }

    private function year(): array
    {
        return $this->item('year', 'L\'année', 'telescope', 'later', 'Une fois par an', 'Relire votre But, vos valeurs et votre banque de découvertes (résultats de tests, paroles reçues, retours de vos proches).', 'Relire mon Plan de Vie', 'app_plan_de_vie');
    }

    private function monthName(\DateTimeImmutable $d): string
    {
        return (new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'MMMM y'))->format($d);
    }

    private function item(string $key, string $label, string $icon, string $state, string $status, string $text, string $cta, string $route, bool $locked = false): array
    {
        return ['key' => $key, 'label' => $label, 'icon' => $icon, 'state' => $state, 'status' => $status, 'text' => $text, 'cta' => $cta, 'route' => $route, 'locked' => $locked];
    }
}
