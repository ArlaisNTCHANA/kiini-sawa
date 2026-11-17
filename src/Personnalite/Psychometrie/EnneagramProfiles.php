<?php

namespace App\Personnalite\Psychometrie;

/**
 * Contenu détaillé du rapport Ennéagramme (complète EnneagramBank::TYPES). Contenu original KIINI SAWA.
 * Directions de croissance et de stress selon les lignes classiques de la figure de l'ennéagramme.
 * Figures bibliques : rapprochements pédagogiques, pas une affirmation sur la personnalité réelle des personnages.
 */
final class EnneagramProfiles
{
    /** Les trois centres : où se loge la motivation, et l'émotion qui y est liée. */
    public const CENTERS = [
        'instinct' => ['name' => 'Centre de l\'instinct', 'types' => [8, 9, 1], 'emotion' => 'la colère', 'color' => '#B45309', 'light' => '#FEF3C7', 'dark' => '#78350F',
            'desc' => 'Vous réagissez d\'abord avec le corps et l\'instinct : la question de l\'autonomie, du contrôle et de la justice vous habite.'],
        'coeur' => ['name' => 'Centre du cœur', 'types' => [2, 3, 4], 'emotion' => 'la honte et l\'image de soi', 'color' => '#DB2777', 'light' => '#FCE7F3', 'dark' => '#831843',
            'desc' => 'Vous réagissez d\'abord avec le cœur : la question de la valeur, de l\'identité et du regard des autres vous habite.'],
        'tete' => ['name' => 'Centre de la tête', 'types' => [5, 6, 7], 'emotion' => 'la peur', 'color' => '#2563EB', 'light' => '#DBEAFE', 'dark' => '#1E3A8A',
            'desc' => 'Vous réagissez d\'abord avec la pensée : la question de la sécurité, de l\'anticipation et des possibles vous habite.'],
    ];

    /** Croissance (quand tout va bien) et stress (sous pression) : type => [croissance, stress]. */
    public const ARROWS = [1 => [7, 4], 2 => [4, 8], 3 => [6, 9], 4 => [1, 2], 5 => [8, 7], 6 => [9, 3], 7 => [5, 1], 8 => [2, 5], 9 => [3, 6]];

    /** Ailes : [type][aile] => [nom, description]. */
    public const WINGS = [
        1 => [9 => ['L\'idéaliste', 'Plus calme et réservé, vous cherchez la justesse avec sérénité.'], 2 => ['Le défenseur', 'Plus chaleureux, vous mettez votre exigence au service des personnes.']],
        2 => [1 => ['Le serviteur', 'Plus réservé et consciencieux, vous aidez avec rigueur et principes.'], 3 => ['L\'hôte', 'Plus rayonnant et sociable, vous aidez avec énergie et charme.']],
        3 => [2 => ['Le charmeur', 'Plus relationnel, vous réussissez en entraînant et en aidant les autres.'], 4 => ['Le professionnel', 'Plus introspectif, vous cherchez une réussite qui ait du sens et du style.']],
        4 => [3 => ['L\'aristocrate', 'Plus ambitieux et expressif, vous voulez que votre singularité se voie et compte.'], 5 => ['Le bohème', 'Plus intérieur et réservé, vous explorez votre profondeur avec discrétion.']],
        5 => [4 => ['L\'iconoclaste', 'Plus créatif et sensible, vous unissez savoir et imagination.'], 6 => ['Le résolveur', 'Plus méthodique et loyal, vous mettez votre savoir au service d\'un groupe.']],
        6 => [5 => ['Le défenseur', 'Plus réfléchi et indépendant, vous cherchez la sécurité dans la compréhension.'], 7 => ['Le compagnon', 'Plus sociable et enjoué, vous cherchez la sécurité dans les relations.']],
        7 => [6 => ['L\'animateur', 'Plus loyal et responsable, vous mettez votre enthousiasme au service des autres.'], 8 => ['Le réaliste', 'Plus affirmé et entreprenant, vous transformez les possibles en réalisations.']],
        8 => [7 => ['L\'indépendant', 'Plus extraverti et audacieux, vous avancez avec énergie et goût du risque.'], 9 => ['L\'ours', 'Plus calme et protecteur, votre force est stable et rassurante.']],
        9 => [8 => ['L\'arbitre', 'Plus affirmé, vous savez tenir bon quand il le faut.'], 1 => ['Le rêveur', 'Plus idéaliste et ordonné, vous cherchez une paix juste.']],
    ];

    public const TYPES = [
        1 => [
            'center' => 'instinct', 'color' => '#B45309', 'tagline' => 'Faire ce qui est juste, et bien le faire.',
            'keywords' => ['Intégrité', 'Exigence', 'Responsabilité'],
            'portrait' => 'Vous avez un sens aigu du bien et du mal, de ce qui devrait être. Vous voulez améliorer les choses, à commencer par vous-même. Fiable, droit, consciencieux, vous élevez le niveau partout où vous passez. Mais une voix intérieure exigeante vous rappelle sans cesse ce qui n\'est pas encore parfait.',
            'strengths' => ['Intègre et digne de confiance', 'Sens des responsabilités', 'Souci du travail bien fait', 'Défense de ce qui est juste'],
            'work' => 'Vous excellez là où la qualité, l\'éthique et la rigueur comptent. Vous gagnez à accepter que « bien » suffise souvent.',
            'relations' => 'Vous êtes fidèle et engagé. Vos proches ont besoin que votre exigence s\'accompagne de douceur et de félicitations.',
            'church' => 'Enseignement, organisation, intégrité de l\'œuvre. Votre défi : vivre de la grâce que vous annoncez.',
            'signs' => 'Vous devenez critique, rigide, irritable ; vous corrigez tout et tout le monde.',
            'growthText' => 'Vous vous détendez, vous retrouvez la joie et la spontanéité : le jeu, la légèreté, la confiance.',
            'stressText' => 'Vous vous sentez incompris, vous vous repliez sur vos émotions et une forme de mélancolie.',
            'grow' => ['Remarquer chaque jour une chose réussie, chez vous et chez un autre', 'Distinguer l\'essentiel du détail avant de corriger', 'Prévoir des temps de détente sans objectif'],
            'figures' => [
                ['name' => 'Paul', 'ref' => 'Philippiens 3.4–9', 'text' => '« Irréprochable » selon la loi, zélé jusqu\'à l\'excès ; il découvre que la vraie justice vient de la grâce, non de l\'effort.'],
                ['name' => 'Jonas', 'ref' => 'Jonas 4', 'text' => 'Il s\'irrite que Dieu fasse grâce à Ninive : le sens de la justice qui doit apprendre la miséricorde.'],
            ],
            'verse' => ['Ma grâce te suffit, car ma puissance s\'accomplit dans la faiblesse.', '2 Corinthiens 12.9'],
            'meditation' => 'Où ai-je du mal à recevoir la grâce, pour moi-même ou pour les autres ?',
            'actions' => ['Faire volontairement une chose « assez bien » et la laisser ainsi', 'Exprimer trois encouragements avant toute remarque cette semaine', 'Programmer un vrai moment de loisir'],
        ],
        2 => [
            'center' => 'coeur', 'color' => '#DB2777', 'tagline' => 'Aimer, et se rendre utile.',
            'keywords' => ['Générosité', 'Attention', 'Chaleur'],
            'portrait' => 'Vous percevez les besoins des autres avant qu\'ils ne les expriment. Vous donnez votre temps, votre énergie, votre cœur. Les gens se sentent aimés à vos côtés. Mais à force de donner, vous risquez d\'oublier vos propres besoins, et d\'attendre en secret qu\'on vous le rende.',
            'strengths' => ['Attentif aux besoins des autres', 'Généreux et serviable', 'Crée des liens profonds', 'Encourage et soutient'],
            'work' => 'Vous excellez dans l\'accompagnement, le soin, l\'accueil, le soutien d\'une équipe. Vous gagnez à poser des limites et à dire vos propres besoins.',
            'relations' => 'Vous êtes un ami et un conjoint attentionné. Laissez aussi les autres prendre soin de vous.',
            'church' => 'Accueil, entraide, visites, accompagnement. Votre défi : servir par amour libre, non pour être aimé.',
            'signs' => 'Vous vous sentez peu reconnu, vous en faites encore plus, puis vous devenez amer ou possessif.',
            'growthText' => 'Vous vous tournez vers vous-même avec honnêteté : vous reconnaissez vos émotions et vos besoins.',
            'stressText' => 'Vous devenez exigeant, direct, voire dur, pour obtenir la reconnaissance qui manque.',
            'grow' => ['Demander chaque semaine : « De quoi ai-je besoin, moi ? »', 'Accepter une aide sans la rendre aussitôt', 'Dire non à une demande sans vous justifier longuement'],
            'figures' => [
                ['name' => 'Marthe', 'ref' => 'Luc 10.38–42', 'text' => 'Elle accueille et sert Jésus avec ardeur, mais s\'agite et s\'irrite ; Jésus l\'invite d\'abord à recevoir.'],
                ['name' => 'Tabitha (Dorcas)', 'ref' => 'Actes 9.36–39', 'text' => 'Ses bonnes œuvres ont touché toute une communauté : l\'amour concret qui laisse une trace.'],
            ],
            'verse' => ['Nous l\'aimons, parce qu\'il nous a aimés le premier.', '1 Jean 4.19'],
            'meditation' => 'Est-ce que je sers par liberté, ou pour être aimé en retour ?',
            'actions' => ['Prendre un temps pour vous, sans le justifier', 'Exprimer un besoin personnel à un proche', 'Refuser une demande de trop'],
        ],
        3 => [
            'center' => 'coeur', 'color' => '#DB2777', 'tagline' => 'Réussir, et faire réussir.',
            'keywords' => ['Efficacité', 'Ambition', 'Rayonnement'],
            'portrait' => 'Vous êtes orienté vers les objectifs et la réussite. Énergique, efficace, adaptable, vous savez ce qu\'il faut faire pour atteindre un but et vous y parvenez. Vous inspirez les autres. Mais vous risquez de confondre ce que vous faites avec ce que vous êtes, et de porter un masque pour être apprécié.',
            'strengths' => ['Efficace et orienté résultats', 'Adaptable et énergique', 'Motive et inspire les autres', 'Sait se fixer et atteindre des objectifs'],
            'work' => 'Vous excellez dans les projets à objectifs, la direction, la représentation, l\'entrepreneuriat. Vous gagnez à ralentir et à rester vrai.',
            'relations' => 'Vous êtes dynamique et valorisant. Vos proches ont besoin de vous connaître tel que vous êtes, pas seulement dans vos réussites.',
            'church' => 'Projets, communication, leadership. Votre défi : servir sans chercher à briller.',
            'signs' => 'Vous travaillez sans fin, vous soignez l\'image, vous ignorez vos émotions.',
            'growthText' => 'Vous devenez loyal et coopératif, vous mettez votre énergie au service d\'un projet commun.',
            'stressText' => 'Vous vous désengagez, vous tombez dans l\'inertie et la démotivation.',
            'grow' => ['Partager un échec ou une faiblesse avec une personne de confiance', 'Prendre un temps de repos sans productivité', 'Demander « qu\'est-ce que je ressens ? » avant « que dois-je faire ? »'],
            'figures' => [
                ['name' => 'Jacob', 'ref' => 'Genèse 27 ; 32.24–30', 'text' => 'Ambitieux et habile, il veut la bénédiction à tout prix ; après avoir lutté avec Dieu, il reçoit un nom nouveau.'],
                ['name' => 'Saül', 'ref' => '1 Samuel 15.24–30', 'text' => 'Il préfère être honoré devant le peuple plutôt qu\'obéir : l\'image qui prend la place de la vérité.'],
            ],
            'verse' => ['C\'est par la grâce que vous êtes sauvés, par le moyen de la foi. Et cela ne vient pas de vous, c\'est le don de Dieu.', 'Éphésiens 2.8'],
            'meditation' => 'Qui suis-je quand je ne réussis pas ?',
            'actions' => ['Passer une soirée sans travail ni écran professionnel', 'Raconter à un proche une difficulté que vous traversez', 'Célébrer la réussite d\'un autre'],
        ],
        4 => [
            'center' => 'coeur', 'color' => '#DB2777', 'tagline' => 'Être vrai, et unique.',
            'keywords' => ['Authenticité', 'Profondeur', 'Créativité'],
            'portrait' => 'Vous ressentez la vie intensément. Vous cherchez l\'authenticité, la beauté, le sens. Vous êtes sensible à ce qui manque et à la souffrance des autres. Votre profondeur et votre créativité touchent les cœurs. Mais vous pouvez vous sentir différent, incompris, et vous comparer à ce que les autres semblent avoir.',
            'strengths' => ['Profondeur émotionnelle', 'Créativité et sens esthétique', 'Empathie face à la souffrance', 'Authenticité'],
            'work' => 'Vous excellez dans la création, l\'accompagnement, tout ce qui demande sens et sensibilité. Vous gagnez à la régularité, même sans inspiration.',
            'relations' => 'Vous offrez une présence vraie et profonde. Attention aux montagnes russes émotionnelles et à l\'idéalisation.',
            'church' => 'Louange, art, accompagnement des personnes blessées. Votre défi : trouver Dieu aussi dans l\'ordinaire.',
            'signs' => 'Vous vous repliez, vous ruminez, vous vous sentez incompris et à part.',
            'growthText' => 'Vous devenez discipliné et objectif : vous agissez selon des principes, pas seulement selon vos humeurs.',
            'stressText' => 'Vous cherchez à vous rendre indispensable, vous devenez dépendant de l\'attention des autres.',
            'grow' => ['Tenir un rituel simple chaque jour, même sans envie', 'Noter trois choses ordinaires pour lesquelles vous êtes reconnaissant', 'Remplacer la comparaison par la gratitude'],
            'figures' => [
                ['name' => 'David, le psalmiste', 'ref' => 'Psaumes 42 ; 51', 'text' => 'Il met des mots sur sa détresse et sa joie avec une sincérité unique : l\'émotion offerte à Dieu devient prière.'],
                ['name' => 'Jérémie', 'ref' => 'Jérémie 20.7–18', 'text' => 'Le prophète des larmes, déchiré et sincère, qui continue pourtant d\'annoncer la parole reçue.'],
            ],
            'verse' => ['Je te loue de ce que je suis une créature si merveilleuse.', 'Psaume 139.14'],
            'meditation' => 'Qu\'ai-je reçu aujourd\'hui que je n\'ai pas remarqué ?',
            'actions' => ['Tenir un carnet de gratitude pendant 7 jours', 'Terminer une création commencée', 'Rendre service sans attendre de reconnaissance'],
        ],
        5 => [
            'center' => 'tete', 'color' => '#2563EB', 'tagline' => 'Comprendre avant d\'agir.',
            'keywords' => ['Lucidité', 'Savoir', 'Indépendance'],
            'portrait' => 'Vous avez besoin de comprendre. Observateur, curieux, réfléchi, vous approfondissez les sujets qui vous passionnent et vous gardez la tête froide. Votre lucidité éclaire les autres. Mais vous pouvez vous retirer dans votre tête, économiser votre énergie et vos émotions, et rester spectateur.',
            'strengths' => ['Analyse profonde', 'Objectivité', 'Autonomie', 'Expertise dans ses domaines'],
            'work' => 'Vous excellez dans la recherche, l\'expertise, l\'analyse, la stratégie. Vous gagnez à partager plus tôt ce que vous savez.',
            'relations' => 'Vous êtes fidèle et respectueux de l\'espace des autres. Vos proches ont besoin de votre présence et de vos émotions, pas seulement de vos idées.',
            'church' => 'Enseignement, étude, conseil. Votre défi : passer de la connaissance à l\'engagement concret.',
            'signs' => 'Vous vous isolez, vous vous coupez de vos émotions, vous accumulez sans agir.',
            'growthText' => 'Vous passez à l\'action avec assurance, vous vous engagez et vous affirmez.',
            'stressText' => 'Vous vous dispersez, vous fuyez dans l\'agitation et les distractions.',
            'grow' => ['Agir sur une idée avant d\'avoir tout compris', 'Partager une émotion, pas seulement une analyse', 'Donner de votre temps à une cause concrète'],
            'figures' => [
                ['name' => 'Nicodème', 'ref' => 'Jean 3.1–21 ; 19.39', 'text' => 'Il vient de nuit, avec ses questions ; plus tard, il ose se montrer en apportant les aromates pour le corps de Jésus.'],
                ['name' => 'L\'Ecclésiaste', 'ref' => 'Ecclésiaste 1.13–18 ; 12.13', 'text' => 'Il a appliqué son cœur à tout comprendre ; il conclut que la crainte de Dieu donne sens au savoir.'],
            ],
            'verse' => ['La connaissance enfle, mais la charité édifie.', '1 Corinthiens 8.1'],
            'meditation' => 'Qu\'est-ce que je sais déjà, et que je n\'ose pas encore vivre ?',
            'actions' => ['Passer à l\'action sur un projet que vous étudiez depuis longtemps', 'Proposer votre aide concrète à quelqu\'un', 'Dire à un proche ce que vous ressentez'],
        ],
        6 => [
            'center' => 'tete', 'color' => '#2563EB', 'tagline' => 'Fidèle, prévoyant, solidaire.',
            'keywords' => ['Loyauté', 'Prudence', 'Engagement'],
            'portrait' => 'Vous êtes loyal et responsable. Vous anticipez les risques, vous protégez votre groupe, vous tenez vos engagements. On peut compter sur vous. Mais le doute et l\'inquiétude vous accompagnent souvent : vous cherchez la sécurité, auprès des autres ou dans les règles, et vous hésitez à vous faire confiance.',
            'strengths' => ['Loyal et fiable', 'Anticipe les risques', 'Sens du collectif', 'Courageux face au danger réel'],
            'work' => 'Vous excellez dans la gestion des risques, la coordination, le soutien d\'une équipe. Vous gagnez à faire confiance à votre jugement.',
            'relations' => 'Vous êtes un allié fidèle. Partagez vos peurs plutôt que de tester la loyauté des autres.',
            'church' => 'Service fidèle, intercession, protection d\'une communauté. Votre défi : passer de la crainte à la confiance.',
            'signs' => 'Vous doutez de tout, vous imaginez le pire, vous devenez méfiant ou réactif.',
            'growthText' => 'Vous trouvez la paix intérieure, vous vous détendez et faites confiance.',
            'stressText' => 'Vous vous jetez dans l\'activité et la performance pour calmer l\'angoisse.',
            'grow' => ['Noter vos peurs et ce qui est réellement probable', 'Prendre une décision sans demander l\'avis de tous', 'Confier une inquiétude dans la prière chaque soir'],
            'figures' => [
                ['name' => 'Gédéon', 'ref' => 'Juges 6.11–40', 'text' => 'Caché pour battre son blé, il demande des signes avant d\'agir ; Dieu l\'appelle « vaillant héros » et le conduit à la victoire.'],
                ['name' => 'Timothée', 'ref' => '2 Timothée 1.5–7', 'text' => 'Fidèle et timide, encouragé par Paul à ranimer le don reçu : l\'esprit de force remplace la crainte.'],
            ],
            'verse' => ['Ne crains rien, car je suis avec toi ; ne promène pas des regards inquiets, car je suis ton Dieu.', 'Ésaïe 41.10'],
            'meditation' => 'Sur quoi est-ce que je fonde ma sécurité aujourd\'hui ?',
            'actions' => ['Prendre une décision attendue sans consulter', 'Écrire vos trois plus grandes peurs et un verset en face de chacune', 'Faire confiance à une personne sur une tâche importante'],
        ],
        7 => [
            'center' => 'tete', 'color' => '#2563EB', 'tagline' => 'La joie des possibles.',
            'keywords' => ['Enthousiasme', 'Optimisme', 'Créativité'],
            'portrait' => 'Vous aimez la vie et ses possibles. Curieux, optimiste, plein d\'idées, vous apportez la joie et l\'élan partout où vous allez. Vous voyez des solutions là où d\'autres voient des obstacles. Mais vous pouvez fuir l\'inconfort, multiplier les projets et éviter ce qui fait mal.',
            'strengths' => ['Optimisme contagieux', 'Créativité et idées', 'Polyvalence', 'Capacité à rebondir'],
            'work' => 'Vous excellez dans l\'innovation, le lancement, l\'animation, la communication. Vous gagnez à aller au bout des choses.',
            'relations' => 'Vous êtes un compagnon joyeux et stimulant. Vos proches ont aussi besoin de votre présence dans les moments difficiles.',
            'church' => 'Animation, évangélisation, projets nouveaux. Votre défi : la persévérance et la profondeur.',
            'signs' => 'Vous vous dispersez, vous fuyez dans de nouveaux projets, vous évitez les sujets lourds.',
            'growthText' => 'Vous devenez concentré et profond, vous savourez une chose à la fois.',
            'stressText' => 'Vous devenez critique, rigide et impatient envers les autres.',
            'grow' => ['Terminer un projet avant d\'en commencer un autre', 'Rester présent dans une conversation difficile', 'Pratiquer un temps de silence chaque jour'],
            'figures' => [
                ['name' => 'Pierre', 'ref' => 'Matthieu 14.28–31 ; 26.69–75', 'text' => 'Enthousiaste et spontané, il marche sur l\'eau et fuit la souffrance de la croix ; restauré, il deviendra un pilier.'],
                ['name' => 'Salomon', 'ref' => 'Ecclésiaste 2.1–11', 'text' => 'Il a tout essayé, tout goûté, et découvert que la joie ne se trouve pas dans l\'accumulation des expériences.'],
            ],
            'verse' => ['Il y a d\'abondantes joies devant ta face, des délices éternelles à ta droite.', 'Psaume 16.11'],
            'meditation' => 'Quelle difficulté est-ce que j\'évite au lieu de la traverser ?',
            'actions' => ['Choisir un projet unique à terminer ce mois-ci', 'Passer une journée sans planifier de nouvelle activité', 'Avoir la conversation que vous repoussez'],
        ],
        8 => [
            'center' => 'instinct', 'color' => '#B45309', 'tagline' => 'La force au service des autres.',
            'keywords' => ['Courage', 'Franchise', 'Protection'],
            'portrait' => 'Vous êtes fort, direct, déterminé. Vous prenez les choses en main, vous défendez les plus faibles, vous ne reculez pas devant la difficulté. Votre présence rassure et entraîne. Mais vous pouvez écraser par votre intensité et refuser de montrer votre vulnérabilité.',
            'strengths' => ['Courage et détermination', 'Protège les plus faibles', 'Franchise', 'Capacité à décider et à diriger'],
            'work' => 'Vous excellez dans la direction, le redressement, la défense de causes. Vous gagnez à écouter et à doser votre intensité.',
            'relations' => 'Vous êtes protecteur et loyal. Vos proches ont besoin de voir aussi votre tendresse.',
            'church' => 'Leadership, justice, défense des opprimés. Votre défi : mettre votre force sous l\'autorité de l\'amour.',
            'signs' => 'Vous devenez dominant, colérique, vous refusez toute faiblesse.',
            'growthText' => 'Vous ouvrez votre cœur, vous prenez soin des autres avec tendresse.',
            'stressText' => 'Vous vous retirez, vous vous isolez pour reprendre le contrôle.',
            'grow' => ['Demander l\'avis des autres avant d\'imposer le vôtre', 'Montrer une vulnérabilité à une personne de confiance', 'Baisser d\'un ton dans un désaccord'],
            'figures' => [
                ['name' => 'Samson', 'ref' => 'Juges 15–16', 'text' => 'Une force hors du commun, mal maîtrisée ; dans sa faiblesse finale, il se tourne enfin vers Dieu (Juges 16.28).'],
                ['name' => 'Jacques et Jean, « fils du tonnerre »', 'ref' => 'Marc 3.17 ; Luc 9.54–55', 'text' => 'Prompts à faire tomber le feu du ciel ; Jean deviendra pourtant l\'apôtre de l\'amour.'],
            ],
            'verse' => ['Car, quand je suis faible, c\'est alors que je suis fort.', '2 Corinthiens 12.10'],
            'meditation' => 'Où ma force protège-t-elle, et où écrase-t-elle ?',
            'actions' => ['Laisser une autre personne décider sur un sujet', 'Exprimer de la tendresse à un proche', 'Écouter jusqu\'au bout avant de répondre'],
        ],
        9 => [
            'center' => 'instinct', 'color' => '#B45309', 'tagline' => 'La paix qui rassemble.',
            'keywords' => ['Paix', 'Écoute', 'Harmonie'],
            'portrait' => 'Vous recherchez la paix, en vous et autour de vous. Calme, accueillant, à l\'écoute, vous voyez tous les points de vue et vous savez réunir. Votre présence apaise. Mais vous pouvez vous effacer, remettre à plus tard et éviter les conflits au point d\'oublier ce que vous voulez vraiment.',
            'strengths' => ['Écoute et accueil', 'Apaise les tensions', 'Voit tous les points de vue', 'Stable et patient'],
            'work' => 'Vous excellez dans la médiation, l\'accompagnement, la coordination. Vous gagnez à exprimer vos priorités et vos désaccords.',
            'relations' => 'Vous êtes un compagnon paisible et fidèle. Vos proches ont besoin d\'entendre ce que vous voulez vraiment.',
            'church' => 'Médiation, accueil, accompagnement. Votre défi : prendre votre place et oser votre voix.',
            'signs' => 'Vous vous engourdissez, vous procrastinez, vous dites oui pour éviter le conflit.',
            'growthText' => 'Vous devenez énergique et décidé, vous agissez pour vos propres objectifs.',
            'stressText' => 'Vous devenez anxieux et méfiant, vous doutez de tout.',
            'grow' => ['Écrire chaque matin votre priorité du jour', 'Exprimer une préférence quand on vous demande votre avis', 'Aborder un désaccord plutôt que l\'éviter'],
            'figures' => [
                ['name' => 'Abraham', 'ref' => 'Genèse 13.8–9', 'text' => '« Qu\'il n\'y ait point […] de dispute entre moi et toi » : il laisse Lot choisir le premier pour préserver la paix.'],
                ['name' => 'Isaac', 'ref' => 'Genèse 26.17–22', 'text' => 'Il creuse un puits, puis un autre, plutôt que de se quereller ; Dieu lui donne finalement l\'espace.'],
            ],
            'verse' => ['Heureux ceux qui procurent la paix, car ils seront appelés fils de Dieu !', 'Matthieu 5.9'],
            'meditation' => 'Qu\'est-ce que je veux vraiment, et que je n\'ose pas dire ?',
            'actions' => ['Fixer un objectif personnel et le partager', 'Dire non à une chose qui vous éloigne de vos priorités', 'Exprimer un désaccord avec douceur'],
        ],
    ];

    public static function centerOf(int $type): string
    {
        return self::TYPES[$type]['center'];
    }
}
