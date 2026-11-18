<?php

namespace App\Personnalite\Psychometrie;

/**
 * Bibliothèque de fiches de personnages bibliques (pilote : Élie, Joseph, Timothée).
 *
 * Cadre doctrinal (fondateur, 18/11/2026) : le Christ seul et son œuvre ; l'Ancien Testament pointe vers le Christ ; la grâce, jamais le
 * légalisme. Version biblique : Louis Segond 1910 (domaine public).
 *
 * Chaque fiche sépare strictement :
 *  - « Dans le texte » : ce que le récit biblique dit (références et citations vérifiables) ;
 *  - « Notre lecture » : le tempérament que ce récit suggère, dit clairement comme une lecture de KIINI SAWA (les auteurs bibliques ne
 *    pensaient pas en DISC) ;
 *  - « Le Christ au centre » : comment ce personnage pointe vers le Christ et vers la grâce.
 *
 * Toutes les citations sont celles de la LSG ; `tests/Personnalite/BiblicalFiguresTest.php` les vérifie contre le texte quand il est
 * présent (var/bible/lsg.json, voir bin/outils/bible.py). Contenu marqué « à relire par le fondateur » tant que `reviewed` est faux.
 */
final class BiblicalFigures
{
    public const FIGURES = [
        'ELIE' => [
            'name' => 'Élie', 'aliases' => ['Élie'], 'reviewed' => false,
            'title' => 'Le prophète qui s\'effondre après la victoire',
            'refs' => '1 Rois 17–19 ; 2 Rois 2',
            'why' => [
                'D' => 'Comme lui, vous décidez vite, vous affrontez ce que d\'autres évitent et vous aimez que les choses avancent. Son récit dit aussi ce qui guette ce tempérament : l\'épuisement et la solitude.',
                'default' => 'Son audace et sa vigilance face à l\'épuisement éclairent particulièrement les tempéraments d\'action.',
            ],
            'who' => 'Élie est un prophète du royaume du Nord, au temps du roi Achab (IXe siècle av. J.-C.). Il apparaît sans présentation, sinon « Élie, le Thischbite, l\'un des habitants de Galaad » (1 Rois 17:1). Son message tient en une phrase : l\'Éternel est vivant, et Baal n\'est rien.',
            'episodes' => [
                ['title' => 'La parole au roi', 'ref' => '1 Rois 17:1', 'text' => 'Il annonce à Achab qu\'il n\'y aura ni rosée ni pluie « sinon à ma parole ». D\'emblée, un homme qui parle sans détour au plus puissant du pays.'],
                ['title' => 'Nourri par Dieu, avant d\'agir', 'ref' => '1 Rois 17:2-16', 'text' => 'Dieu l\'envoie d\'abord au torrent de Kerith, où des corbeaux lui apportent du pain et de la viande, puis chez une veuve de Sarepta dont la farine et l\'huile ne manquent pas. Avant d\'agir, le prophète dépend.'],
                ['title' => 'Le Carmel', 'ref' => '1 Rois 18:20-40', 'text' => 'Il somme le peuple de choisir entre l\'Éternel et Baal, puis prie brièvement. Le feu tombe et le peuple s\'écrie : « C\'est l\'Éternel qui est Dieu ! » Il court ensuite devant le char d\'Achab jusqu\'à Jizreel (18:46).',
                    'quote' => ['ref' => '1 Rois 18:21', 'text' => 'Jusqu\'à quand clocherez-vous des deux côtés? Si l\'Eternel est Dieu, allez après lui; si c\'est Baal, allez après lui!']],
                ['title' => 'La fuite', 'ref' => '1 Rois 19:1-4', 'text' => 'Une menace de Jézabel suffit : il s\'enfuit, laisse son serviteur, s\'assoit sous un genêt et demande la mort. Le vainqueur du Carmel s\'effondre.',
                    'quote' => ['ref' => '1 Rois 19:4', 'text' => 'C\'est assez! Maintenant, Eternel, prends mon âme, car je ne suis pas meilleur que mes pères.']],
                ['title' => 'Lève-toi, mange', 'ref' => '1 Rois 19:5-8', 'text' => 'Dieu ne commence pas par une correction : un ange le touche, lui donne à manger et à boire, le laisse dormir, puis revient une seconde fois. Rien n\'est reproché.',
                    'quote' => ['ref' => '1 Rois 19:7', 'text' => 'Lève-toi, mange, car le chemin est trop long pour toi.']],
                ['title' => 'Le murmure et la mission', 'ref' => '1 Rois 19:9-18', 'text' => 'À l\'Horeb, Dieu n\'est ni dans le vent, ni dans le tremblement de terre, ni dans le feu, mais dans « un murmure doux et léger » (19:12). Élie se plaint : « je suis resté, moi seul » (19:10). Dieu lui répond qu\'il a gardé sept mille hommes (19:18), lui donne une tâche et un compagnon, Élisée (19:16, 19).'],
                ['title' => 'Enlevé au ciel', 'ref' => '2 Rois 2:11', 'text' => 'Il ne meurt pas : « Élie monta au ciel dans un tourbillon ». Il laisse son manteau et son ministère à Élisée.'],
            ],
            'reading' => [
                'temperament' => 'Le récit montre un homme de décision et d\'action : il somme tout un peuple de choisir, prie en une phrase, court devant un char. Nous lisons dans ce tempérament la dimension Dominante du DISC : audace, autorité, goût du résultat, et tendance à porter seul. Les auteurs bibliques ne pensaient pas en DISC : c\'est une lecture, pas une affirmation du texte.',
                'traits' => [
                    ['Il dit les choses sans détour', '1 Rois 17:1 ; 18:21'],
                    ['Il agit avec énergie', '1 Rois 18:46'],
                    ['Il croit que tout repose sur lui', '1 Rois 19:10'],
                ],
            ],
            'weakness' => 'Après le Carmel, une menace suffit à le faire fuir. Il se compare (« je ne suis pas meilleur que mes pères », 19:4), puis ramène tout à lui : « je suis resté, moi seul » (19:10). Pour un tempérament d\'action, c\'est la vigilance : une force qui ne se repose pas finit par s\'effondrer, et l\'isolement s\'installe.',
            'grace' => 'Dieu ne le reprend pas d\'abord : il le laisse dormir et le nourrit, deux fois (19:5-7). La restauration précède la mission. Il corrige ensuite son isolement : sept mille hommes n\'ont pas fléchi le genou (19:18), et Élisée sera à ses côtés. Sa victoire ne lui a pas donné le droit de se reposer ; sa fatigue ne lui a pas fait perdre l\'amour de Dieu.',
            'christ' => 'Élie ne reste pas le héros : il s\'efface. À la transfiguration, il paraît avec Moïse, puis la voix du Père dit : « Celui-ci est mon Fils bien-aimé... écoutez-le ! » (Matthieu 17:3-5). Jean-Baptiste viendra « avec l\'esprit et la puissance d\'Élie » pour préparer le Seigneur (Luc 1:17). Le feu, la puissance et la parole d\'Élie montrent tous la même chose : c\'est le Fils qu\'il faut écouter. Votre force n\'est pas ce qui vous sauve ; c\'est la grâce de celui qui vous nourrit quand vous êtes épuisé.',
            'quotes' => [
                ['ref' => 'Jacques 5:17', 'text' => 'Elie était un homme de la même nature que nous'],
                ['ref' => 'Matthieu 17:5', 'text' => 'Celui-ci est mon Fils bien-aimé, en qui j\'ai mis toute mon affection: écoutez-le!'],
            ],
            'question' => 'Qui, autour de moi, peut me dire « lève-toi, mange » avant que je ne m\'effondre ?',
            'step' => 'Prévoir un temps de repos réel avant le prochain défi, et le dire à une personne qui peut vous le rappeler.',
        ],

        'JOSEPH' => [
            'name' => 'Joseph de Nazareth', 'aliases' => ['Joseph de Nazareth', 'Joseph'], 'reviewed' => false,
            'title' => 'L\'homme qui protège sans dire un mot',
            'refs' => 'Matthieu 1–2 ; Luc 2',
            'why' => [
                'S' => 'Comme lui, vous êtes fidèle dans la durée, discret, et vous protégez ceux qui vous sont confiés. Son récit montre aussi ce que Dieu fait de la crainte : il vient à la rencontre d\'un homme prudent.',
                'C' => 'Comme lui, vous réfléchissez avant d\'agir et vous voulez bien faire. Son récit montre que Dieu rejoint la personne prudente dans son hésitation, pas à côté d\'elle.',
                'default' => 'Sa discrétion et sa fidélité éclairent les tempéraments posés, qui agissent sans bruit.',
            ],
            'who' => 'Joseph est le fiancé puis l\'époux de Marie. Il est « fils de David » (Matthieu 1:20) et on connaît Jésus comme « le fils du charpentier » (Matthieu 13:55). Dans les évangiles, aucune parole de lui n\'est rapportée : on le voit agir, jamais parler.',
            'episodes' => [
                ['title' => 'Une décision difficile', 'ref' => 'Matthieu 1:18-19', 'text' => 'Il apprend que Marie est enceinte, alors qu\'ils n\'ont pas habité ensemble. Il est « un homme de bien » qui ne veut pas la diffamer ; il se propose de rompre secrètement. Il aurait pu la rendre publique : il choisit la discrétion.',
                    'quote' => ['ref' => 'Matthieu 1:19', 'text' => 'Joseph, son époux, qui était un homme de bien et qui ne voulait pas la diffamer, se proposa de rompre secrètement avec elle.']],
                ['title' => 'Ne crains pas', 'ref' => 'Matthieu 1:20-25', 'text' => 'Pendant qu\'il y pense, un ange lui apparaît en songe. Il lui dit de ne pas craindre de prendre Marie avec lui, car l\'enfant vient du Saint-Esprit, et d\'appeler l\'enfant Jésus. Joseph s\'éveille et fait ce que l\'ange lui a ordonné ; il donne lui-même le nom de Jésus.',
                    'quote' => ['ref' => 'Matthieu 1:20', 'text' => 'Joseph, fils de David, ne crains pas de prendre avec toi Marie, ta femme, car l\'enfant qu\'elle a conçu vient du Saint-Esprit']],
                ['title' => 'La fuite de nuit', 'ref' => 'Matthieu 2:13-15', 'text' => 'Un ange l\'avertit qu\'Hérode cherche l\'enfant. Sans un mot rapporté, « Joseph se leva, prit de nuit le petit enfant et sa mère, et se retira en Égypte ». Il y reste jusqu\'à la mort d\'Hérode.'],
                ['title' => 'Le retour et Nazareth', 'ref' => 'Matthieu 2:19-23', 'text' => 'Averti de revenir, il part, mais « il craignit de s\'y rendre » en apprenant qu\'Archélaüs règne en Judée (2:22). Divinement averti, il s\'établit à Nazareth, en Galilée.'],
                ['title' => 'Jérusalem, quand l\'enfant disparaît', 'ref' => 'Luc 2:41-52', 'text' => 'Chaque année, la famille monte à Jérusalem pour la Pâque. Quand Jésus a douze ans, il reste au temple sans qu\'ils le sachent. Ils le cherchent trois jours. Jésus parle des « affaires de mon Père » (2:49) : Joseph apprend, en silence, qu\'il n\'est pas le père ultime de cet enfant. Ensuite, Jésus « leur était soumis » (2:51).'],
            ],
            'reading' => [
                'temperament' => 'Le récit montre un homme prudent, discret, constant, qui obéit sans rien réclamer et qui agit plutôt que de parler. Nous y lisons les dimensions Stable et Conforme du DISC : fidélité, prudence, souci de bien faire, présence régulière. Les auteurs bibliques ne pensaient pas en DISC : c\'est une lecture, pas une affirmation du texte.',
                'traits' => [
                    ['Il choisit la discrétion plutôt que l\'éclat', 'Matthieu 1:19'],
                    ['Il obéit sans délai', 'Matthieu 1:24 ; 2:14'],
                    ['Il est prudent', 'Matthieu 2:22'],
                    ['Il revient chaque année', 'Luc 2:41'],
                ],
            ],
            'weakness' => 'Le récit ne rapporte aucune chute de Joseph. Ce que la lecture signale comme vigilance, c\'est la crainte : devant l\'inquiétude, il pense d\'abord à se retirer (Matthieu 1:19 ; 2:22). Chaque fois, Dieu l\'atteint au moment de sa crainte, par un songe, et jamais par un reproche : « ne crains pas » (1:20).',
            'grace' => 'Sa bonté n\'est pas l\'application froide d\'une règle : il pouvait rompre publiquement, il choisit de ne pas diffamer. Puis il reçoit ce qu\'il n\'a pas mérité : devenir le père terrestre du Sauveur. Le texte ne dit pas qu\'il a gagné ce rôle ; il le reçoit, et il répond par la confiance. Son obéissance suit la grâce, elle ne l\'achète pas.',
            'christ' => 'Le rôle de Joseph est de recevoir et de protéger le Fils, jamais d\'être au centre. Il donne le nom de Jésus, « car c\'est lui qui sauvera son peuple de ses péchés » (Matthieu 1:21). À douze ans, Jésus parle de « mon Père » (Luc 2:49) : Joseph accepte de s\'effacer. L\'homme discret n\'est pas moins aimé que celui qui parle fort ; il est appelé à garder celui qui sauve.',
            'quotes' => [
                ['ref' => 'Matthieu 1:21', 'text' => 'elle enfantera un fils, et tu lui donneras le nom de Jésus; c\'est lui qui sauvera son peuple de ses péchés.'],
                ['ref' => 'Luc 2:49', 'text' => 'Ne saviez-vous pas qu\'il faut que je m\'occupe des affaires de mon Père?'],
            ],
            'question' => 'Qu\'est-ce que je retiens par prudence, alors que Dieu me dit « ne crains pas » ?',
            'step' => 'Faire cette semaine un pas simple que vous repoussez par prudence, et le confier à Dieu dans la prière avant de le faire.',
        ],

        'TIMOTHEE' => [
            'name' => 'Timothée', 'aliases' => ['Timothée'], 'reviewed' => false,
            'title' => 'Le jeune sensible que Paul n\'a pas laissé seul',
            'refs' => 'Actes 16 ; Philippiens 2 ; 1 et 2 Timothée',
            'why' => [
                'S' => 'Comme lui, vous êtes loyal, attentif aux autres et fiable, avec parfois une retenue à vous avancer. Son récit montre comment Dieu fortifie ce tempérament sans le changer en un autre.',
                'C' => 'Comme lui, vous êtes consciencieux et sensible au jugement des autres. Son récit montre que la force du consciencieux ne vient pas de lui, mais de la grâce.',
                'default' => 'Sa loyauté et sa sensibilité éclairent les tempéraments posés, qui se retiennent d\'occuper le devant de la scène.',
            ],
            'who' => 'Timothée est un disciple de Lystre, « fils d\'une femme juive fidèle et d\'un père grec » (Actes 16:1). Les frères de Lystre et d\'Icone en disent du bien. Paul l\'emmène avec lui (16:2-3), puis l\'envoie en mission à Thessalonique et ailleurs (1 Thessaloniciens 3:2) ; plus tard, il l\'établit à Éphèse (1 Timothée 1:3).',
            'episodes' => [
                ['title' => 'Une foi reçue d\'une maison', 'ref' => '2 Timothée 1:5', 'text' => 'Paul se souvient de « la foi sincère » qui habita d\'abord en son aïeule Loïs et en sa mère Eunice. La foi de Timothée a des racines familiales, dans une maison mixte.'],
                ['title' => 'Choisi malgré tout', 'ref' => 'Actes 16:1-3', 'text' => 'Il est de père grec, de mère juive. Les frères en disent du bien et Paul veut l\'emmener. Son parcours commence par la recommandation de ceux qui le connaissent.'],
                ['title' => 'Un collaborateur sur qui compter', 'ref' => 'Philippiens 2:19-22', 'text' => 'Paul n\'a personne d\'autre qui « partage mes sentiments » et prenne à cœur la situation des Philippiens. Timothée a été mis à l\'épreuve, « comme un enfant avec son père ».',
                    'quote' => ['ref' => 'Philippiens 2:20', 'text' => 'je n\'ai personne ici qui partage mes sentiments, pour prendre sincèrement à coeur votre situation']],
                ['title' => 'Sans crainte, et pas méprisé', 'ref' => '1 Corinthiens 16:10-11 ; 1 Timothée 4:12', 'text' => 'Paul demande aux Corinthiens de faire en sorte qu\'il soit « sans crainte » parmi eux et que personne ne le méprise. Il lui écrit de ne pas laisser mépriser sa jeunesse. Il lui recommande même un peu de vin, « à cause de ton estomac et de tes fréquentes indispositions » (1 Timothée 5:23) : un homme fragile, connu comme tel.'],
                ['title' => 'Des larmes, et un don à ranimer', 'ref' => '2 Timothée 1:4-7', 'text' => 'Paul se souvient de ses larmes. Il l\'exhorte à « ranimer le don de Dieu » reçu par l\'imposition des mains, car Dieu n\'a pas donné « un esprit de timidité », mais « un esprit de force, d\'amour et de sagesse ».',
                    'quote' => ['ref' => '2 Timothée 1:7', 'text' => 'Car ce n\'est pas un esprit de timidité que Dieu nous a donné, mais un esprit de force, d\'amour et de sagesse.']],
                ['title' => 'Viens vers moi', 'ref' => '2 Timothée 4:9, 21', 'text' => 'Dans sa dernière lettre, Paul lui demande de venir au plus tôt, avant l\'hiver. Le vieil apôtre a besoin de lui : le sensible n\'est pas un maillon faible, il est aimé et attendu.'],
            ],
            'reading' => [
                'temperament' => 'Le récit montre un homme loyal, sensible, fiable, qui se retient de prendre toute la place et que la crainte peut freiner. Nous y lisons les dimensions Stable et Conforme du DISC : fidélité, sensibilité, souci de bien faire, retenue. Les auteurs bibliques ne pensaient pas en DISC : c\'est une lecture, pas une affirmation du texte.',
                'traits' => [
                    ['Il est fiable, au point que Paul compte sur lui', 'Philippiens 2:20-22'],
                    ['Il est sensible', '2 Timothée 1:4'],
                    ['Il peut craindre ou se retenir', '1 Corinthiens 16:10 ; 2 Timothée 1:7'],
                    ['Il est fragile physiquement', '1 Timothée 5:23'],
                ],
            ],
            'weakness' => 'Le texte laisse entendre une tendance à la crainte et au retrait : Paul demande qu\'il soit « sans crainte » (1 Corinthiens 16:10), l\'encourage à ne pas être méprisé pour sa jeunesse (1 Timothée 4:12) et à ranimer son don (2 Timothée 1:6). Pour ce tempérament, la vigilance est de laisser la timidité éteindre ce que Dieu a mis en soi.',
            'grace' => 'Paul ne lui dit pas de devenir un autre : il le renvoie au don reçu (2 Timothée 1:6) et à ce que Dieu donne, « force, amour et sagesse » (1:7). Il ne lui demande pas d\'avoir plus de caractère, mais de se tourner vers celui qui donne. Et il l\'appelle auprès de lui, parce qu\'il tient à lui.',
            'christ' => 'La parole décisive est dans 2 Timothée 2:1, citée ci-dessous : la force vient de la grâce « qui est en Jésus-Christ ». Ce n\'est pas Paul qui est la source, ni le caractère de Timothée : la grâce est en Jésus-Christ, et elle est donnée. Pour la personne sensible, la force vient de la grâce reçue, pas d\'un effort pour se montrer plus sûre d\'elle.',
            'quotes' => [
                ['ref' => '2 Timothée 2:1', 'text' => 'Toi donc, mon enfant, fortifie-toi dans la grâce qui est en Jésus-Christ.'],
                ['ref' => '1 Timothée 4:12', 'text' => 'Que personne ne méprise ta jeunesse; mais sois un modèle pour les fidèles'],
            ],
            'question' => 'Quel don la timidité retient-elle en moi, et à qui puis-je le confier pour qu\'on m\'aide à le ranimer ?',
            'step' => 'Exercer ce don une fois cette semaine, devant une personne qui vous encourage, et lui demander son retour.',
        ],
    ];

    /** Fiche d'un personnage d'après son nom tel qu'il apparaît dans les profils, ou null s'il n'a pas encore de fiche. */
    public static function find(string $name): ?array
    {
        foreach (self::FIGURES as $key => $fig) {
            if (\in_array($name, $fig['aliases'], true)) {
                return $fig + ['key' => $key];
            }
        }

        return null;
    }

    /** Pourquoi cette figure parle à ce profil DISC (dimension principale), avec un texte par défaut. */
    public static function why(array $fig, string $discLetter): string
    {
        return $fig['why'][$discLetter] ?? $fig['why']['default'];
    }
}
