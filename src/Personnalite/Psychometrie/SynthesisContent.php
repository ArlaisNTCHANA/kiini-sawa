<?php

namespace App\Personnalite\Psychometrie;

/**
 * Textes de la synthèse « Mon profil KIINI SAWA » : zones de vocation (dimension DISC dominante × famille de dons).
 * Contenu original KIINI SAWA, modifiable dans l'administration (source « synth.vocations »).
 */
final class SynthesisContent
{
    public const VOCATIONS = [
        'D' => [
            'parole' => ['name' => 'Le messager qui mobilise', 'text' => 'Votre énergie de décision rencontre un don de parole : vous êtes fait pour porter une parole claire qui met les autres en mouvement. Là où vous parlez, les choses avancent.', 'ideas' => ['Porter une vision devant une équipe ou une assemblée', 'Former et mobiliser des responsables', 'Défendre une cause juste (plaidoyer, justice)'], 'plan' => 'Plan de Transmission', 'read' => 'Jérémie 1.7–9'],
            'service' => ['name' => 'Le bâtisseur pratique', 'text' => 'Vous aimez que les choses se fassent, et vous servez concrètement : vous rendez possibles les projets des autres, avec efficacité et sans attendre.', 'ideas' => ['Piloter la logistique d\'un événement ou d\'un chantier', 'Organiser une action sociale de terrain', 'Mettre vos compétences professionnelles au service d\'une œuvre'], 'plan' => 'Plan de Développement', 'read' => 'Néhémie 2.17–18'],
            'conduite' => ['name' => 'Le pionnier', 'text' => 'Décision et don de conduite : vous êtes fait pour ouvrir des chemins, lancer ce qui n\'existe pas encore et entraîner les autres à votre suite.', 'ideas' => ['Lancer un ministère, une association ou une entreprise', 'Diriger une équipe dans une période de changement', 'Implanter une œuvre nouvelle'], 'plan' => 'Plan de Vie (Faire)', 'read' => 'Josué 1.6–9'],
        ],
        'I' => [
            'parole' => ['name' => 'Le porte-voix', 'text' => 'Votre aisance relationnelle et votre don de parole vous rendent contagieux : vous savez partager ce qui vous habite et donner envie aux autres de découvrir.', 'ideas' => ['Évangélisation et témoignage', 'Animation, prise de parole, louange', 'Communication et réseaux sociaux d\'une œuvre'], 'plan' => 'Plan de Transmission', 'read' => 'Actes 8.4–8'],
            'service' => ['name' => 'L\'hôte rayonnant', 'text' => 'Chaleur et service : vous faites que chacun se sente attendu. Là où vous êtes, les nouveaux trouvent leur place.', 'ideas' => ['Accueil et intégration des nouveaux', 'Organisation de moments conviviaux', 'Encadrement de la jeunesse'], 'plan' => 'Plan de Transmission', 'read' => 'Romains 12.13'],
            'conduite' => ['name' => 'Le rassembleur', 'text' => 'Vous savez fédérer : votre enthousiasme et votre don de conduite rassemblent les personnes autour d\'une vision commune.', 'ideas' => ['Animer un réseau ou une communauté', 'Conduire un groupe de maison ou une équipe de bénévoles', 'Mobiliser autour d\'un projet collectif'], 'plan' => 'Plan de Vie (Faire)', 'read' => 'Actes 11.22–26'],
        ],
        'S' => [
            'parole' => ['name' => 'Le conseiller patient', 'text' => 'Votre écoute et votre don de parole se rejoignent : vous accompagnez les personnes dans la durée, avec des mots justes et apaisants.', 'ideas' => ['Accompagnement et mentorat', 'Écoute et relation d\'aide', 'Enseignement aux enfants ou aux nouveaux'], 'plan' => 'Plan de Transmission', 'read' => 'Ésaïe 50.4'],
            'service' => ['name' => 'Le serviteur fidèle', 'text' => 'Fidélité et service : vous êtes de ceux qui font tenir les communautés, par une présence régulière et un amour concret.', 'ideas' => ['Visites aux malades et aux personnes isolées', 'Équipes de prière et d\'intercession', 'Entraide pratique dans la durée'], 'plan' => 'Plan de Transmission', 'read' => 'Galates 6.9–10'],
            'conduite' => ['name' => 'Le berger', 'text' => 'Patience et don de conduite : vous êtes fait pour prendre soin d\'un groupe dans la durée, le protéger et le faire grandir.', 'ideas' => ['Responsabilité d\'une cellule ou d\'un groupe', 'Coordination d\'une équipe stable', 'Accompagnement d\'une famille ou d\'un couple'], 'plan' => 'Plan de Vie (Donner)', 'read' => '1 Pierre 5.2–3'],
        ],
        'C' => [
            'parole' => ['name' => 'L\'enseignant', 'text' => 'Rigueur et don de parole : vous êtes fait pour comprendre en profondeur et transmettre avec clarté.', 'ideas' => ['Enseignement biblique et formation', 'Écriture et création de supports', 'Réponses aux questions difficiles sur la foi'], 'plan' => 'Plan de Croissance', 'read' => '2 Timothée 2.15'],
            'service' => ['name' => 'Le garant de l\'excellence', 'text' => 'Précision et service : vous assurez ce qui doit être fiable. Grâce à vous, l\'œuvre repose sur du solide.', 'ideas' => ['Finances, trésorerie, administration', 'Technique, son, informatique', 'Qualité et organisation des processus'], 'plan' => 'Plan de Développement', 'read' => 'Luc 16.10'],
            'conduite' => ['name' => 'L\'architecte de l\'œuvre', 'text' => 'Analyse et don de conduite : vous voyez comment organiser, structurer et guider pour que l\'œuvre dure.', 'ideas' => ['Stratégie et organisation d\'un ministère', 'Gouvernance, conseil, discernement', 'Planification de projets d\'envergure'], 'plan' => 'Plan de Vie (Faire)', 'read' => 'Proverbes 24.3–4'],
        ],
    ];

    /** Lecture des trois axes de convergence. */
    public const AXES = [
        'relation' => ['left' => 'Orienté tâche', 'right' => 'Orienté personnes', 'leftText' => 'vous êtes d\'abord tourné vers les objectifs, les idées et les résultats', 'rightText' => 'vous êtes d\'abord tourné vers les personnes et les relations', 'midText' => 'vous équilibrez attention aux personnes et souci des résultats'],
        'rythme' => ['left' => 'Rythme posé', 'right' => 'Rythme rapide', 'leftText' => 'vous préférez prendre le temps, réfléchir avant d\'agir', 'rightText' => 'vous aimez l\'action, le mouvement et les échanges', 'midText' => 'vous alternez temps d\'action et temps de réflexion'],
        'structure' => ['left' => 'Souple et spontané', 'right' => 'Structuré et organisé', 'leftText' => 'vous préférez garder des options ouvertes et vous adapter', 'rightText' => 'vous aimez planifier, organiser et conclure', 'midText' => 'vous savez organiser sans vous enfermer dans le plan'],
    ];
}
