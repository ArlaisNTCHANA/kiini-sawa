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
            'figures' => [['Esdras', 'Esdras 7.10', 'Il étudie, met en pratique, puis enseigne la loi au peuple.'], ['Aquilas et Priscille', 'Actes 18.26', 'Ils prennent Apollos à part pour lui exposer plus exactement la voie de Dieu.']],
            'read' => 'Romains 12.7 ; 2 Timothée 2.2', 'action' => 'Proposer d\'animer une étude ou une séance de formation dans votre groupe.',
        ],
        'EXHORTATION' => [
            'portrait' => 'Vous avez le don de relever, d\'encourager et de remettre en marche. Vos paroles redonnent courage à ceux qui doutent. Vous savez à la fois consoler et pousser à avancer.',
            'signs' => ['Les gens viennent vers vous quand ils sont découragés', 'Vous trouvez spontanément les mots qui relèvent', 'Vous aimez voir quelqu\'un progresser pas à pas'],
            'church' => 'Accompagnement, mentorat, suivi des nouveaux, messages d\'encouragement, visites.',
            'daily' => 'Soutenir un collègue en difficulté, encourager vos enfants, être un ami qui relève.',
            'dangers' => ['Encourager sans dire la vérité', 'S\'épuiser à porter tout le monde'],
            'figures' => [['Barnabé', 'Actes 4.36 ; 11.23', '« Fils d\'exhortation » : il encourage les croyants d\'Antioche à rester attachés au Seigneur.'], ['Jonathan', '1 Samuel 23.16', 'Il va vers David et fortifie sa confiance en Dieu.']],
            'read' => 'Romains 12.8 ; 1 Thessaloniciens 5.11', 'action' => 'Écrire un message d\'encouragement à trois personnes cette semaine.',
        ],
        'SERVICE' => [
            'portrait' => 'Vous voyez ce qui doit être fait, et vous le faites. Sans chercher la lumière, vous rendez possible le travail des autres. Votre fidélité dans les tâches concrètes fait tenir l\'œuvre.',
            'signs' => ['Vous remarquez ce qui manque avant les autres', 'Vous aimez rendre service concrètement', 'Vous êtes à l\'aise dans les tâches pratiques'],
            'church' => 'Logistique, accueil, installation, technique, entraide pratique.',
            'daily' => 'Aider un voisin, soutenir une équipe par votre fiabilité, être présent dans les moments concrets.',
            'dangers' => ['Ne jamais savoir dire non', 'Juger ceux qui servent moins'],
            'figures' => [['Marthe', 'Luc 10.38–40 ; Jean 12.2', 'Elle accueille et sert : le service concret, avec ses fatigues.'], ['Josué', 'Exode 33.11 ; Nombres 11.28', 'Serviteur de Moïse depuis sa jeunesse, il ne quitte pas la tente.']],
            'read' => 'Romains 12.7 ; 1 Pierre 4.10–11', 'action' => 'Rejoindre une équipe de service pour trois mois.',
        ],
        'LIBERALITE' => [
            'portrait' => 'Vous donnez avec joie, simplicité et intelligence. Vos ressources, votre temps, vos biens deviennent des moyens pour l\'œuvre de Dieu. Vous voyez dans la générosité une forme d\'adoration.',
            'signs' => ['Vous éprouvez de la joie à donner', 'Vous repérez des besoins matériels et y répondez', 'Vous gérez vos ressources pour pouvoir donner davantage'],
            'church' => 'Soutien de projets et de missions, aide aux familles dans le besoin, bourses.',
            'daily' => 'Générosité réfléchie, gestion sage de l\'argent, soutien discret de personnes.',
            'dangers' => ['Donner pour contrôler', 'Chercher la reconnaissance'],
            'figures' => [['Boaz', 'Ruth 2 ; 3.15', 'Il donne largement à Ruth, en préservant sa dignité.'], ['Barnabé', 'Actes 4.37', 'Il vend un champ et en dépose le prix aux pieds des apôtres.']],
            'read' => 'Romains 12.8 ; 2 Corinthiens 9.7', 'action' => 'Choisir une œuvre ou une personne à soutenir régulièrement.',
        ],
        'PRESIDENCE' => [
            'portrait' => 'Vous savez donner une direction, organiser et rassembler. Les autres vous suivent naturellement. Vous voyez le but et vous savez mobiliser chacun pour l\'atteindre.',
            'signs' => ['On vous confie naturellement la conduite de projets', 'Vous savez organiser un groupe vers un but', 'Vous voyez comment répartir les rôles'],
            'church' => 'Responsabilité de ministère, coordination d\'événements, conseil, conduite de projets.',
            'daily' => 'Diriger une équipe, organiser une association, conduire votre foyer avec sagesse.',
            'dangers' => ['Diriger sans servir', 'Confondre autorité et contrôle'],
            'figures' => [['Néhémie', 'Néhémie 2 ; 4 ; 6', 'Il rassemble le peuple, organise la défense et achève la muraille en cinquante-deux jours.'], ['Josué', 'Josué 1 ; 24', 'Il conduit le peuple dans le pays et l\'appelle à choisir qui il servira.']],
            'read' => 'Romains 12.8 ; Marc 10.42–45', 'action' => 'Proposer de coordonner un projet concret dans votre communauté.',
        ],
        'MISERICORDE' => [
            'portrait' => 'Vous ressentez profondément la souffrance des autres et vous allez vers eux. Votre présence douce apaise les blessés, les malades, les exclus. Vous rendez visible la tendresse de Dieu.',
            'signs' => ['Vous êtes touché par la souffrance des autres', 'Vous allez vers ceux que l\'on oublie', 'Les personnes fragiles se sentent bien avec vous'],
            'church' => 'Visites aux malades, aux personnes âgées, aux prisonniers ; aide aux personnes fragiles ; écoute.',
            'daily' => 'Être présent pour un proche malade, soutenir un collègue en deuil, aider les plus vulnérables.',
            'dangers' => ['Porter la souffrance au point de s\'effondrer', 'Excuser ce qui doit être corrigé'],
            'figures' => [['Ruth', 'Ruth 1.16 ; 2.2', 'Elle reste auprès de Naomi et va glaner pour la nourrir.'], ['Joseph, fils de Jacob', 'Genèse 45 ; 50', 'Il pleure, pardonne à ses frères et les console.']],
            'read' => 'Romains 12.8 ; Matthieu 25.35–40', 'action' => 'Rendre visite à une personne malade, isolée ou âgée ce mois-ci.',
        ],
        'PROPHETIE' => [
            'portrait' => 'Vous recevez à certains moments une parole qui édifie, exhorte ou console, et vous la transmettez avec humilité. Vous avez un sens aigu de ce que Dieu veut dire à une personne ou à une communauté.',
            'signs' => ['Une parole juste vous vient parfois pour quelqu\'un', 'Vous êtes sensible à ce qui se joue spirituellement', 'Vos paroles sont confirmées par les responsables'],
            'church' => 'Parole d\'encouragement, intercession, discernement des temps, toujours dans le cadre et sous la conduite des responsables de l\'Église.',
            'daily' => 'Dire une parole vraie et utile au bon moment, avec amour et humilité.',
            'dangers' => ['Parler sans se soumettre à l\'examen des autres (1 Co 14.29)', 'Confondre son opinion et une parole reçue'],
            'figures' => [['Élie', '1 Rois 17 ; 18 ; 21', 'Il porte la parole de Dieu, jusque devant le roi.'], ['Jean-Baptiste', 'Matthieu 3 ; Jean 3.30', 'Il appelle à la repentance et désigne Jésus : « Il faut qu\'il croisse, et que je diminue. »']],
            'read' => 'Romains 12.6 ; 1 Corinthiens 14.3', 'action' => 'Parler avec un responsable de votre Église de la manière d\'exercer ce don dans un cadre sain.',
        ],
        'SAGESSE' => [
            'portrait' => 'Vous savez appliquer la vérité aux situations concrètes. Face à un problème complexe, vous voyez la voie juste. On vient vers vous pour un conseil, et votre parole apporte la paix et la clarté.',
            'signs' => ['On vous demande conseil dans les décisions difficiles', 'Vous voyez la solution juste dans les situations complexes', 'Vos conseils portent du fruit dans la durée'],
            'church' => 'Conseil, médiation, accompagnement des décisions, participation au conseil d\'Église.',
            'daily' => 'Aider un ami à décider, apaiser un conflit familial, conseiller au travail.',
            'dangers' => ['Conseiller sans écouter', 'S\'appuyer sur sa propre sagesse plutôt que sur Dieu'],
            'figures' => [['Salomon', '1 Rois 3.9', 'Il demande un cœur intelligent pour discerner le bien du mal.'], ['Daniel', 'Daniel 1 ; 2', 'Il s\'adresse avec prudence aux puissants et rend gloire à Dieu qui révèle les secrets.']],
            'read' => '1 Corinthiens 12.8 ; Jacques 3.17', 'action' => 'Proposer votre écoute à une personne qui doit prendre une décision.',
        ],
        'CONNAISSANCE' => [
            'portrait' => 'Vous aimez chercher, étudier et mettre en lumière la vérité. Vous creusez les Écritures, vous rassemblez les informations, vous éclairez les autres par votre compréhension profonde.',
            'signs' => ['Vous passez du temps à étudier avec plaisir', 'Vous savez trouver l\'information juste', 'Les autres s\'appuient sur vos recherches'],
            'church' => 'Recherche biblique, préparation de contenus, bibliothèque, soutien aux enseignants.',
            'daily' => 'Expertise professionnelle, recherche, transmission de savoirs utiles.',
            'dangers' => ['L\'orgueil du savoir', 'L\'étude sans mise en pratique'],
            'figures' => [['Apollos', 'Actes 18.24–28', 'Versé dans les Écritures, il démontre que Jésus est le Christ.'], ['Esdras', 'Esdras 7.10', 'Il s\'applique à étudier la loi de l\'Éternel.']],
            'read' => '1 Corinthiens 12.8 ; Colossiens 2.2–3', 'action' => 'Préparer une fiche d\'étude pour votre groupe sur un sujet qui vous passionne.',
        ],
        'FOI' => [
            'portrait' => 'Vous croyez avec assurance que Dieu va agir, même quand tout semble impossible. Votre confiance entraîne les autres. Vous osez lancer ce que d\'autres jugent trop grand.',
            'signs' => ['Vous restez confiant quand d\'autres doutent', 'Vous osez des projets que d\'autres jugent impossibles', 'Votre prière est marquée par l\'assurance'],
            'church' => 'Projets pionniers, prière pour les situations difficiles, lancement d\'œuvres nouvelles.',
            'daily' => 'Garder espoir dans les épreuves, encourager votre famille dans la confiance.',
            'dangers' => ['Confondre foi et imprudence', 'Mépriser les doutes des autres'],
            'figures' => [['Abraham', 'Genèse 12 ; 15.6', 'Il part sur la parole de Dieu, et croit à sa promesse.'], ['Noé', 'Genèse 6 ; Hébreux 11.7', 'Il construit l\'arche, averti de ce qu\'on ne voyait pas encore.']],
            'read' => '1 Corinthiens 12.9 ; Hébreux 11.1', 'action' => 'Porter dans la prière, chaque jour pendant un mois, un projet qui vous semble impossible.',
        ],
        'DISCERNEMENT' => [
            'portrait' => 'Vous percevez ce qui est vrai et ce qui ne l\'est pas, ce qui vient de Dieu et ce qui n\'en vient pas. Vous protégez la communauté en repérant ce qui sonne faux, avec humilité et sagesse.',
            'signs' => ['Vous sentez quand quelque chose sonne faux', 'Votre intuition sur les situations se confirme souvent', 'Vous savez distinguer l\'essentiel de l\'accessoire'],
            'church' => 'Conseil, protection d\'une communauté, évaluation de projets et d\'enseignements.',
            'daily' => 'Évaluer une proposition, protéger votre famille de mauvaises influences.',
            'dangers' => ['Devenir soupçonneux', 'Juger les personnes au lieu d\'éclairer les situations'],
            'figures' => [['Pierre', 'Actes 5 ; 8.20–23', 'Il démasque le mensonge d\'Ananias et le faux zèle de Simon.'], ['Paul', 'Actes 13.8–11 ; 16.16–18', 'Il reconnaît l\'œuvre de l\'esprit mauvais et la ruse d\'Élymas.']],
            'read' => '1 Corinthiens 12.10 ; 1 Jean 4.1', 'action' => 'Proposer votre regard sur un projet de votre communauté, avec humilité.',
        ],
        'HOSPITALITE' => [
            'portrait' => 'Vous savez accueillir. Chez vous, ou là où vous êtes, chacun se sent attendu et à sa place. Vous ouvrez votre porte et votre table, et vous faites de l\'accueil un lieu de rencontre avec Dieu.',
            'signs' => ['Vous aimez recevoir chez vous', 'Les nouveaux venus se sentent bien avec vous', 'Vous pensez aux détails qui mettent à l\'aise'],
            'church' => 'Accueil, repas, groupes de maison, intégration des nouveaux, hébergement.',
            'daily' => 'Ouvrir votre maison, accueillir un collègue nouveau, créer des moments conviviaux.',
            'dangers' => ['Chercher la perfection dans l\'accueil', 'S\'épuiser à recevoir sans se reposer'],
            'figures' => [['Abraham', 'Genèse 18.1–8', 'Il court au-devant de trois visiteurs et leur prépare un repas.'], ['Aquilas et Priscille', 'Actes 18.3 ; Romains 16.5', 'Ils accueillent Paul, puis une Église dans leur maison.']],
            'read' => '1 Pierre 4.9 ; Hébreux 13.2', 'action' => 'Inviter à votre table une personne nouvelle ou isolée ce mois-ci.',
        ],
        'EVANGELISATION' => [
            'portrait' => 'Vous partagez votre foi avec naturel et joie. Vous savez parler de Jésus de manière simple et accessible, et vous voyez des personnes s\'ouvrir à Dieu à votre contact.',
            'signs' => ['Vous parlez facilement de votre foi', 'Vous êtes à l\'aise avec les personnes qui ne croient pas', 'Des personnes se sont ouvertes à Dieu grâce à vous'],
            'church' => 'Témoignage, actions de proximité, parcours de découverte de la foi, accueil des chercheurs.',
            'daily' => 'Vivre et dire votre foi au travail, avec vos voisins, dans vos amitiés.',
            'dangers' => ['Compter les résultats', 'Oublier l\'accompagnement après la découverte'],
            'figures' => [['Philippe', 'Actes 8', 'Il annonce Jésus à l\'eunuque éthiopien, à partir du passage qu\'il lit.'], ['Paul', 'Actes 17 ; 1 Corinthiens 9.22', 'Il se fait tout à tous pour en sauver quelques-uns.']],
            'read' => 'Éphésiens 4.11 ; 1 Pierre 3.15', 'action' => 'Prier pour trois personnes de votre entourage et chercher une occasion de partager votre foi.',
        ],
        'PASTORAT' => [
            'portrait' => 'Vous prenez soin d\'un groupe dans la durée. Vous nourrissez, vous protégez, vous accompagnez chacun. Vous connaissez vos « brebis » et vous vous souciez de leur croissance.',
            'signs' => ['Vous vous souciez de la croissance spirituelle des autres', 'Vous aimez accompagner un groupe dans la durée', 'On vient vers vous pour être guidé'],
            'church' => 'Responsable de cellule ou de groupe de maison, accompagnement d\'un groupe, suivi des membres.',
            'daily' => 'Prendre soin de votre famille, accompagner une équipe, veiller sur vos amis.',
            'dangers' => ['Vouloir tout porter seul', 'Créer de la dépendance plutôt que de l\'autonomie'],
            'figures' => [['Pierre', 'Jean 21.15–17 ; 1 Pierre 5.2', '« Pais mes brebis » : il est appelé à prendre soin du troupeau.'], ['Timothée', '1 Timothée 4 ; Philippiens 2.20', 'Il prend à cœur les Églises, et on lui demande de veiller sur elles.']],
            'read' => 'Éphésiens 4.11 ; Actes 20.28', 'action' => 'Prendre des nouvelles régulières de deux ou trois personnes que vous accompagnez.',
        ],
        'INTERCESSION' => [
            'portrait' => 'Vous priez avec persévérance pour les autres et pour l\'œuvre de Dieu. Vous portez des personnes et des situations devant Dieu, souvent longtemps, souvent en secret, et vous voyez des réponses.',
            'signs' => ['Vous priez longtemps et avec joie pour les autres', 'On vous confie volontiers des sujets de prière', 'Vous voyez des réponses concrètes à vos prières'],
            'church' => 'Équipes de prière, soutien spirituel de projets, de missionnaires et de responsables.',
            'daily' => 'Porter votre famille, vos collègues et votre ville dans la prière.',
            'dangers' => ['Prier sans agir quand l\'action est nécessaire', 'Porter seul des fardeaux trop lourds'],
            'figures' => [['Moïse', 'Exode 32.11–32', 'Il plaide pour le peuple qui l\'a trahi.'], ['Daniel', 'Daniel 9', 'Il prie pour son peuple et s\'inclut dans la faute.']],
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
