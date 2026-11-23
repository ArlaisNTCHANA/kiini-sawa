<?php

namespace App\Boussole\Service;

/**
 * Textes de la Boussole 360 (24 affirmations, lectures par plan, matrice des 30 dynamiques).
 *
 * Copie conforme des données de assets/js/boussole-engine.js, qui affiche le résultat sans attendre dans le
 * navigateur. Toute modification doit être faite des deux côtés : tests/Boussole/BoussoleParityTest.php compare
 * les deux calculs sur des centaines de cas (fichier tests/fixtures/boussole-parite.json, régénéré par
 * `node tests/boussole-parite.mjs --ecrire`). Les noms des plans viennent de PlanCatalog::PLANS.
 */
final class BoussoleContent
{
    public const SCALE = [
        [
            'val' => 1,
            'label' => 'Pas du tout vrai',
        ],
        [
            'val' => 2,
            'label' => 'Plutôt faux',
        ],
        [
            'val' => 3,
            'label' => 'Mitigé',
        ],
        [
            'val' => 4,
            'label' => 'Plutôt vrai',
        ],
        [
            'val' => 5,
            'label' => 'Tout à fait vrai',
        ],
    ];

    public const PLANS = [
        'VIE' => [
            'short' => 'Vie',
            'color' => '#C8962E',
            'angle' => -90,
            'highText' => 'Votre direction est claire : vos choix importants s\'appuient sur des convictions posées.',
            'midText' => 'Vous avez une idée de votre direction, mais les urgences ou les attentes extérieures viennent encore la brouiller.',
            'lowText' => 'Votre emploi du temps tourne, mais le cap manque de netteté. Clarifier votre direction vous évitera de vous disperser.',
            'stepsLow' => [
                'Prenez 20 minutes au calme cette semaine pour écrire en une phrase ce que vous voulez vraiment bâtir cette année.',
                'Relisez vos 7 derniers jours : repérez une activité qui prend beaucoup de place sans servir ce qui compte pour vous.',
                'Dites non cette semaine à une sollicitation qui ne correspond pas à votre direction.',
            ],
            'stepsMid' => [
                'Vérifiez que vos 3 objectifs principaux du trimestre sont bien reliés à vos convictions profondes.',
                'Bloquez un rendez-vous mensuel de 30 minutes pour ajuster votre cap avant que les urgences ne décident à votre place.',
                'Partagez votre direction de l\'année à un proche de confiance pour la rendre concrète.',
            ],
        ],
        'CROISSANCE' => [
            'short' => 'Croissance',
            'color' => '#1F4D3A',
            'angle' => -30,
            'highText' => 'Vous prenez soin de votre caractère et vous êtes entouré(e) de personnes qui peuvent vous dire la vérité.',
            'midText' => 'Vos valeurs sont là, mais elles restent fragiles sous pression ou peu soutenues par un entourage de confiance.',
            'lowText' => 'Sous pression, l\'écart se creuse entre vos valeurs et vos réactions, ou vous avancez trop seul(e) sur vos luttes.',
            'stepsLow' => [
                'Choisissez un seul point précis de caractère sur lequel vous voulez progresser ce mois-ci.',
                'Repensez à une situation récente où vous avez réagi trop vite, et décidez à l\'avance comment répondre la prochaine fois.',
                'Contactez une personne de confiance cette semaine pour lui demander de vous parler franchement quand vous dérivez.',
            ],
            'stepsMid' => [
                'Définissez une ligne rouge claire que vous refusez de franchir même sous forte pression.',
                'Planifiez un échange vrai ce mois-ci avec un mentor ou un partenaire de redevabilité.',
                'Prenez 5 minutes chaque vendredi pour relire une réaction dont vous êtes fier(ère) et une à ajuster.',
            ],
        ],
        'DEVELOPPEMENT' => [
            'short' => 'Développ.',
            'color' => '#2E6B53',
            'angle' => 30,
            'highText' => 'Vous continuez à apprendre et à faire grandir vos compétences de façon ciblée.',
            'midText' => 'Vous progressez par moments, mais sans rythme régulier ni objectif d\'apprentissage vraiment précis.',
            'lowText' => 'Vous vous appuyez surtout sur vos acquis. Sans apprentissage ciblé, votre progression finit par plafonner.',
            'stepsLow' => [
                'Identifiez la compétence précise qui aurait le plus d\'impact sur votre travail ou vos projets dans les 3 mois.',
                'Bloquez deux créneaux de 45 minutes dans votre semaine pour travailler cette compétence sans distraction.',
                'Mettez en pratique cette semaine une seule chose apprise au lieu d\'accumuler de nouvelles lectures.',
            ],
            'stepsMid' => [
                'Reliez votre lecture ou formation en cours à un livrable concret à produire d\'ici 30 jours.',
                'Demandez un retour franc à quelqu\'un de plus avancé que vous sur votre pratique actuelle.',
                'Éliminez une source de contenu dispersante pour concentrer votre attention sur votre axe prioritaire.',
            ],
        ],
        'HYGIENE' => [
            'short' => 'Hygiène',
            'color' => '#B5562F',
            'angle' => 90,
            'highText' => 'Votre rythme de sommeil, de repos et d\'énergie soutient bien le reste de votre vie.',
            'midText' => 'Votre énergie tient globalement, mais votre récupération reste fragile dès que les semaines s\'accélèrent.',
            'lowText' => 'La fatigue est installée. Difficile de tenir dans la durée si votre corps, votre calme intérieur et votre repos passent en dernier.',
            'stepsLow' => [
                'Fixez une heure de coucher réaliste pour les 5 prochains soirs et coupez les écrans 30 minutes avant.',
                'Bloquez un vrai créneau de repos cette semaine — sans travail ni culpabilité.',
                'Marchez 30 minutes au moins trois fois cette semaine pour relancer votre énergie physique.',
            ],
            'stepsMid' => [
                'Protégez un rituel quotidien de 15 minutes de calme, de prière ou de silence avant de consulter votre téléphone.',
                'Repérez ce qui vous vide le plus dans votre semaine et posez une limite claire autour de ce moment.',
                'Inscrivez votre jour de repos hebdomadaire dans votre agenda avant vos autres engagements.',
            ],
        ],
        'INTENDANCE' => [
            'short' => 'Intendance',
            'color' => '#5E8463',
            'angle' => 150,
            'highText' => 'Vous gérez votre temps, vos finances et vos responsabilités avec clarté et suivi.',
            'midText' => 'L\'essentiel est tenu, mais certains sujets (budget, temps, engagements) méritent un cadre plus régulier.',
            'lowText' => 'Une partie de votre temps ou de votre argent part sans vrai suivi, ce qui crée une tension inutile au quotidien.',
            'stepsLow' => [
                'Prenez 20 minutes cette semaine pour regarder votre dernier mois de dépenses et repérer une fuite à corriger.',
                'Listez 3 sujets administratifs, financiers ou matériels qui traînent et réglez le plus simple dès demain.',
                'Regardez où sont passées vos heures cette semaine et récupérez un créneau de 2 heures pour l\'essentiel.',
            ],
            'stepsMid' => [
                'Instaurez un point budget et engagements fixe d\'une heure au début de chaque mois.',
                'Décidez d\'un montant précis d\'épargne ou de générosité automatisé dès réception de vos revenus.',
                'Faites le tri dans un engagement actuel qui consomme plus qu\'il ne porte de fruit.',
            ],
        ],
        'TRANSMISSION' => [
            'short' => 'Transm.',
            'color' => '#A87A1F',
            'angle' => 210,
            'highText' => 'Vous ne gardez pas ce que vous recevez pour vous : votre vie porte déjà du fruit autour de vous.',
            'midText' => 'L\'envie de transmettre est présente, mais elle passe encore souvent après les urgences de la semaine.',
            'lowText' => 'Le quotidien absorbe presque tout votre horizon ; il reste peu de place pour transmettre à d\'autres.',
            'stepsLow' => [
                'Pensez à une personne précise autour de vous (enfant, jeune, collègue) à qui transmettre quelque chose d\'utile.',
                'Proposez-lui cette semaine un moment simple pour échanger, écouter ou partager votre expérience.',
                'Écrivez en trois lignes une valeur ou un savoir-faire concret que vous souhaitez laisser après vous.',
            ],
            'stepsMid' => [
                'Choisissez une personne à accompagner ou encourager de façon suivie sur les 3 prochains mois.',
                'Formalisez par écrit ou à l\'oral une leçon clé de votre parcours pour qu\'elle profite à d\'autres.',
                'Bloquez un créneau mensuel dédié à un projet qui vous dépassera.',
            ],
        ],
    ];

    public const ITEMS = [
        [
            'plan' => 'VIE',
            'rev' => false,
            'text' => 'Si on me demande aujourd\'hui ce que je veux vraiment bâtir de ma vie, ma réponse est claire.',
            'lowSignal' => 'Votre cap de fond reste encore flou : difficile de formuler simplement ce que vous voulez bâtir.',
        ],
        [
            'plan' => 'CROISSANCE',
            'rev' => false,
            'text' => 'Je travaille consciemment sur un point précis de mon caractère en ce moment.',
            'lowSignal' => 'Vous ne travaillez aucun point précis de caractère cette saison.',
        ],
        [
            'plan' => 'DEVELOPPEMENT',
            'rev' => false,
            'text' => 'Chaque semaine, je réserve du temps pour améliorer une compétence importante pour mon avenir.',
            'lowSignal' => 'Votre semaine ne contient presque aucun temps réservé à développer vos compétences.',
        ],
        [
            'plan' => 'HYGIENE',
            'rev' => true,
            'text' => 'Je termine souvent mes semaines vidé(e), sans vrai temps pour récupérer.',
            'lowSignal' => 'Vous terminez souvent vos semaines vidé(e), sans vrai temps pour récupérer.',
        ],
        [
            'plan' => 'INTENDANCE',
            'rev' => false,
            'text' => 'Je sais précisément où passent mon argent et mon temps chaque mois.',
            'lowSignal' => 'Vous manquez de visibilité claire sur l\'usage réel de votre temps et de votre argent.',
        ],
        [
            'plan' => 'TRANSMISSION',
            'rev' => false,
            'text' => 'Je prends régulièrement le temps de transmettre ce que je sais ou ce que je crois à quelqu\'un d\'autre.',
            'lowSignal' => 'Ce que vous savez et ce que vous portez reste encore peu transmis autour de vous.',
        ],
        [
            'plan' => 'VIE',
            'rev' => true,
            'text' => 'Mon emploi du temps est plein, mais j\'ai souvent le sentiment de courir sans vraie direction.',
            'lowSignal' => 'Votre emploi du temps est plein, mais avec le sentiment de courir sans vraie direction.',
        ],
        [
            'plan' => 'CROISSANCE',
            'rev' => true,
            'text' => 'Quand la pression monte, mes réactions contredisent encore trop souvent les valeurs que je défends.',
            'lowSignal' => 'Sous pression, vos réactions contredisent encore trop souvent vos valeurs.',
        ],
        [
            'plan' => 'DEVELOPPEMENT',
            'rev' => true,
            'text' => 'Je me repose surtout sur mon expérience passée, sans vrai plan pour progresser cette année.',
            'lowSignal' => 'Vous vous reposez surtout sur vos acquis, sans vrai plan de progression cette année.',
        ],
        [
            'plan' => 'HYGIENE',
            'rev' => false,
            'text' => 'Mon sommeil, mon alimentation et mon activité physique me donnent l\'énergie dont j\'ai besoin.',
            'lowSignal' => 'Votre sommeil, votre alimentation ou votre rythme physique ne soutiennent plus assez votre énergie.',
        ],
        [
            'plan' => 'INTENDANCE',
            'rev' => true,
            'text' => 'Des sujets importants (finances, démarches, engagements) traînent par manque de cadre clair.',
            'lowSignal' => 'Des sujets importants (finances, démarches, engagements) traînent par manque de cadre.',
        ],
        [
            'plan' => 'TRANSMISSION',
            'rev' => true,
            'text' => 'Les urgences de la semaine absorbent tellement mon attention que je pense rarement à ce que je laisserai après moi.',
            'lowSignal' => 'Les urgences de la semaine absorbent votre horizon au détriment de ce qui durera après vous.',
        ],
        [
            'plan' => 'VIE',
            'rev' => false,
            'text' => 'Mes grandes décisions des six derniers mois reflètent mes convictions profondes, pas seulement les attentes des autres.',
            'lowSignal' => 'Vos décisions récentes ont davantage répondu aux attentes extérieures qu\'à vos convictions.',
        ],
        [
            'plan' => 'CROISSANCE',
            'rev' => false,
            'text' => 'J\'ai au moins une personne de confiance autorisée à me dire franchement quand je fais fausse route.',
            'lowSignal' => 'Vous avancez sans personne de confiance autorisée à vous parler franchement quand vous dérivez.',
        ],
        [
            'plan' => 'DEVELOPPEMENT',
            'rev' => false,
            'text' => 'Ce que je lis ou apprends en ce moment sert directement un objectif concret.',
            'lowSignal' => 'Vos lectures ou apprentissages actuels ne sont pas reliés à un objectif précis.',
        ],
        [
            'plan' => 'HYGIENE',
            'rev' => false,
            'text' => 'Je m\'accorde régulièrement des temps de calme, de prière ou de recul qui me ressourcent vraiment.',
            'lowSignal' => 'Vous manquez de vrais temps de calme, de prière ou de recul pour vous ressourcer.',
        ],
        [
            'plan' => 'INTENDANCE',
            'rev' => false,
            'text' => 'Je gère mes finances, mon foyer et mes responsabilités de manière posée et suivie.',
            'lowSignal' => 'La gestion de vos finances, de votre foyer ou de vos engagements manque de suivi posé.',
        ],
        [
            'plan' => 'TRANSMISSION',
            'rev' => false,
            'text' => 'Mes choix actuels ont déjà un impact concret et durable sur d\'autres vies.',
            'lowSignal' => 'Vous peinez encore à voir l\'impact durable de vos choix actuels sur d\'autres vies.',
        ],
        [
            'plan' => 'VIE',
            'rev' => true,
            'text' => 'Il m\'arrive de dire oui à des projets ou des engagements qui ne correspondent pas vraiment à ce qui compte pour moi.',
            'lowSignal' => 'Vous acceptez encore trop souvent des engagements qui ne correspondent pas à vos priorités.',
        ],
        [
            'plan' => 'CROISSANCE',
            'rev' => true,
            'text' => 'J\'avance surtout seul(e) sur mes difficultés personnelles, sans vraiment en parler à quelqu\'un qui me connaît bien.',
            'lowSignal' => 'Vous portez vos difficultés personnelles en solitaire, sans vrai soutien proche.',
        ],
        [
            'plan' => 'DEVELOPPEMENT',
            'rev' => true,
            'text' => 'J\'accumule des lectures, des vidéos ou des idées sans vraiment les mettre en pratique dans mon travail ou mes projets.',
            'lowSignal' => 'Vous accumulez des idées ou lectures sans les transformer en pratique concrète.',
        ],
        [
            'plan' => 'HYGIENE',
            'rev' => true,
            'text' => 'Je culpabilise ou j\'ai du mal à décrocher quand je prends un vrai moment de repos.',
            'lowSignal' => 'Vous culpabilisez ou avez du mal à décrocher lors de vos moments de repos.',
        ],
        [
            'plan' => 'INTENDANCE',
            'rev' => true,
            'text' => 'Des dépenses ou des pertes de temps évitables reviennent régulièrement sans que je les corrige vraiment.',
            'lowSignal' => 'Des dépenses ou pertes de temps évitables reviennent régulièrement sans correction durable.',
        ],
        [
            'plan' => 'TRANSMISSION',
            'rev' => true,
            'text' => 'J\'ai le sentiment que ce que j\'ai appris jusqu\'ici profite encore trop peu aux personnes autour de moi.',
            'lowSignal' => 'Votre expérience accumulée profite encore trop peu aux personnes autour de vous.',
        ],
    ];

    public const PAIR_DYNAMICS = [
        'VIE__CROISSANCE' => 'Vous savez où vous voulez aller, mais le travail sur votre caractère et votre entourage de confiance ne suit pas au même rythme. Sans garde-fou solide, la pression finit souvent par fissurer ce que la vision construit.',
        'VIE__DEVELOPPEMENT' => 'Votre direction est claire, mais vos compétences n\'évoluent plus au même niveau que votre ambition. Aiguiser un savoir-faire clé donnera beaucoup plus de portée à vos convictions.',
        'VIE__HYGIENE' => 'Vous savez où vous voulez aller, mais votre corps et votre repos paient la facture de votre engagement. Tant que la récupération passe en dernier, votre vision avance à crédit.',
        'VIE__INTENDANCE' => 'Votre cap est clair, mais la gestion concrète de votre temps, de vos finances ou de vos engagements ne suit pas encore. Ce décalage crée une friction matérielle qui ralentit vos projets.',
        'VIE__TRANSMISSION' => 'Votre direction personnelle est posée, mais elle reste encore centrée sur votre propre parcours. Votre prochaine étape consiste à faire bénéficier d\'autres vies de la clarté que vous avez trouvée.',
        'CROISSANCE__VIE' => 'Vous avez de la profondeur humaine et de vraies valeurs, mais sans direction suffisamment précise pour les orienter. Clarifier votre cap évitera à votre sincérité de se disperser.',
        'CROISSANCE__DEVELOPPEMENT' => 'Votre caractère et vos relations sont des appuis solides, mais vos compétences techniques ou professionnelles demandent un vrai coup d\'accélérateur pour soutenir pleinement votre utilité.',
        'CROISSANCE__HYGIENE' => 'Vous prenez soin de vos valeurs et des autres, mais vous négligez votre propre énergie. Même avec un cœur droit, on ne peut pas donner durablement en restant épuisé(e).',
        'CROISSANCE__INTENDANCE' => 'Votre cœur est droit et vos valeurs sont sincères, mais l\'absence de cadre sur votre temps ou vos finances crée une tension matérielle qui finit par peser sur votre paix.',
        'CROISSANCE__TRANSMISSION' => 'Vous avez déjà beaucoup travaillé sur vous-même et votre entourage proche, mais cette maturité reste encore peu partagée au-delà. D\'autres autour de vous pourraient déjà grandir grâce à ce chemin.',
        'DEVELOPPEMENT__VIE' => 'Vous avez de vraies compétences et l\'habitude d\'apprendre, mais sans cap choisi, votre énergie risque de servir les priorités des autres plutôt que les vôtres.',
        'DEVELOPPEMENT__CROISSANCE' => 'Vos compétences progressent bien, mais votre maîtrise de soi sous pression ou votre entourage de vérité reste en retrait. Des capacités fortes ont besoin d\'un caractère tout aussi solide.',
        'DEVELOPPEMENT__HYGIENE' => 'Vous investissez beaucoup dans votre progression et votre efficacité, mais votre rythme physique et votre repos décrochent. Sans récupération, la performance finit toujours par s\'user.',
        'DEVELOPPEMENT__INTENDANCE' => 'Vous savez apprendre et produire, mais la gestion suivie de vos finances, de votre temps ou de vos engagements laisse fuir une partie des fruits de vos efforts.',
        'DEVELOPPEMENT__TRANSMISSION' => 'Vous avez accumulé de solides compétences et une vraie expérience, mais ce capital reste surtout personnel. Former ou conseiller quelqu\'un multiplierait la valeur de ce que vous savez.',
        'HYGIENE__VIE' => 'Vous avez su préserver votre équilibre et votre énergie, ce qui est rare. Il manque maintenant une direction claire pour mettre cette ressource au service d\'un vrai projet de vie.',
        'HYGIENE__CROISSANCE' => 'Votre rythme de vie est sain et stable, mais le travail en profondeur sur le caractère ou l\'entourage qui vous remet en question reste encore timide.',
        'HYGIENE__DEVELOPPEMENT' => 'Vous avez l\'énergie et la stabilité nécessaires pour durer, mais vos compétences n\'avancent plus au même rythme : vous avez la ressource pour apprendre davantage et viser plus haut.',
        'HYGIENE__INTENDANCE' => 'Vous protégez bien votre énergie personnelle, mais la gestion concrète de vos finances, de vos dossiers ou de votre organisation reste flottante et finit par peser sur votre tranquillité.',
        'HYGIENE__TRANSMISSION' => 'Vous avez bâti un équilibre personnel solide, mais il profite encore surtout à vous-même. Mettre cette stabilité au service d\'une autre personne lui donnerait une dimension nouvelle.',
        'INTENDANCE__VIE' => 'Vous êtes organisé(e) et fiable dans la gestion du quotidien, mais sans direction personnelle claire, le risque est de gérer impeccablement des priorités qui ne sont pas vraiment les vôtres.',
        'INTENDANCE__CROISSANCE' => 'Votre gestion pratique tient bien la route, mais le travail intérieur sur vos réactions ou l\'ouverture à des personnes qui vous disent la vérité passe encore au second plan.',
        'INTENDANCE__DEVELOPPEMENT' => 'Vous savez tenir un cadre, un budget et des engagements, mais vous n\'investissez plus assez dans le développement de nouvelles compétences pour faire évoluer votre parcours.',
        'INTENDANCE__HYGIENE' => 'Vous tenez sérieusement vos responsabilités, votre foyer et vos finances, mais au prix de votre propre fatigue. Protéger votre repos fait aussi partie d\'une bonne gestion.',
        'INTENDANCE__TRANSMISSION' => 'Vous gérez bien ce que vous avez entre les mains — temps, finances, engagements. La question maintenant est de passer d\'une bonne gestion pour soi à ce qui porte du fruit pour d\'autres.',
        'TRANSMISSION__VIE' => 'Vous donnez beaucoup autour de vous, mais votre propre direction de vie manque de netteté. Prendre le temps de clarifier votre propre cap rendra votre générosité encore plus juste.',
        'TRANSMISSION__CROISSANCE' => 'Vous aimez partager et investir dans les autres, mais vous avez besoin de garder un vrai travail personnel sur votre caractère et des pairs capables de vous reprendre avec franchise.',
        'TRANSMISSION__DEVELOPPEMENT' => 'Vous êtes généreux(se) dans ce que vous partagez, mais renouveler vos propres compétences est indispensable pour continuer à nourrir les autres sans vous répéter.',
        'TRANSMISSION__HYGIENE' => 'Vous donnez beaucoup aux autres et cela se voit, mais vous vous oubliez en route. Si vous ne vous rechargez pas, c\'est votre capacité même à aider qui va finir par s\'éteindre.',
        'TRANSMISSION__INTENDANCE' => 'Votre cœur est tourné vers les autres et l\'impact durable, mais le suivi concret de votre temps et de vos finances manque de rigueur pour soutenir cet élan sans stress.',
    ];
}
