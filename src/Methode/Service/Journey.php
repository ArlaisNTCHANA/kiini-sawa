<?php

namespace App\Methode\Service;

/**
 * Le parcours de 90 jours (livre, annexe A) : treize semaines, environ trois heures par semaine (une heure de lecture,
 * une heure de Sanctuaire, une heure de mise en pratique). Les colonnes « chapitres » et « ce que tu produis » sont
 * celles du livre, mot pour mot ; « actions » relie chaque semaine aux écrans où l'on écrit son plan.
 */
final class Journey
{
    public const WEEKS = 13;

    /** @var array<int, array{chapters: list<string>, produce: string, actions: list<array{0: string, 1: string, 2?: array}>}> */
    public const PLAN = [
        1 => ['chapters' => ['introduction', 'danger-de-comparer'], 'produce' => 'Ta règle de route contre la comparaison',
            'actions' => [['L\'écrire dans mon journal', 'app_journal']]],
        2 => ['chapters' => ['plan-et-providence', 'clarifier-les-notions'], 'produce' => 'Ta prière de planification ; le tableau des cinq notions',
            'actions' => [['Écrire ma prière dans mon journal', 'app_journal']]],
        3 => ['chapters' => ['but-de-dieu'], 'produce' => 'Ton voyage dans le passé et ta première Équation du But',
            'actions' => [['Écrire mon But de Dieu', 'app_plan_wizard', ['step' => 1, 'single' => 1]]]],
        4 => ['chapters' => ['balance-interne'], 'produce' => 'Ta balance interne : convictions, valeurs, croyances recadrées',
            'actions' => [['Remplir ma balance interne', 'app_plan_wizard', ['step' => 2, 'single' => 1]]]],
        5 => ['chapters' => ['passions', 'dons-et-talents'], 'produce' => 'Tes passions, tes intérêts, tes dons confirmés par trois personnes',
            'actions' => [['Mes passions', 'app_plan_wizard', ['step' => 3, 'single' => 1]], ['Mes dons', 'app_plan_wizard', ['step' => 4, 'single' => 1]], ['Passer le test des dons', 'app_personality_index']]],
        6 => ['chapters' => ['personnalite', 'vision'], 'produce' => 'Ton profil de personnalité ; ta vision écrite',
            'actions' => [['Passer les tests', 'app_personality_index'], ['Ma personnalité', 'app_plan_wizard', ['step' => 5, 'single' => 1]], ['Écrire ma vision', 'app_plan_wizard', ['step' => 6, 'single' => 1]]]],
        7 => ['chapters' => ['objectifs', 'saisons-et-echeancier'], 'produce' => 'Tes objectifs par domaine ; ta saison et tes trois horizons',
            'actions' => [['Mes objectifs', 'app_plan_wizard', ['step' => 7, 'single' => 1]], ['Ma saison', 'app_season']]],
        8 => ['chapters' => ['confirmations'], 'produce' => 'Tes confirmations, et tes premières demandes de conseil envoyées',
            'actions' => [['Demander un feedback nourricier', 'app_feedback']]],
        9 => ['chapters' => ['puissance-sans-plan', 'caractere-et-conviction'], 'produce' => 'Ton radar Samson ; tes trois chantiers de caractère',
            'actions' => [['Mes chantiers de caractère', 'app_plan_wizard', ['step' => 9, 'single' => 1, 'plan' => 'CROISSANCE']]]],
        10 => ['chapters' => ['relation-et-reseau', 'competence-et-connaissance'], 'produce' => 'Ta carte des cinq réseaux ; tes trois compétences prioritaires',
            'actions' => [['Mes réseaux', 'app_plan_wizard', ['step' => 10, 'single' => 1, 'plan' => 'CROISSANCE']], ['Mes compétences', 'app_plan_wizard', ['step' => 8, 'single' => 1, 'plan' => 'DEVELOPPEMENT']]]],
        11 => ['chapters' => ['hygiene-de-vie', 'intendance'], 'produce' => 'Ton Plan d\'Hygiène et ton premier bilan du jour ; ton inventaire d\'intendance',
            'actions' => [['Mon Plan d\'Hygiène', 'app_plan_wizard', ['step' => 12, 'single' => 1, 'plan' => 'HYGIENE']], ['Mon bilan du jour', 'app_daily_review'], ['Mon inventaire d\'intendance', 'app_plan_d_intendance']]],
        12 => ['chapters' => ['perspective-de-l-eternite', 'heritage-et-transmission'], 'produce' => 'Ta phrase d\'éternité ; ton Plan de Transmission',
            'actions' => [['Ma phrase d\'éternité et mes legs', 'app_plan_wizard', ['step' => 11, 'single' => 1, 'plan' => 'TRANSMISSION']]]],
        13 => ['chapters' => ['carte-strategique', 'habitudes', 'revues-feedback-et-defis', 'quand-le-plan-echoue', 'conclusion'], 'produce' => 'Ta Carte stratégique, ta première habitude enracinée, ton calendrier de revues',
            'actions' => [['Ma carte stratégique', 'app_plan_de_vie'], ['Mes rituels', 'app_user_habits'], ['Ma revue de la semaine', 'app_weekly_review']]],
    ];

    /** Semaine en cours (1 à 13) à partir de la date de départ. */
    public static function currentWeek(\DateTimeInterface $startedAt, ?\DateTimeInterface $today = null): int
    {
        $today ??= new \DateTimeImmutable('today');
        $days = (int) \DateTimeImmutable::createFromInterface($startedAt)->setTime(0, 0)->diff(\DateTimeImmutable::createFromInterface($today)->setTime(0, 0))->format('%r%a');

        return max(1, min(self::WEEKS, intdiv(max(0, $days), 7) + 1));
    }
}
