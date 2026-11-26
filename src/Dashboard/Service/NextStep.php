<?php

namespace App\Dashboard\Service;

use App\Abonnement\Service\Entitlements;
use App\PlanDHygiene\Entity\DailyLog;
use App\Bilan\Repository\WeeklyReviewRepository;
use App\Bilan\Service\WeeklyReviewService;
use App\Compte\Entity\User;
use App\Methode\Entity\JourneyProgress;
use App\Methode\Service\Journey;
use App\PlanDeVie\Service\PlanCatalog;
use App\PlanDeVie\Service\PlanProgress;
use Doctrine\ORM\EntityManagerInterface;

/**
 * « Ma prochaine étape » : UNE action mise en avant en tête de l'accueil, choisie par des règles ordonnées et lisibles, plus
 * deux actions « ensuite ». L'ordre reflète l'esprit du livre : poser les fondations, tenir le rythme (bilan du soir, revue de la
 * semaine), avancer dans le parcours de 90 jours, puis faire progresser le plan le moins avancé.
 *
 * Règles, dans l'ordre (la première qui s'applique devient « la » prochaine étape ; les suivantes alimentent « ensuite ») :
 *  0 bis. quand le parcours « Mon Plan de Vie » est publié et que « Vos premiers pas » ne sont pas finis : le premier
 *     pas non fait est « la » prochaine étape (plus de doublon entre les deux cartes, décision du 26/11/2026) ;
 *  0. parcours guidés (26/11/2026) : reprendre son parcours de plan en cours ; ou, tant qu'aucun n'a été choisi et que le
 *     Plan de Vie n'est pas complet, « Par où commencer ? » (remplace alors la règle 1) ;
 *  1. fondations : le Plan de Vie n'est pas encore commencé ;
 *  2. le soir (à partir de 18 h) : le bilan du jour n'est pas fait ;
 *  3. samedi soir et dimanche : la revue de la semaine n'est pas faite ;
 *  4. parcours de 90 jours : la semaine en cours n'est pas terminée (ou invitation à le commencer) ;
 *  5. le plan accessible le moins avancé : sa prochaine étape ;
 *  6. sinon : relire son Plan de Vie.
 */
final class NextStep
{
    public const EVENING_HOUR = 18;

    public function __construct(
        private readonly PlanProgress $progress,
        private readonly EntityManagerInterface $em,
        private readonly Entitlements $entitlements,
        private readonly WeeklyReviewRepository $weeklyReviews,
        private readonly WeeklyReviewService $weekly,
        private readonly \App\Academie\Service\AcademyCatalog $academy,
        private readonly DashboardData $dashboard,
    ) {
    }

    /**
     * @return array{kind: string, icon: string, eyebrow: string, title: string, text: string, cta: string, route: string, params: array, then: list<array{title: string, route: string, params: array}>}
     */
    public function forUser(User $user, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $candidates = $this->candidates($user, $now);
        $main = array_shift($candidates);

        return $main + ['then' => array_map(fn ($c) => ['title' => $c['title'], 'route' => $c['route'], 'params' => $c['params']], \array_slice($candidates, 0, 2))];
    }

    /** @return list<array<string, mixed>> candidats, du plus au moins prioritaire ; jamais vide */
    private function candidates(User $user, \DateTimeImmutable $now): array
    {
        $out = [];
        $plans = $this->progress->forUser($user);
        $locked = $this->entitlements->lockedPlans($user);

        // 0 bis. Premiers pas : tant qu'ils ne sont pas finis, le premier non fait passe devant (parcours de plan publié)
        $chooser = false;
        $firstSteps = $this->dashboard->nextSteps($user, $user->getHabits()->toArray());
        $firstUndone = array_values(array_filter($firstSteps, fn (array $s) => !$s['done']))[0] ?? null;
        if (null !== $firstUndone && 'journey' === $firstSteps[0]['key']) {
            $position = array_search($firstUndone, $firstSteps, true) + 1;
            $out[] = $this->item('first-step', 'footprints', 'Vos premiers pas · '.$position.' sur '.\count($firstSteps), $firstUndone['label'], $firstUndone['hint'],
                $firstUndone['cta'], $firstUndone['route'], $firstUndone['params']);
            $chooser = true; // les fondations passent par le parcours : pas de règle 1 en plus
            // « Ensuite » : les premiers pas suivants, pas d'autres écrans qui leur feraient concurrence
            foreach (\array_slice(array_values(array_filter($firstSteps, fn (array $s) => !$s['done'])), 1) as $s) {
                $out[] = $this->item('first-step-next', 'footprints', 'Vos premiers pas', $s['label'], $s['hint'], $s['cta'], $s['route'], $s['params']);
            }
        }

        // 0. Parcours guidés : reprendre, ou choisir par où commencer
        if ($chooser) {
            // la séance à reprendre est déjà le premier pas, ou les premiers pas passent avant
        } elseif (null !== $current = $this->academy->currentPlanJourney($user)) {
            $course = $current->getCourse();
            $progress = $current->progress();
            if (null !== $lesson = $current->nextLesson()) {
                $out[] = $this->item('plan-journey', 'map', 'Mon parcours « '.$course->getTitle().' » · station '.($progress['done'] + 1).' sur '.$progress['total'],
                    $lesson->getTitle(), 'Une séance de '.$lesson->getDurationMinutes().' min : comprendre, méditer, réfléchir, puis écrire dans votre plan.',
                    'Reprendre', 'app_academie_lesson', ['slug' => $course->getSlug(), 'lessonSlug' => $lesson->getSlug()]);
            }
        } elseif ($plans['VIE']['percent'] < 100 && !$this->academy->hasStartedPlanJourney($user) && [] !== $this->academy->planJourneys()) {
            $chooser = true;
            $out[] = $this->item('plan-journey-start', 'map', 'Pour commencer', 'Par où commencer ?', 'Remplissez vos plans en étant accompagné : une courte séance par jour, et vous voyez toujours où vous en êtes.', 'Choisir mon parcours', 'app_academie_start');
        }

        // 1. Fondations
        if (!$chooser && 0 === $plans['VIE']['percent'] && $plans['VIE']['next']) {
            $next = $plans['VIE']['next'];
            $out[] = $this->item('foundation', 'compass', 'Pour commencer', 'Poser vos fondations', 'Votre Plan de Vie est le centre de tout : '.lcfirst($next['label']).'.', 'Commencer', $next['route'], $next['params']);
        }

        // 2. Bilan du soir
        if ((int) $now->format('G') >= self::EVENING_HOUR && !$this->dailyReviewDone($user, $now)) {
            $out[] = $this->item('evening', 'moon', 'Ce soir', 'Faire mon bilan du jour', 'Cinq minutes pour relire votre journée : votre intention, votre énergie, un motif de gratitude.', 'Faire mon bilan', 'app_daily_review');
        }

        // 3. Revue de la semaine (samedi soir et dimanche)
        $weekday = (int) $now->format('N');
        if ((7 === $weekday || (6 === $weekday && (int) $now->format('G') >= self::EVENING_HOUR)) && !$this->weeklyReviewDone($user)) {
            $out[] = $this->item('weekly', 'calendar-check', 'Cette semaine', 'Faire la revue de la semaine', 'Relire la semaine qui s\'achève pour préparer la suivante : ce qui a avancé, ce qui a coincé.', 'Faire ma revue', 'app_weekly_review');
        }

        // 4. Parcours de 90 jours
        $journey = $this->em->getRepository(JourneyProgress::class)->findOneBy(['user' => $user]);
        if ($journey && !$journey->isFinished()) {
            $n = $journey->currentWeek();
            if (!$journey->isWeekDone($n)) {
                $out[] = $this->item('journey', 'route', 'Mon parcours de 90 jours · semaine '.$n.' sur 13', Journey::PLAN[$n]['produce'], 'Lire, écrire dans vos plans, puis marquer la semaine comme terminée.', 'Voir ma semaine', 'app_journey', [], 'semaine-'.$n);
            }
        } elseif (!$journey && null === ($current ?? null) && !$chooser) { // pas d'invitation concurrente pendant un parcours de plan
            $out[] = $this->item('journey-start', 'route', 'Le parcours de 90 jours', 'Commencer mon parcours', 'Treize semaines, environ trois heures par semaine, pour écrire vos six plans chapitre après chapitre.', 'Découvrir le parcours', 'app_journey');
        }

        // 5. Le plan accessible le moins avancé
        $open = array_filter($plans, fn ($p, $key) => !\in_array($key, $locked, true) && $p['percent'] < 100 && $p['next'], \ARRAY_FILTER_USE_BOTH);
        uasort($open, fn ($a, $b) => $a['percent'] <=> $b['percent']);
        foreach (\array_slice($open, 0, 2, true) as $key => $p) {
            $out[] = $this->item('plan', 'target', PlanCatalog::label($key).' · '.$p['percent'].' %', ucfirst($p['next']['label']), 'La prochaine étape de ce plan, le moins avancé de ceux que vous pouvez ouvrir.', 'Continuer', $p['next']['route'], $p['next']['params']);
        }

        // 6. Rien d'urgent
        if (!$out) {
            $out[] = $this->item('done', 'sparkles', 'Tout est à jour', 'Relire mon Plan de Vie', 'Rien d\'urgent aujourd\'hui. Relire votre But et votre vision garde le cap.', 'Ouvrir mon Plan de Vie', 'app_plan_de_vie');
        }

        return $out;
    }

    private function dailyReviewDone(User $user, \DateTimeImmutable $now): bool
    {
        $plan = $user->getPlanDHygiene();

        return $plan && null !== $this->em->getRepository(DailyLog::class)->findOneBy(['planDHygiene' => $plan, 'date' => new \DateTime($now->format('Y-m-d'))]);
    }

    private function weeklyReviewDone(User $user): bool
    {
        return null !== $this->weeklyReviews->findForWeek($user, $this->weekly->currentWeekStart());
    }

    /** @return array<string, mixed> */
    private function item(string $kind, string $icon, string $eyebrow, string $title, string $text, string $cta, string $route, array $params = [], ?string $fragment = null): array
    {
        return ['kind' => $kind, 'icon' => $icon, 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text, 'cta' => $cta, 'route' => $route, 'params' => $params, 'fragment' => $fragment];
    }
}
