<?php

namespace App\Personnalite\Psychometrie;

/**
 * Contenu détaillé du rapport Dons spirituels (complète SpiritualGiftsBank::GIFTS). Contenu original KIINI SAWA.
 * Figures bibliques : exemples où le don est visible dans le récit.
 */
final class GiftProfiles
{
    public const FAMILIES = [
        'parole' => ['name' => 'Dons de parole', 'desc' => 'Transmettre, éclairer, encourager par la parole.', 'color' => '#7C3AED', 'light' => '#EDE9FE', 'dark' => '#4C1D95',
            'gifts' => ['ENSEIGNEMENT', 'EXHORTATION', 'PROPHETIE', 'SAGESSE', 'CONNAISSANCE', 'EVANGELISATION']],
        'service' => ['name' => 'Dons de service', 'desc' => 'Aimer et servir concrètement, souvent dans l\'ombre.', 'color' => '#059669', 'light' => '#D1FAE5', 'dark' => '#064E3B',
            'gifts' => ['SERVICE', 'LIBERALITE', 'MISERICORDE', 'HOSPITALITE', 'INTERCESSION']],
        'conduite' => ['name' => 'Dons de conduite', 'desc' => 'Guider, protéger, ouvrir le chemin.', 'color' => '#B45309', 'light' => '#FEF3C7', 'dark' => '#78350F',
            'gifts' => ['PRESIDENCE', 'PASTORAT', 'FOI', 'DISCERNEMENT']],
    ];

    public const GIFTS = [
        'ENSEIGNEMENT' => [
            'portrait' => 'Vous aimez comprendre la Parole et la rendre claire. Quand vous expliquez, les autres saisissent et grandissent. Vous préparez avec soin, vous cherchez la justesse et vous aimez voir la lumière s\'allumer chez ceux qui apprennent.',
            'signs' => ['On vous demande souvent d\'expliquer un passage ou une notion', 'Vous aimez préparer une étude ou une formation', 'Les autres disent mieux comprendre après vous avoir écouté'],
            'church' => 'Groupes d\'étude biblique, école du dimanche, formation des nouveaux, écriture de supports.',
            'daily' => 'Transmettre un savoir au travail, aider les enfants dans leurs devoirs, former un collègue.',
            'dangers' => ['Privilégier le savoir sur l\'amour (1 Co 8.1)', 'Corriger plus qu\'enseigner'],
            'figures' => [['Esdras', 'Esdras 7.10', 'Il étudie, met en pratique, puis enseigne la loi au peuple.'], ['Priscille et Aquilas', 'Actes 18.26', 'Ils prennent Apollos à part pour lui exposer plus exactement la voie de Dieu.']],
            'read' => 'Romains 12.7 ; 2 Timothée 2.2', 'action' => 'Proposer d\'animer une étude ou une séance de formation dans votre groupe.',
        ],
        'EXHORTATION' => [
            'portrait' => 'Vous avez le don de relever, d\'encourager et de remettre en marche. Vos paroles redonnent courage à ceux qui doutent. Vous savez à la fois consoler et pousser à avancer.',
            'signs' => ['Les gens viennent vers vous quand ils sont découragés', 'Vous trouvez spontanément les mots qui relèvent', 'Vous aimez voir quelqu\'un progresser pas à pas'],
            'church' => 'Accompagnement, mentorat, suivi des nouveaux, messages d\'encouragement, visites.',
            'daily' => 'Soutenir un collègue en difficulté, encourager vos enfants, être un ami qui relève.',
            'dangers' => ['Encourager sans dire la vérité', 'S\'épuiser à porter tout le monde'],
            'figures' => [['Barnabé', 'Actes 11.23', 'Il exhorte les nouveaux croyants d\'Antioche à rester attachés au Seigneur.'], ['Jonathan', '1 Samuel 23.16', 'Il va trouver David dans le désert et fortifie sa confiance en Dieu.']],
            'read' => 'Romains 12.8 ; 1 Thessaloniciens 5.11', 'action' => 'Écrire un message d\'encouragement à trois personnes cette semaine.',
        ],
        'SERVICE' => [
            'portrait' => 'Vous voyez ce qui doit être fait, et vous le faites. Sans chercher la lumière, vous rendez possible le travail des autres. Votre fidélité dans les tâches concrètes fait tenir l\'œuvre.',
            'signs' => ['Vous remarquez ce qui manque avant les autres', 'Vous aimez rendre service concrètement', 'Vous êtes à l\'aise dans les tâches pratiques'],
            'church' => 'Logistique, accueil, installation, technique, entraide pratique.',
            'daily' => 'Aider un voisin, soutenir une équipe par votre fiabilité, être présent dans les moments concrets.',
            'dangers' => ['Ne jamais savoir dire non', 'Juger ceux qui servent moins'],
            'figures' => [['Les sept serviteurs', 'Actes 6.1–6', 'Choisis pour servir aux tables, ils libèrent les apôtres pour la prière et la parole.'], ['Phœbé', 'Romains 16.1–2', 'Servante de l\'Église de Cenchrées, elle a aidé beaucoup de monde, dont Paul.']],
            'read' => 'Romains 12.7 ; 1 Pierre 4.10–11', 'action' => 'Rejoindre une équipe de service pour trois mois.',
        ],
        'LIBERALITE' => [
            'portrait' => 'Vous donnez avec joie, simplicité et intelligence. Vos ressources, votre temps, vos biens deviennent des moyens pour l\'œuvre de Dieu. Vous voyez dans la générosité une forme d\'adoration.',
            'signs' => ['Vous éprouvez de la joie à donner', 'Vous repérez des besoins matériels et y répondez', 'Vous gérez vos ressources pour pouvoir donner davantage'],
            'church' => 'Soutien de projets et de missions, aide aux familles dans le besoin, bourses.',
            'daily' => 'Générosité réfléchie, gestion sage de l\'argent, soutien discret de personnes.',
            'dangers' => ['Donner pour contrôler', 'Chercher la reconnaissance'],
            'figures' => [['Les Églises de Macédoine', '2 Corinthiens 8.1–5', 'Dans une grande pauvreté, elles donnent au-delà de leurs moyens, avec joie.'], ['La veuve aux deux petites pièces', 'Marc 12.41–44', 'Elle donne tout ce qu\'elle a pour vivre ; Jésus la cite en exemple.']],
            'read' => 'Romains 12.8 ; 2 Corinthiens 9.7', 'action' => 'Choisir une œuvre ou une personne à soutenir régulièrement.',
        ],
        'PRESIDENCE' => [
            'portrait' => 'Vous savez donner une direction, organiser et rassembler. Les autres vous suivent naturellement. Vous voyez le but et vous savez mobiliser chacun pour l\'atteindre.',
            'signs' => ['On vous confie naturellement la conduite de projets', 'Vous savez organiser un groupe vers un but', 'Vous voyez comment répartir les rôles'],
            'church' => 'Responsabilité de ministère, coordination d\'événements, conseil, conduite de projets.',
            'daily' => 'Diriger une équipe, organiser une association, conduire votre foyer avec sagesse.',
            'dangers' => ['Diriger sans servir', 'Confondre autorité et contrôle'],
            'figures' => [['Néhémie', 'Néhémie 2–4', 'Il organise la reconstruction des murailles, répartit le travail et tient bon dans l\'opposition.'], ['Moïse', 'Exode 18.13–26', 'Conseillé par Jéthro, il apprend à déléguer et à organiser le peuple.']],
            'read' => 'Romains 12.8 ; Marc 10.42–45', 'action' => 'Proposer de coordonner un projet concret dans votre communauté.',
        ],
        'MISERICORDE' => [
            'portrait' => 'Vous ressentez profondément la souffrance des autres et vous allez vers eux. Votre présence douce apaise les blessés, les malades, les exclus. Vous rendez visible la tendresse de Dieu.',
            'signs' => ['Vous êtes touché par la souffrance des autres', 'Vous allez vers ceux que l\'on oublie', 'Les personnes fragiles se sentent bien avec vous'],
            'church' => 'Visites aux malades, aux personnes âgées, aux prisonniers ; aide aux personnes fragiles ; écoute.',
            'daily' => 'Être présent pour un proche malade, soutenir un collègue en deuil, aider les plus vulnérables.',
            'dangers' => ['Porter la souffrance au point de s\'effondrer', 'Excuser ce qui doit être corrigé'],
            'figures' => [['Le bon Samaritain', 'Luc 10.30–37', 'Il voit, s\'approche, soigne et prend en charge le blessé que d\'autres ont évité.'], ['Tabitha (Dorcas)', 'Actes 9.36–39', 'Elle était riche en bonnes œuvres et prenait soin des veuves.']],
            'read' => 'Romains 12.8 ; Matthieu 25.35–40', 'action' => 'Rendre visite à une personne malade, isolée ou âgée ce mois-ci.',
        ],
        'PROPHETIE' => [
            'portrait' => 'Vous recevez à certains moments une parole qui édifie, exhorte ou console, et vous la transmettez avec humilité. Vous avez un sens aigu de ce que Dieu veut dire à une personne ou à une communauté.',
            'signs' => ['Une parole juste vous vient parfois pour quelqu\'un', 'Vous êtes sensible à ce qui se joue spirituellement', 'Vos paroles sont confirmées par les responsables'],
            'church' => 'Parole d\'encouragement, intercession, discernement des temps, toujours dans le cadre et sous la conduite des responsables de l\'Église.',
            'daily' => 'Dire une parole vraie et utile au bon moment, avec amour et humilité.',
            'dangers' => ['Parler sans se soumettre à l\'examen des autres (1 Co 14.29)', 'Confondre son opinion et une parole reçue'],
            'figures' => [['Agabus', 'Actes 11.28 ; 21.10–11', 'Il annonce une famine, et l\'Église s\'organise pour aider ; sa parole est éprouvée par les faits.'], ['Les filles de Philippe', 'Actes 21.9', 'Quatre filles qui prophétisaient, au sein d\'une famille ouverte à l\'Esprit.']],
            'read' => 'Romains 12.6 ; 1 Corinthiens 14.3', 'action' => 'Parler avec un responsable de votre Église de la manière d\'exercer ce don dans un cadre sain.',
        ],
        'SAGESSE' => [
            'portrait' => 'Vous savez appliquer la vérité aux situations concrètes. Face à un problème complexe, vous voyez la voie juste. On vient vers vous pour un conseil, et votre parole apporte la paix et la clarté.',
            'signs' => ['On vous demande conseil dans les décisions difficiles', 'Vous voyez la solution juste dans les situations complexes', 'Vos conseils portent du fruit dans la durée'],
            'church' => 'Conseil, médiation, accompagnement des décisions, participation au conseil d\'Église.',
            'daily' => 'Aider un ami à décider, apaiser un conflit familial, conseiller au travail.',
            'dangers' => ['Conseiller sans écouter', 'S\'appuyer sur sa propre sagesse plutôt que sur Dieu'],
            'figures' => [['Salomon', '1 Rois 3.16–28', 'Face aux deux mères, il trouve la décision qui révèle la vérité.'], ['Abigaïl', '1 Samuel 25.23–33', 'Par une parole sage, elle empêche David de commettre l\'irréparable.']],
            'read' => '1 Corinthiens 12.8 ; Jacques 3.17', 'action' => 'Proposer votre écoute à une personne qui doit prendre une décision.',
        ],
        'CONNAISSANCE' => [
            'portrait' => 'Vous aimez chercher, étudier et mettre en lumière la vérité. Vous creusez les Écritures, vous rassemblez les informations, vous éclairez les autres par votre compréhension profonde.',
            'signs' => ['Vous passez du temps à étudier avec plaisir', 'Vous savez trouver l\'information juste', 'Les autres s\'appuient sur vos recherches'],
            'church' => 'Recherche biblique, préparation de contenus, bibliothèque, soutien aux enseignants.',
            'daily' => 'Expertise professionnelle, recherche, transmission de savoirs utiles.',
            'dangers' => ['L\'orgueil du savoir', 'L\'étude sans mise en pratique'],
            'figures' => [['Luc', 'Luc 1.1–4', 'Il fait des recherches exactes depuis l\'origine pour écrire un récit ordonné.'], ['Les Béréens', 'Actes 17.11', 'Ils examinent chaque jour les Écritures pour vérifier ce qu\'on leur dit.']],
            'read' => '1 Corinthiens 12.8 ; Colossiens 2.2–3', 'action' => 'Préparer une fiche d\'étude pour votre groupe sur un sujet qui vous passionne.',
        ],
        'FOI' => [
            'portrait' => 'Vous croyez avec assurance que Dieu va agir, même quand tout semble impossible. Votre confiance entraîne les autres. Vous osez lancer ce que d\'autres jugent trop grand.',
            'signs' => ['Vous restez confiant quand d\'autres doutent', 'Vous osez des projets que d\'autres jugent impossibles', 'Votre prière est marquée par l\'assurance'],
            'church' => 'Projets pionniers, prière pour les situations difficiles, lancement d\'œuvres nouvelles.',
            'daily' => 'Garder espoir dans les épreuves, encourager votre famille dans la confiance.',
            'dangers' => ['Confondre foi et imprudence', 'Mépriser les doutes des autres'],
            'figures' => [['Abraham', 'Romains 4.18–21', 'Espérant contre toute espérance, il croit à la promesse de Dieu.'], ['La femme cananéenne', 'Matthieu 15.21–28', 'Elle persévère dans sa demande ; Jésus admire la grandeur de sa foi.']],
            'read' => '1 Corinthiens 12.9 ; Hébreux 11.1', 'action' => 'Porter dans la prière, chaque jour pendant un mois, un projet qui vous semble impossible.',
        ],
        'DISCERNEMENT' => [
            'portrait' => 'Vous percevez ce qui est vrai et ce qui ne l\'est pas, ce qui vient de Dieu et ce qui n\'en vient pas. Vous protégez la communauté en repérant ce qui sonne faux, avec humilité et sagesse.',
            'signs' => ['Vous sentez quand quelque chose sonne faux', 'Votre intuition sur les situations se confirme souvent', 'Vous savez distinguer l\'essentiel de l\'accessoire'],
            'church' => 'Conseil, protection d\'une communauté, évaluation de projets et d\'enseignements.',
            'daily' => 'Évaluer une proposition, protéger votre famille de mauvaises influences.',
            'dangers' => ['Devenir soupçonneux', 'Juger les personnes au lieu d\'éclairer les situations'],
            'figures' => [['Pierre face à Ananias', 'Actes 5.1–11', 'Il perçoit le mensonge caché derrière un don apparemment généreux.'], ['Paul à Philippes', 'Actes 16.16–18', 'Il discerne l\'esprit qui se cache derrière des paroles flatteuses.']],
            'read' => '1 Corinthiens 12.10 ; 1 Jean 4.1', 'action' => 'Proposer votre regard sur un projet de votre communauté, avec humilité.',
        ],
        'HOSPITALITE' => [
            'portrait' => 'Vous savez accueillir. Chez vous, ou là où vous êtes, chacun se sent attendu et à sa place. Vous ouvrez votre porte et votre table, et vous faites de l\'accueil un lieu de rencontre avec Dieu.',
            'signs' => ['Vous aimez recevoir chez vous', 'Les nouveaux venus se sentent bien avec vous', 'Vous pensez aux détails qui mettent à l\'aise'],
            'church' => 'Accueil, repas, groupes de maison, intégration des nouveaux, hébergement.',
            'daily' => 'Ouvrir votre maison, accueillir un collègue nouveau, créer des moments conviviaux.',
            'dangers' => ['Chercher la perfection dans l\'accueil', 'S\'épuiser à recevoir sans se reposer'],
            'figures' => [['Abraham à Mamré', 'Genèse 18.1–8', 'Il court au-devant de trois inconnus et leur prépare un repas : il accueille le Seigneur.'], ['Lydie', 'Actes 16.15', 'Elle insiste pour accueillir Paul et ses compagnons dans sa maison.']],
            'read' => '1 Pierre 4.9 ; Hébreux 13.2', 'action' => 'Inviter à votre table une personne nouvelle ou isolée ce mois-ci.',
        ],
        'EVANGELISATION' => [
            'portrait' => 'Vous partagez votre foi avec naturel et joie. Vous savez parler de Jésus de manière simple et accessible, et vous voyez des personnes s\'ouvrir à Dieu à votre contact.',
            'signs' => ['Vous parlez facilement de votre foi', 'Vous êtes à l\'aise avec les personnes qui ne croient pas', 'Des personnes se sont ouvertes à Dieu grâce à vous'],
            'church' => 'Témoignage, actions de proximité, parcours de découverte de la foi, accueil des chercheurs.',
            'daily' => 'Vivre et dire votre foi au travail, avec vos voisins, dans vos amitiés.',
            'dangers' => ['Compter les résultats', 'Oublier l\'accompagnement après la découverte'],
            'figures' => [['Philippe', 'Actes 8.26–40', 'Il rejoint l\'eunuque éthiopien sur la route et lui annonce Jésus à partir du texte qu\'il lit.'], ['La Samaritaine', 'Jean 4.28–30, 39', 'Elle court dans sa ville dire « Venez voir », et beaucoup croient.']],
            'read' => 'Éphésiens 4.11 ; 1 Pierre 3.15', 'action' => 'Prier pour trois personnes de votre entourage et chercher une occasion de partager votre foi.',
        ],
        'PASTORAT' => [
            'portrait' => 'Vous prenez soin d\'un groupe dans la durée. Vous nourrissez, vous protégez, vous accompagnez chacun. Vous connaissez vos « brebis » et vous vous souciez de leur croissance.',
            'signs' => ['Vous vous souciez de la croissance spirituelle des autres', 'Vous aimez accompagner un groupe dans la durée', 'On vient vers vous pour être guidé'],
            'church' => 'Responsable de cellule ou de groupe de maison, accompagnement d\'un groupe, suivi des membres.',
            'daily' => 'Prendre soin de votre famille, accompagner une équipe, veiller sur vos amis.',
            'dangers' => ['Vouloir tout porter seul', 'Créer de la dépendance plutôt que de l\'autonomie'],
            'figures' => [['Pierre', 'Jean 21.15–17 ; 1 Pierre 5.2–3', 'Restauré par Jésus, il reçoit la mission : « Pais mes brebis. »'], ['Timothée', '1 Timothée 4.12–16', 'Jeune responsable de l\'Église d\'Éphèse, appelé à être un modèle et à veiller sur l\'enseignement.']],
            'read' => 'Éphésiens 4.11 ; Actes 20.28', 'action' => 'Prendre des nouvelles régulières de deux ou trois personnes que vous accompagnez.',
        ],
        'INTERCESSION' => [
            'portrait' => 'Vous priez avec persévérance pour les autres et pour l\'œuvre de Dieu. Vous portez des personnes et des situations devant Dieu, souvent longtemps, souvent en secret, et vous voyez des réponses.',
            'signs' => ['Vous priez longtemps et avec joie pour les autres', 'On vous confie volontiers des sujets de prière', 'Vous voyez des réponses concrètes à vos prières'],
            'church' => 'Équipes de prière, soutien spirituel de projets, de missionnaires et de responsables.',
            'daily' => 'Porter votre famille, vos collègues et votre ville dans la prière.',
            'dangers' => ['Prier sans agir quand l\'action est nécessaire', 'Porter seul des fardeaux trop lourds'],
            'figures' => [['Épaphras', 'Colossiens 4.12', 'Il combat sans cesse dans ses prières pour l\'Église de Colosses.'], ['Moïse', 'Exode 32.11–14', 'Il intercède pour le peuple qui a péché, et Dieu l\'entend.']],
            'read' => '1 Timothée 2.1 ; Jacques 5.16', 'action' => 'Tenir un carnet de prière pour dix personnes pendant un mois.',
        ],
    ];

    public static function familyOf(string $gift): string
    {
        foreach (self::FAMILIES as $key => $f) {
            if (\in_array($gift, $f['gifts'], true)) {
                return $key;
            }
        }

        return 'service';
    }
}
