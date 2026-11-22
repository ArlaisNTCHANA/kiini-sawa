(function (root) {
  'use strict';

  const PLAN_ORDER = ['VIE', 'CROISSANCE', 'DEVELOPPEMENT', 'HYGIENE', 'INTENDANCE', 'TRANSMISSION'];

  const SCALE = [
    { val: 1, label: 'Pas du tout vrai' },
    { val: 2, label: 'Plutôt faux' },
    { val: 3, label: 'Mitigé' },
    { val: 4, label: 'Plutôt vrai' },
    { val: 5, label: 'Tout à fait vrai' }
  ];

  const PLANS = {
    VIE: {
      key: 'VIE',
      short: 'Vie',
      name: 'Plan de Vie',
      color: '#C8962E',
      angle: -90,
      highText: 'Votre direction est claire : vos choix importants s\'appuient sur des convictions posées.',
      midText: 'Vous avez une idée de votre direction, mais les urgences ou les attentes extérieures viennent encore la brouiller.',
      lowText: 'Votre emploi du temps tourne, mais le cap manque de netteté. Clarifier votre direction vous évitera de vous disperser.',
      stepsLow: [
        'Prenez 20 minutes au calme cette semaine pour écrire en une phrase ce que vous voulez vraiment bâtir cette année.',
        'Relisez vos 7 derniers jours : repérez une activité qui prend beaucoup de place sans servir ce qui compte pour vous.',
        'Dites non cette semaine à une sollicitation qui ne correspond pas à votre direction.'
      ],
      stepsMid: [
        'Vérifiez que vos 3 objectifs principaux du trimestre sont bien reliés à vos convictions profondes.',
        'Bloquez un rendez-vous mensuel de 30 minutes pour ajuster votre cap avant que les urgences ne décident à votre place.',
        'Partagez votre direction de l\'année à un proche de confiance pour la rendre concrète.'
      ]
    },
    CROISSANCE: {
      key: 'CROISSANCE',
      short: 'Croissance',
      name: 'Plan de Croissance',
      color: '#1F4D3A',
      angle: -30,
      highText: 'Vous prenez soin de votre caractère et vous êtes entouré(e) de personnes qui peuvent vous dire la vérité.',
      midText: 'Vos valeurs sont là, mais elles restent fragiles sous pression ou peu soutenues par un entourage de confiance.',
      lowText: 'Sous pression, l\'écart se creuse entre vos valeurs et vos réactions, ou vous avancez trop seul(e) sur vos luttes.',
      stepsLow: [
        'Choisissez un seul point précis de caractère sur lequel vous voulez progresser ce mois-ci.',
        'Repensez à une situation récente où vous avez réagi trop vite, et décidez à l\'avance comment répondre la prochaine fois.',
        'Contactez une personne de confiance cette semaine pour lui demander de vous parler franchement quand vous dérivez.'
      ],
      stepsMid: [
        'Définissez une ligne rouge claire que vous refusez de franchir même sous forte pression.',
        'Planifiez un échange vrai ce mois-ci avec un mentor ou un partenaire de redevabilité.',
        'Prenez 5 minutes chaque vendredi pour relire une réaction dont vous êtes fier(ère) et une à ajuster.'
      ]
    },
    DEVELOPPEMENT: {
      key: 'DEVELOPPEMENT',
      short: 'Développ.',
      name: 'Plan de Développement personnel',
      color: '#2E6B53',
      angle: 30,
      highText: 'Vous continuez à apprendre et à faire grandir vos compétences de façon ciblée.',
      midText: 'Vous progressez par moments, mais sans rythme régulier ni objectif d\'apprentissage vraiment précis.',
      lowText: 'Vous vous appuyez surtout sur vos acquis. Sans apprentissage ciblé, votre progression finit par plafonner.',
      stepsLow: [
        'Identifiez la compétence précise qui aurait le plus d\'impact sur votre travail ou vos projets dans les 3 mois.',
        'Bloquez deux créneaux de 45 minutes dans votre semaine pour travailler cette compétence sans distraction.',
        'Mettez en pratique cette semaine une seule chose apprise au lieu d\'accumuler de nouvelles lectures.'
      ],
      stepsMid: [
        'Reliez votre lecture ou formation en cours à un livrable concret à produire d\'ici 30 jours.',
        'Demandez un retour franc à quelqu\'un de plus avancé que vous sur votre pratique actuelle.',
        'Éliminez une source de contenu dispersante pour concentrer votre attention sur votre axe prioritaire.'
      ]
    },
    HYGIENE: {
      key: 'HYGIENE',
      short: 'Hygiène',
      name: 'Plan d\'Hygiène de vie',
      color: '#B5562F',
      angle: 90,
      highText: 'Votre rythme de sommeil, de repos et d\'énergie soutient bien le reste de votre vie.',
      midText: 'Votre énergie tient globalement, mais votre récupération reste fragile dès que les semaines s\'accélèrent.',
      lowText: 'La fatigue est installée. Difficile de tenir dans la durée si votre corps, votre calme intérieur et votre repos passent en dernier.',
      stepsLow: [
        'Fixez une heure de coucher réaliste pour les 5 prochains soirs et coupez les écrans 30 minutes avant.',
        'Bloquez un vrai créneau de repos cette semaine — sans travail ni culpabilité.',
        'Marchez 30 minutes au moins trois fois cette semaine pour relancer votre énergie physique.'
      ],
      stepsMid: [
        'Protégez un rituel quotidien de 15 minutes de calme, de prière ou de silence avant de consulter votre téléphone.',
        'Repérez ce qui vous vide le plus dans votre semaine et posez une limite claire autour de ce moment.',
        'Inscrivez votre jour de repos hebdomadaire dans votre agenda avant vos autres engagements.'
      ]
    },
    INTENDANCE: {
      key: 'INTENDANCE',
      short: 'Intendance',
      name: 'Plan d\'Intendance',
      color: '#5E8463',
      angle: 150,
      highText: 'Vous gérez votre temps, vos finances et vos responsabilités avec clarté et suivi.',
      midText: 'L\'essentiel est tenu, mais certains sujets (budget, temps, engagements) méritent un cadre plus régulier.',
      lowText: 'Une partie de votre temps ou de votre argent part sans vrai suivi, ce qui crée une tension inutile au quotidien.',
      stepsLow: [
        'Prenez 20 minutes cette semaine pour regarder votre dernier mois de dépenses et repérer une fuite à corriger.',
        'Listez 3 sujets administratifs, financiers ou matériels qui traînent et réglez le plus simple dès demain.',
        'Regardez où sont passées vos heures cette semaine et récupérez un créneau de 2 heures pour l\'essentiel.'
      ],
      stepsMid: [
        'Instaurez un point budget et engagements fixe d\'une heure au début de chaque mois.',
        'Décidez d\'un montant précis d\'épargne ou de générosité automatisé dès réception de vos revenus.',
        'Faites le tri dans un engagement actuel qui consomme plus qu\'il ne porte de fruit.'
      ]
    },
    TRANSMISSION: {
      key: 'TRANSMISSION',
      short: 'Transm.',
      name: 'Plan de Transmission',
      color: '#A87A1F',
      angle: 210,
      highText: 'Vous ne gardez pas ce que vous recevez pour vous : votre vie porte déjà du fruit autour de vous.',
      midText: 'L\'envie de transmettre est présente, mais elle passe encore souvent après les urgences de la semaine.',
      lowText: 'Le quotidien absorbe presque tout votre horizon ; il reste peu de place pour transmettre à d\'autres.',
      stepsLow: [
        'Pensez à une personne précise autour de vous (enfant, jeune, collègue) à qui transmettre quelque chose d\'utile.',
        'Proposez-lui cette semaine un moment simple pour échanger, écouter ou partager votre expérience.',
        'Écrivez en trois lignes une valeur ou un savoir-faire concret que vous souhaitez laisser après vous.'
      ],
      stepsMid: [
        'Choisissez une personne à accompagner ou encourager de façon suivie sur les 3 prochains mois.',
        'Formalisez par écrit ou à l\'oral une leçon clé de votre parcours pour qu\'elle profite à d\'autres.',
        'Bloquez un créneau mensuel dédié à un projet qui vous dépassera.'
      ]
    }
  };

  // 24 items entrelacés (4 cycles de 6 plans : exactement 2 directs + 2 inversés par plan)
  const ITEMS = [
    // Cycle 1
    { plan: 'VIE', rev: false,
      text: 'Si on me demande aujourd\'hui ce que je veux vraiment bâtir de ma vie, ma réponse est claire.',
      lowSignal: 'Votre cap de fond reste encore flou : difficile de formuler simplement ce que vous voulez bâtir.' },
    { plan: 'CROISSANCE', rev: false,
      text: 'Je travaille consciemment sur un point précis de mon caractère en ce moment.',
      lowSignal: 'Vous ne travaillez aucun point précis de caractère cette saison.' },
    { plan: 'DEVELOPPEMENT', rev: false,
      text: 'Chaque semaine, je réserve du temps pour améliorer une compétence importante pour mon avenir.',
      lowSignal: 'Votre semaine ne contient presque aucun temps réservé à développer vos compétences.' },
    { plan: 'HYGIENE', rev: true,
      text: 'Je termine souvent mes semaines vidé(e), sans vrai temps pour récupérer.',
      lowSignal: 'Vous terminez souvent vos semaines vidé(e), sans vrai temps pour récupérer.' },
    { plan: 'INTENDANCE', rev: false,
      text: 'Je sais précisément où passent mon argent et mon temps chaque mois.',
      lowSignal: 'Vous manquez de visibilité claire sur l\'usage réel de votre temps et de votre argent.' },
    { plan: 'TRANSMISSION', rev: false,
      text: 'Je prends régulièrement le temps de transmettre ce que je sais ou ce que je crois à quelqu\'un d\'autre.',
      lowSignal: 'Ce que vous savez et ce que vous portez reste encore peu transmis autour de vous.' },

    // Cycle 2
    { plan: 'VIE', rev: true,
      text: 'Mon emploi du temps est plein, mais j\'ai souvent le sentiment de courir sans vraie direction.',
      lowSignal: 'Votre emploi du temps est plein, mais avec le sentiment de courir sans vraie direction.' },
    { plan: 'CROISSANCE', rev: true,
      text: 'Quand la pression monte, mes réactions contredisent encore trop souvent les valeurs que je défends.',
      lowSignal: 'Sous pression, vos réactions contredisent encore trop souvent vos valeurs.' },
    { plan: 'DEVELOPPEMENT', rev: true,
      text: 'Je me repose surtout sur mon expérience passée, sans vrai plan pour progresser cette année.',
      lowSignal: 'Vous vous reposez surtout sur vos acquis, sans vrai plan de progression cette année.' },
    { plan: 'HYGIENE', rev: false,
      text: 'Mon sommeil, mon alimentation et mon activité physique me donnent l\'énergie dont j\'ai besoin.',
      lowSignal: 'Votre sommeil, votre alimentation ou votre rythme physique ne soutiennent plus assez votre énergie.' },
    { plan: 'INTENDANCE', rev: true,
      text: 'Des sujets importants (finances, démarches, engagements) traînent par manque de cadre clair.',
      lowSignal: 'Des sujets importants (finances, démarches, engagements) traînent par manque de cadre.' },
    { plan: 'TRANSMISSION', rev: true,
      text: 'Les urgences de la semaine absorbent tellement mon attention que je pense rarement à ce que je laisserai après moi.',
      lowSignal: 'Les urgences de la semaine absorbent votre horizon au détriment de ce qui durera après vous.' },

    // Cycle 3
    { plan: 'VIE', rev: false,
      text: 'Mes grandes décisions des six derniers mois reflètent mes convictions profondes, pas seulement les attentes des autres.',
      lowSignal: 'Vos décisions récentes ont davantage répondu aux attentes extérieures qu\'à vos convictions.' },
    { plan: 'CROISSANCE', rev: false,
      text: 'J\'ai au moins une personne de confiance autorisée à me dire franchement quand je fais fausse route.',
      lowSignal: 'Vous avancez sans personne de confiance autorisée à vous parler franchement quand vous dérivez.' },
    { plan: 'DEVELOPPEMENT', rev: false,
      text: 'Ce que je lis ou apprends en ce moment sert directement un objectif concret.',
      lowSignal: 'Vos lectures ou apprentissages actuels ne sont pas reliés à un objectif précis.' },
    { plan: 'HYGIENE', rev: false,
      text: 'Je m\'accorde régulièrement des temps de calme, de prière ou de recul qui me ressourcent vraiment.',
      lowSignal: 'Vous manquez de vrais temps de calme, de prière ou de recul pour vous ressourcer.' },
    { plan: 'INTENDANCE', rev: false,
      text: 'Je gère mes finances, mon foyer et mes responsabilités de manière posée et suivie.',
      lowSignal: 'La gestion de vos finances, de votre foyer ou de vos engagements manque de suivi posé.' },
    { plan: 'TRANSMISSION', rev: false,
      text: 'Mes choix actuels ont déjà un impact concret et durable sur d\'autres vies.',
      lowSignal: 'Vous peinez encore à voir l\'impact durable de vos choix actuels sur d\'autres vies.' },

    // Cycle 4
    { plan: 'VIE', rev: true,
      text: 'Il m\'arrive de dire oui à des projets ou des engagements qui ne correspondent pas vraiment à ce qui compte pour moi.',
      lowSignal: 'Vous acceptez encore trop souvent des engagements qui ne correspondent pas à vos priorités.' },
    { plan: 'CROISSANCE', rev: true,
      text: 'J\'avance surtout seul(e) sur mes difficultés personnelles, sans vraiment en parler à quelqu\'un qui me connaît bien.',
      lowSignal: 'Vous portez vos difficultés personnelles en solitaire, sans vrai soutien proche.' },
    { plan: 'DEVELOPPEMENT', rev: true,
      text: 'J\'accumule des lectures, des vidéos ou des idées sans vraiment les mettre en pratique dans mon travail ou mes projets.',
      lowSignal: 'Vous accumulez des idées ou lectures sans les transformer en pratique concrète.' },
    { plan: 'HYGIENE', rev: true,
      text: 'Je culpabilise ou j\'ai du mal à décrocher quand je prends un vrai moment de repos.',
      lowSignal: 'Vous culpabilisez ou avez du mal à décrocher lors de vos moments de repos.' },
    { plan: 'INTENDANCE', rev: true,
      text: 'Des dépenses ou des pertes de temps évitables reviennent régulièrement sans que je les corrige vraiment.',
      lowSignal: 'Des dépenses ou pertes de temps évitables reviennent régulièrement sans correction durable.' },
    { plan: 'TRANSMISSION', rev: true,
      text: 'J\'ai le sentiment que ce que j\'ai appris jusqu\'ici profite encore trop peu aux personnes autour de moi.',
      lowSignal: 'Votre expérience accumulée profite encore trop peu aux personnes autour de vous.' }
  ];

  // Matrice complète 6 x 5 = 30 dynamiques croisées (Fort__Faible)
  const PAIR_DYNAMICS = {
    'VIE__CROISSANCE': 'Vous savez où vous voulez aller, mais le travail sur votre caractère et votre entourage de confiance ne suit pas au même rythme. Sans garde-fou solide, la pression finit souvent par fissurer ce que la vision construit.',
    'VIE__DEVELOPPEMENT': 'Votre direction est claire, mais vos compétences n\'évoluent plus au même niveau que votre ambition. Aiguiser un savoir-faire clé donnera beaucoup plus de portée à vos convictions.',
    'VIE__HYGIENE': 'Vous savez où vous voulez aller, mais votre corps et votre repos paient la facture de votre engagement. Tant que la récupération passe en dernier, votre vision avance à crédit.',
    'VIE__INTENDANCE': 'Votre cap est clair, mais la gestion concrète de votre temps, de vos finances ou de vos engagements ne suit pas encore. Ce décalage crée une friction matérielle qui ralentit vos projets.',
    'VIE__TRANSMISSION': 'Votre direction personnelle est posée, mais elle reste encore centrée sur votre propre parcours. Votre prochaine étape consiste à faire bénéficier d\'autres vies de la clarté que vous avez trouvée.',

    'CROISSANCE__VIE': 'Vous avez de la profondeur humaine et de vraies valeurs, mais sans direction suffisamment précise pour les orienter. Clarifier votre cap évitera à votre sincérité de se disperser.',
    'CROISSANCE__DEVELOPPEMENT': 'Votre caractère et vos relations sont des appuis solides, mais vos compétences techniques ou professionnelles demandent un vrai coup d\'accélérateur pour soutenir pleinement votre utilité.',
    'CROISSANCE__HYGIENE': 'Vous prenez soin de vos valeurs et des autres, mais vous négligez votre propre énergie. Même avec un cœur droit, on ne peut pas donner durablement en restant épuisé(e).',
    'CROISSANCE__INTENDANCE': 'Votre cœur est droit et vos valeurs sont sincères, mais l\'absence de cadre sur votre temps ou vos finances crée une tension matérielle qui finit par peser sur votre paix.',
    'CROISSANCE__TRANSMISSION': 'Vous avez déjà beaucoup travaillé sur vous-même et votre entourage proche, mais cette maturité reste encore peu partagée au-delà. D\'autres autour de vous pourraient déjà grandir grâce à ce chemin.',

    'DEVELOPPEMENT__VIE': 'Vous avez de vraies compétences et l\'habitude d\'apprendre, mais sans cap choisi, votre énergie risque de servir les priorités des autres plutôt que les vôtres.',
    'DEVELOPPEMENT__CROISSANCE': 'Vos compétences progressent bien, mais votre maîtrise de soi sous pression ou votre entourage de vérité reste en retrait. Des capacités fortes ont besoin d\'un caractère tout aussi solide.',
    'DEVELOPPEMENT__HYGIENE': 'Vous investissez beaucoup dans votre progression et votre efficacité, mais votre rythme physique et votre repos décrochent. Sans récupération, la performance finit toujours par s\'user.',
    'DEVELOPPEMENT__INTENDANCE': 'Vous savez apprendre et produire, mais la gestion suivie de vos finances, de votre temps ou de vos engagements laisse fuir une partie des fruits de vos efforts.',
    'DEVELOPPEMENT__TRANSMISSION': 'Vous avez accumulé de solides compétences et une vraie expérience, mais ce capital reste surtout personnel. Former ou conseiller quelqu\'un multiplierait la valeur de ce que vous savez.',

    'HYGIENE__VIE': 'Vous avez su préserver votre équilibre et votre énergie, ce qui est rare. Il manque maintenant une direction claire pour mettre cette ressource au service d\'un vrai projet de vie.',
    'HYGIENE__CROISSANCE': 'Votre rythme de vie est sain et stable, mais le travail en profondeur sur le caractère ou l\'entourage qui vous remet en question reste encore timide.',
    'HYGIENE__DEVELOPPEMENT': 'Vous avez l\'énergie et la stabilité nécessaires pour durer, mais vos compétences n\'avancent plus au même rythme : vous avez la ressource pour apprendre davantage et viser plus haut.',
    'HYGIENE__INTENDANCE': 'Vous protégez bien votre énergie personnelle, mais la gestion concrète de vos finances, de vos dossiers ou de votre organisation reste flottante et finit par peser sur votre tranquillité.',
    'HYGIENE__TRANSMISSION': 'Vous avez bâti un équilibre personnel solide, mais il profite encore surtout à vous-même. Mettre cette stabilité au service d\'une autre personne lui donnerait une dimension nouvelle.',

    'INTENDANCE__VIE': 'Vous êtes organisé(e) et fiable dans la gestion du quotidien, mais sans direction personnelle claire, le risque est de gérer impeccablement des priorités qui ne sont pas vraiment les vôtres.',
    'INTENDANCE__CROISSANCE': 'Votre gestion pratique tient bien la route, mais le travail intérieur sur vos réactions ou l\'ouverture à des personnes qui vous disent la vérité passe encore au second plan.',
    'INTENDANCE__DEVELOPPEMENT': 'Vous savez tenir un cadre, un budget et des engagements, mais vous n\'investissez plus assez dans le développement de nouvelles compétences pour faire évoluer votre parcours.',
    'INTENDANCE__HYGIENE': 'Vous tenez sérieusement vos responsabilités, votre foyer et vos finances, mais au prix de votre propre fatigue. Protéger votre repos fait aussi partie d\'une bonne gestion.',
    'INTENDANCE__TRANSMISSION': 'Vous gérez bien ce que vous avez entre les mains — temps, finances, engagements. La question maintenant est de passer d\'une bonne gestion pour soi à ce qui porte du fruit pour d\'autres.',

    'TRANSMISSION__VIE': 'Vous donnez beaucoup autour de vous, mais votre propre direction de vie manque de netteté. Prendre le temps de clarifier votre propre cap rendra votre générosité encore plus juste.',
    'TRANSMISSION__CROISSANCE': 'Vous aimez partager et investir dans les autres, mais vous avez besoin de garder un vrai travail personnel sur votre caractère et des pairs capables de vous reprendre avec franchise.',
    'TRANSMISSION__DEVELOPPEMENT': 'Vous êtes généreux(se) dans ce que vous partagez, mais renouveler vos propres compétences est indispensable pour continuer à nourrir les autres sans vous répéter.',
    'TRANSMISSION__HYGIENE': 'Vous donnez beaucoup aux autres et cela se voit, mais vous vous oubliez en route. Si vous ne vous rechargez pas, c\'est votre capacité même à aider qui va finir par s\'éteindre.',
    'TRANSMISSION__INTENDANCE': 'Votre cœur est tourné vers les autres et l\'impact durable, mais le suivi concret de votre temps et de vos finances manque de rigueur pour soutenir cet élan sans stress.'
  };

  function textForScore(plan, score) {
    if (score >= 65) return plan.highText;
    if (score >= 40) return plan.midText;
    return plan.lowText;
  }

  function stepsForScore(plan, score) {
    return score < 45 ? plan.stepsLow : plan.stepsMid;
  }

  function evaluate(answers) {
    if (!Array.isArray(answers) || answers.length !== ITEMS.length) {
      throw new Error('24 réponses sont requises.');
    }

    const sums = { VIE: 0, CROISSANCE: 0, DEVELOPPEMENT: 0, HYGIENE: 0, INTENDANCE: 0, TRANSMISSION: 0 };
    const weakSignals = [];

    ITEMS.forEach(function (item, i) {
      const raw = Math.max(1, Math.min(5, Number(answers[i]) || 3));
      const effective = item.rev ? (6 - raw) : raw;
      sums[item.plan] += effective;
      if (effective <= 2) {
        weakSignals.push({ plan: item.plan, score: effective, text: item.lowSignal });
      }
    });

    const scores = {};
    PLAN_ORDER.forEach(function (k) {
      // 4 items de 1..5 => min 4, max 20
      scores[k] = Math.round(((sums[k] - 4) / 16) * 100);
    });

    const vals = PLAN_ORDER.map(function (k) { return scores[k]; });
    const coherence = Math.round(vals.reduce(function (a, b) { return a + b; }, 0) / vals.length);
    const maxScore = Math.max.apply(null, vals);
    const minScore = Math.min.apply(null, vals);
    const spread = maxScore - minScore;

    const topKeys = PLAN_ORDER.filter(function (k) { return scores[k] === maxScore; });
    const lowKeys = PLAN_ORDER.filter(function (k) { return scores[k] === minScore; });

    let strongestKey;
    let priorityKey;

    if (spread === 0) {
      priorityKey = coherence <= 35 ? 'HYGIENE' : (coherence >= 75 ? 'TRANSMISSION' : 'VIE');
      strongestKey = priorityKey;
    } else {
      strongestKey = topKeys[0];
      priorityKey = lowKeys[0];
    }

    const strongest = PLANS[strongestKey];
    const priority = PLANS[priorityKey];

    // Lecture principale (zéro contradiction)
    let readingMain = '';
    let readingSub = '';

    if (spread < 10) {
      if (coherence >= 75) {
        readingMain = 'Votre socle est déjà solide et remarquablement homogène (' + coherence + ' %). Vous avez posé des bases concrètes dans l\'ensemble de votre vie.';
        readingSub = 'L\'enjeu n\'est plus de tout remettre en ordre, mais de garder ce rythme dans la durée et d\'élargir votre ' + priority.name + ' (' + scores[priorityKey] + ' %).';
      } else if (coherence <= 35) {
        readingMain = 'Tous vos domaines sont sous tension au même moment (' + coherence + ' %). Ce n\'est pas un manque de bonne volonté : à force de répondre aux urgences, plus aucun domaine ne dispose d\'un cadre clair.';
        readingSub = 'Ne cherchez surtout pas à tout réparer en même temps. Commencer par restaurer un minimum de souffle sur votre ' + priority.name + ' (' + scores[priorityKey] + ' %) est la première marche réaliste.';
      } else {
        readingMain = 'Vos six domaines ressortent presque au même niveau autour de ' + coherence + ' % (écart de ' + spread + ' points). C\'est souvent le signe d\'une saison entre deux eaux, ou de réponses prudentes : rien n\'est en crise ouverte, mais aucun domaine n\'a encore pris un vrai cap.';
        readingSub = 'Quand tout est à niveau égal, le meilleur point d\'entrée est votre ' + priority.name + ' : c\'est lui qui donne de la clarté aux cinq autres.';
      }
    } else if (coherence <= 30) {
      readingMain = 'La majorité de vos domaines sont aujourd\'hui sous tension (' + coherence + ' % en moyenne). Ce n\'est pas un manque de volonté : sans cadre simple, les urgences finissent par tout absorber.';
      readingSub = 'Inutile de tout attaquer en même temps : stabiliser d\'abord votre ' + priority.name + ' (' + scores[priorityKey] + ' %) est le pas le plus concret pour reprendre pied.';
    } else if (coherence >= 75 && minScore >= 65) {
      readingMain = 'Votre équilibre d\'ensemble est solide (' + coherence + ' %), avec tous vos domaines au-dessus de 65 %. Vous disposez déjà de bonnes fondations dans votre vie quotidienne.';
      readingSub = 'Votre principale marge de progression se situe sur votre ' + priority.name + ' (' + scores[priorityKey] + ' %) : le renforcer vous permettra de passer d\'une bonne stabilité à un impact plus large.';
    } else if (scores[strongestKey] < 70) {
      readingMain = 'Aucun de vos six domaines n\'est encore pleinement consolidé (votre meilleur score est à ' + scores[strongestKey] + ' % sur votre ' + strongest.name + '), mais un point décroche nettement : votre ' + priority.name + ' (' + scores[priorityKey] + ' %).';
      if (lowKeys.length > 1) {
        const tiedNames = lowKeys.map(function (k) { return PLANS[k].name; }).join(' et ');
        readingSub = 'Deux domaines arrivent ex æquo en bas (' + tiedNames + ' à ' + minScore + ' %). Commencez par celui qui pèse le plus sur votre semaine.';
      } else {
        readingSub = 'Votre score global est de ' + coherence + ' % (' + spread + ' points d\'écart). Commencer par stabiliser votre ' + priority.name + ' est le levier le plus concret pour les 30 prochains jours.';
      }
    } else {
      const pairKey = strongestKey + '__' + priorityKey;
      readingMain = PAIR_DYNAMICS[pairKey] || (
        'Votre ' + strongest.name + ' (' + scores[strongestKey] + ' %) contraste avec votre ' + priority.name + ' (' + scores[priorityKey] + ' %).'
      );
      if (lowKeys.length > 1) {
        const tiedNames = lowKeys.map(function (k) { return PLANS[k].name; }).join(' et ');
        readingSub = 'Votre score global de ' + coherence + ' % masque un écart de ' + spread + ' points. Deux domaines arrivent ex æquo en bas (' + tiedNames + ' à ' + minScore + ' %) : commencez par celui qui pèse le plus sur votre semaine.';
      } else if (spread >= 30) {
        readingSub = 'Votre score global de ' + coherence + ' % masque un contraste marqué (' + spread + ' points d\'écart). Concentrer vos 30 prochains jours sur votre ' + priority.name + ' (' + scores[priorityKey] + ' %) aura plus d\'effet que de disperser vos efforts partout.';
      } else {
        readingSub = 'L\'écart entre vos domaines reste modéré (' + spread + ' points). Renforcer votre ' + priority.name + ' (' + scores[priorityKey] + ' %) est le levier le plus direct pour franchir un palier.';
      }
    }

    // Labels honnêtes (jamais "point fort" < 55%, jamais "freine" >= 65%)
    const showStrongCard = spread > 0;
    const strongLabel = scores[strongestKey] >= 55
      ? 'Ce sur quoi vous pouvez vous appuyer'
      : 'Votre domaine le moins fragilisé';
    const prioLabel = spread === 0
      ? 'Par où commencer'
      : (scores[priorityKey] >= 65
        ? 'Votre principale marge de progression'
        : (lowKeys.length > 1 ? 'Vos priorités actuelles (ex æquo)' : 'Ce qui vous freine le plus en ce moment'));

    const prioDisplayTitle = lowKeys.length > 1 && spread > 0
      ? lowKeys.map(function (k) { return PLANS[k].name; }).join(' & ') + ' (' + minScore + ' %)'
      : priority.name + ' (' + scores[priorityKey] + ' %)';

    // Trier les signaux : d'abord ceux du plan prioritaire, puis par gravité
    weakSignals.sort(function (a, b) {
      const aPrio = a.plan === priorityKey ? 0 : 1;
      const bPrio = b.plan === priorityKey ? 0 : 1;
      if (aPrio !== bPrio) return aPrio - bPrio;
      return a.score - b.score;
    });

    return {
      scores: scores,
      coherence: coherence,
      spread: spread,
      strongestKey: strongestKey,
      priorityKey: priorityKey,
      topKeys: topKeys,
      lowKeys: lowKeys,
      showStrongCard: showStrongCard,
      strongLabel: strongLabel,
      strongTitle: strongest.name + ' (' + scores[strongestKey] + ' %)',
      strongText: textForScore(strongest, scores[strongestKey]),
      prioLabel: prioLabel,
      prioTitle: prioDisplayTitle,
      prioText: textForScore(priority, scores[priorityKey]),
      readingMain: readingMain,
      readingSub: readingSub,
      signals: weakSignals.slice(0, 3).map(function (s) { return s.text; }),
      steps: stepsForScore(priority, scores[priorityKey]),
      stepsMode: scores[priorityKey] < 45 ? 'stabilisation' : 'structuration',
      nextPriorityBadge: priority.name + ' · ' + scores[priorityKey] + ' %'
    };
  }

  const api = {
    PLAN_ORDER: PLAN_ORDER,
    PLANS: PLANS,
    SCALE: SCALE,
    ITEMS: ITEMS,
    PAIR_DYNAMICS: PAIR_DYNAMICS,
    textForScore: textForScore,
    stepsForScore: stepsForScore,
    evaluate: evaluate
  };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  } else {
    root.KiiniBoussole = api;
  }
})(typeof window !== 'undefined' ? window : globalThis);
