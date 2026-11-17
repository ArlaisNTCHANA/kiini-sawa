<?php

namespace App\PlanDIntendance\Service;

/**
 * Le vocabulaire du Plan d'Intendance, mot pour mot du livre (partie VI, chapitre 18 « Gérer ce qui m'est confié »).
 * Ne pas reformuler sans l'auteur : domaines, questions de l'intendant, états et décisions.
 */
final class Stewardship
{
    /** Ce que le Plan de Vie a mis entre mes mains, avec la question du Plan d'Intendance. */
    public const LIFE_PLAN = [
        'VIE_BUT' => ['label' => 'Mon But de Dieu', 'question' => 'Mes ressources sont-elles mises au service de mon But, ou dispersées ailleurs ?'],
        'VIE_DON' => ['label' => 'Mes dons et talents', 'question' => 'Où sont-ils au travail aujourd\'hui ? Lesquels dorment ? Que rapportent-ils ?'],
        'VIE_PASSION' => ['label' => 'Mes passions et intérêts', 'question' => 'Le temps et l\'argent que j\'y mets sont-ils un investissement ou une fuite ?'],
        'VIE_PERSONNALITE' => ['label' => 'Ma personnalité', 'question' => 'Comment est-ce que je gère, compte tenu de mon tempérament ?'],
        'VIE_VISION' => ['label' => 'Ma vision et mes objectifs', 'question' => 'Quelles ressources dois-je mobiliser, libérer ou protéger pour les atteindre ?'],
        'VIE_SAISON' => ['label' => 'Ma saison', 'question' => 'Que me demande cette saison : semer, épargner, investir, tenir ?'],
    ];

    /** Les sept domaines de mon intendance. */
    public const DOMAINS = [
        'FINANCES' => ['label' => 'Finances et biens', 'icon' => 'wallet', 'content' => 'Vos revenus, votre épargne, vos dettes, votre maison, votre véhicule, vos outils de travail', 'question' => 'Mon argent sert-il mon But, ou décide-t-il à ma place ?'],
        'TEMPS' => ['label' => 'Temps', 'icon' => 'hourglass', 'content' => 'Vos heures, vos semaines, vos saisons', 'question' => 'Où vont réellement mes heures ?'],
        'FOYER' => ['label' => 'Foyer et enfants', 'icon' => 'home', 'content' => 'Votre conjoint, vos enfants, ceux qui vivent sous votre toit', 'question' => 'Qui grandit sous ma responsabilité, et comment est-ce que je le nourris ?'],
        'SAVOIR' => ['label' => 'Ressources intellectuelles', 'icon' => 'lightbulb', 'content' => 'Votre savoir, votre expérience, vos idées, vos contenus', 'question' => 'Qu\'est-ce que je sais qui ne sert encore à personne ?'],
        'INFLUENCE' => ['label' => 'Influence et position', 'icon' => 'megaphone', 'content' => 'Votre poste, votre autorité, votre nom, votre réputation, vos plateformes', 'question' => 'Au service de quoi est-ce que je mets ma voix ?'],
        'RESPONSABILITES' => ['label' => 'Responsabilités confiées', 'icon' => 'key-round', 'content' => 'Une équipe, un ministère, un mandat, le bien ou le projet d\'autrui', 'question' => 'Est-ce que je gère ce qui est à autrui au moins aussi bien que ce qui est à moi ?'],
        'OPPORTUNITES' => ['label' => 'Opportunités et accès', 'icon' => 'door-open', 'content' => 'Les portes ouvertes, les invitations, les moments favorables', 'question' => 'Quelle porte est ouverte aujourd\'hui, que je risque de laisser se refermer ?'],
    ];

    /** L'état : un verdict honnête parmi trois. */
    public const STATUSES = [
        'FRUCTIFIE' => ['label' => 'Il fructifie', 'color' => 'var(--success)', 'bg' => 'var(--success-soft)'],
        'DORT' => ['label' => 'Il dort', 'color' => 'var(--warning)', 'bg' => 'var(--warning-soft)'],
        'FUIT' => ['label' => 'Il fuit', 'color' => 'var(--danger)', 'bg' => 'var(--danger-soft)'],
    ];

    /** La décision : ce que j'en fais. */
    public const DECISIONS = [
        'AFFECTER' => ['label' => 'Affecter', 'help' => 'Le mettre au service de votre But et de vos objectifs'],
        'PROTEGER' => ['label' => 'Protéger', 'help' => 'Le mettre à l\'abri de ce qui le menace'],
        'LIBERER' => ['label' => 'Libérer', 'help' => 'Lâcher ce qui coûte plus qu\'il ne sert, ou le confier à quelqu\'un d\'autre'],
    ];

    /** Le livre recommande trois à cinq éléments prioritaires. */
    public const MAX_PRIORITIES = 5;

    public static function originLabel(string $origin): string
    {
        return self::DOMAINS[$origin]['label'] ?? self::LIFE_PLAN[$origin]['label'] ?? $origin;
    }

    public static function isOrigin(string $origin): bool
    {
        return isset(self::DOMAINS[$origin]) || isset(self::LIFE_PLAN[$origin]);
    }
}
