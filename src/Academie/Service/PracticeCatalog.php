<?php

namespace App\Academie\Service;

/**
 * Exercices de fin de leçon : où ils écrivent (un des 6 plans, les Rituels, le Journal ou le Sanctuaire du Couple)
 * et ce qu'ils y ajoutent. Liste fermée : l'auteur choisit dans un menu, le pont vers les plans (AcademyPlanBridge,
 * Lot 2) sait traiter chacune de ces actions. L'Académie ajoute, elle ne remplace jamais (ni raison d'être ni Vision :
 * décision du fondateur, 24/11/2026).
 */
final class PracticeCatalog
{
    public const TARGETS = [
        'VIE' => ['label' => 'Plan de Vie', 'button' => 'Enregistrer dans mon Plan de Vie', 'actions' => [
            'add_value' => ['label' => 'Ajouter une valeur', 'entity' => 'Value'],
            'add_life_goal' => ['label' => 'Ajouter un objectif', 'entity' => 'LifeGoal'],
            // Parcours de plan : la séance écrit dans une rubrique entière (PlanRubrics), en ajoutant sans rien retirer
            'write_purpose' => ['label' => 'Séance : écrire sa raison d\'être', 'entity' => null],
            'list_convictions' => ['label' => 'Séance : poser ses convictions profondes', 'entity' => null],
            'list_values' => ['label' => 'Séance : poser ses valeurs', 'entity' => null],
            'list_passions' => ['label' => 'Séance : nommer ses passions', 'entity' => null],
            'list_talents' => ['label' => 'Séance : nommer ses dons et talents', 'entity' => null],
            'write_vision' => ['label' => 'Séance : écrire sa vision', 'entity' => null],
            'list_life_goals' => ['label' => 'Séance : poser ses objectifs (Être, Faire, Avoir, Donner)', 'entity' => null],
        ]],
        'CROISSANCE' => ['label' => 'Plan de Croissance', 'button' => 'Enregistrer dans mon Plan de Croissance', 'actions' => [
            'add_growth_goal' => ['label' => 'Ajouter un objectif de croissance', 'entity' => 'GrowthGoal'],
            'add_principle' => ['label' => 'Ajouter un principe', 'entity' => 'Principle'],
            'list_character_goals' => ['label' => 'Séance : poser ses chantiers de caractère', 'entity' => null],
            'list_relation_goals' => ['label' => 'Séance : poser ses objectifs de relation et de réseau', 'entity' => null],
        ]],
        'DEVELOPPEMENT' => ['label' => 'Plan de Développement personnel', 'button' => 'Enregistrer dans mon Plan de Développement personnel', 'actions' => [
            // Sur le site, le Plan de Développement personnel range compétences et connaissances parmi les objectifs du
            // Plan de Croissance (axe Compétence) : c'est là que l'exercice écrit, pour que la personne le voie.
            'add_skill_goal' => ['label' => 'Ajouter une compétence à développer', 'entity' => 'GrowthGoal'],
            'add_learning_resource' => ['label' => 'Ajouter une connaissance à acquérir', 'entity' => 'GrowthGoal'],
            'list_skills' => ['label' => 'Séance : nommer les compétences à développer', 'entity' => null],
            'list_knowledge' => ['label' => 'Séance : nommer les connaissances à acquérir', 'entity' => null],
        ]],
        'HYGIENE' => ['label' => 'Plan d\'Hygiène de vie', 'button' => 'Enregistrer dans mon Plan d\'Hygiène de vie', 'actions' => [
            'add_hygiene_domain' => ['label' => 'Ajouter un domaine à soigner', 'entity' => 'HygieneDomain'],
            'list_hygiene_domains' => ['label' => 'Séance : nommer les domaines de son hygiène à soigner', 'entity' => null],
        ]],
        'INTENDANCE' => ['label' => 'Plan d\'Intendance', 'button' => 'Enregistrer dans mon Plan d\'Intendance', 'actions' => [
            'add_stewardship_item' => ['label' => 'Ajouter un élément à gérer', 'entity' => 'StewardshipItem'],
            'list_stewardship' => ['label' => 'Séance : faire l\'inventaire de ce qui est confié', 'entity' => null],
        ]],
        'TRANSMISSION' => ['label' => 'Plan de Transmission', 'button' => 'Enregistrer dans mon Plan de Transmission', 'actions' => [
            'add_legacy_goal' => ['label' => 'Ajouter un legs', 'entity' => 'LegacyGoal'],
            'add_transmission_action' => ['label' => 'Ajouter une action de transmission', 'entity' => 'TransmissionAction'],
            'write_eternity' => ['label' => 'Séance : écrire sa phrase d\'éternité', 'entity' => null],
            'list_legacy_goals' => ['label' => 'Séance : poser ce que l\'on veut transmettre', 'entity' => null],
        ]],
        'RITUEL' => ['label' => 'Rituels', 'button' => 'Ajouter à mes Rituels', 'actions' => [
            'add_habit' => ['label' => 'Ajouter un rituel', 'entity' => 'Habit'],
        ]],
        'JOURNAL' => ['label' => 'Journal', 'button' => 'Enregistrer dans mon Journal', 'actions' => [
            'add_note' => ['label' => 'Écrire une note dans le Journal', 'entity' => 'ReflectionNote'],
        ]],
        'COUPLE' => ['label' => 'Sanctuaire du Couple', 'button' => 'Ajouter au Sanctuaire du Couple', 'actions' => [
            'add_couple_item' => ['label' => 'Ajouter un élément au Sanctuaire du Couple', 'entity' => 'CoupleItem'],
        ]],
    ];

    public static function isValid(string $target, string $action): bool
    {
        return isset(self::TARGETS[$target]['actions'][$action]);
    }

    /** Libellé lisible, ex. « Plan d'Intendance : Ajouter un élément à gérer ». */
    public static function label(?string $target, ?string $action): ?string
    {
        if (null === $target || null === $action || !self::isValid($target, $action)) {
            return null;
        }

        return self::TARGETS[$target]['label'].' : '.self::TARGETS[$target]['actions'][$action]['label'];
    }

    /** Libellé du bouton d'accord de l'apprenant, ex. « Enregistrer dans mon Plan d'Intendance ». */
    public static function button(?string $target): string
    {
        return self::TARGETS[$target ?? '']['button'] ?? 'Enregistrer';
    }

    /** @return array<string, array<string, string>> pour le menu déroulant : cible => [« cible|action » => libellé]. */
    public static function choices(): array
    {
        $out = [];
        foreach (self::TARGETS as $key => $target) {
            foreach ($target['actions'] as $action => $def) {
                $out[$target['label']][$key.'|'.$action] = $def['label'];
            }
        }

        return $out;
    }
}
