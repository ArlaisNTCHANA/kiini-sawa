<?php

namespace App\Personnalite\Service;

/**
 * Contenu des rapports d'auto-évaluation « Équilibre de Vie » (8 domaines) et « Hygiène de Vie » (6 axes).
 * Niveaux sur 10 : bas (< 5), moyen (5 à 7), haut (≥ 7,5). Contenu original KIINI SAWA.
 */
final class SelfAssessmentProfiles
{
    public const EQUILIBRE = [
        'sante' => ['label' => 'Santé et vitalité', 'color' => '#E11D48', 'light' => '#FFE4E6', 'dark' => '#881337', 'plan' => 'Plan d\'Hygiène de vie', 'read' => '1 Corinthiens 6.19–20 ; 3 Jean 2',
            'what' => 'Votre corps, votre énergie, votre sommeil, votre alimentation.',
            'low' => ['Votre corps vous envoie peut-être des signaux de fatigue. Ce n\'est pas un luxe d\'en prendre soin : c\'est la base de tout le reste.', ['Consulter un médecin si la fatigue dure', 'Fixer une heure de coucher régulière']],
            'mid' => ['Votre santé tient, mais certaines habitudes pourraient vous donner plus d\'énergie.', ['Marcher 30 minutes trois fois par semaine', 'Boire de l\'eau à chaque repas']],
            'high' => ['Votre vitalité est un atout : protégez-la par des habitudes régulières.', ['Garder votre rythme de sommeil', 'Partager une activité physique avec un proche']],
            'question' => 'Qu\'est-ce que mon corps essaie de me dire en ce moment ?'],
        'famille' => ['label' => 'Famille', 'color' => '#DB2777', 'light' => '#FCE7F3', 'dark' => '#831843', 'plan' => 'Plan de Vie (Être et Donner)', 'read' => 'Psaume 127.1 ; Colossiens 3.12–14',
            'what' => 'Vos relations avec votre conjoint, vos enfants, vos parents.',
            'low' => ['Vos relations familiales traversent peut-être une période difficile. Un petit pas sincère vaut mieux qu\'une grande résolution.', ['Prévoir un moment de qualité sans écran cette semaine', 'Demander de l\'aide à un conseiller si la tension est forte']],
            'mid' => ['Vos relations familiales sont présentes, mais elles méritent peut-être plus de temps et d\'attention.', ['Instaurer un repas familial hebdomadaire', 'Demander à chacun : « De quoi as-tu besoin ? »']],
            'high' => ['Votre vie familiale est une source de joie : continuez à l\'entretenir.', ['Célébrer un moment familial', 'Transmettre une valeur ou une histoire à vos enfants']],
            'question' => 'Qui, dans ma famille, a besoin de plus de ma présence ?'],
        'finances' => ['label' => 'Finances', 'color' => '#059669', 'light' => '#D1FAE5', 'dark' => '#064E3B', 'plan' => 'Plan de Développement personnel', 'read' => 'Proverbes 21.5 ; Luc 16.10–11',
            'what' => 'Votre sérénité financière, votre budget, votre épargne, votre générosité.',
            'low' => ['L\'argent est sans doute une source d\'inquiétude. Voir clair est le premier pas vers la paix.', ['Noter toutes vos dépenses pendant un mois', 'Demander conseil à une personne de confiance compétente']],
            'mid' => ['Votre situation est gérable, mais un plan plus clair vous apporterait plus de sérénité.', ['Établir un budget mensuel simple', 'Mettre de côté un petit montant chaque mois']],
            'high' => ['Votre gestion est saine : c\'est une liberté, et une occasion de générosité.', ['Fixer un objectif d\'épargne à long terme', 'Choisir une œuvre à soutenir']],
            'question' => 'Mon argent sert-il mes priorités, ou les dicte-t-il ?'],
        'carriere' => ['label' => 'Travail et vocation', 'color' => '#2563EB', 'light' => '#DBEAFE', 'dark' => '#1E3A8A', 'plan' => 'Plan de Développement personnel', 'read' => 'Colossiens 3.23 ; Éphésiens 2.10',
            'what' => 'Votre travail, vos études, votre contribution, votre sens de la vocation.',
            'low' => ['Votre travail vous pèse peut-être, ou ne fait pas sens. Avant toute décision, prenez le temps de comprendre ce qui manque.', ['Noter ce qui vous épuise et ce qui vous anime au travail', 'En parler à un mentor ou à un proche de confiance']],
            'mid' => ['Votre travail vous convient en partie ; certains ajustements pourraient lui redonner du sens.', ['Identifier une compétence à développer', 'Demander un retour à votre responsable']],
            'high' => ['Votre travail vous épanouit : c\'est une bénédiction à partager.', ['Accompagner un plus jeune dans son parcours', 'Fixer un nouvel objectif d\'apprentissage']],
            'question' => 'En quoi mon travail est-il une manière de servir ?'],
        'spiritualite' => ['label' => 'Vie spirituelle', 'color' => '#7C3AED', 'light' => '#EDE9FE', 'dark' => '#4C1D95', 'plan' => 'Plan de Croissance', 'read' => 'Matthieu 6.33 ; Jean 15.5',
            'what' => 'Votre relation avec Dieu, la prière, la Parole, la communauté.',
            'low' => ['Votre vie spirituelle semble en sommeil. Dieu ne demande pas la performance : un pas simple suffit pour revenir.', ['Lire un psaume chaque matin pendant une semaine', 'Parler de votre foi avec une personne de confiance']],
            'mid' => ['Votre vie spirituelle est présente mais irrégulière ; un rythme simple vous aiderait.', ['Choisir un moment fixe de prière chaque jour', 'Rejoindre un petit groupe ou un partenaire de prière']],
            'high' => ['Votre vie spirituelle est vivante : laissez-la rayonner vers les autres.', ['Accompagner quelqu\'un dans sa foi', 'Approfondir un livre de la Bible']],
            'question' => 'Où ai-je rencontré Dieu cette semaine ?'],
        'social' => ['label' => 'Amitiés et vie sociale', 'color' => '#D97706', 'light' => '#FEF3C7', 'dark' => '#78350F', 'plan' => 'Partenaires et Plan de Transmission', 'read' => 'Proverbes 17.17 ; Ecclésiaste 4.9–10',
            'what' => 'Vos amitiés, votre réseau, votre engagement dans la communauté.',
            'low' => ['Vous vous sentez peut-être isolé. Une seule relation de confiance peut tout changer.', ['Reprendre contact avec un ami cette semaine', 'Rejoindre un groupe ou une activité régulière']],
            'mid' => ['Vous avez des relations, mais elles mériteraient peut-être plus de profondeur.', ['Proposer un moment à un ami proche', 'Inviter quelqu\'un chez vous']],
            'high' => ['Vos relations sont riches : elles sont une force à cultiver et à partager.', ['Présenter deux amis qui pourraient s\'entraider', 'Être attentif à une personne isolée de votre entourage']],
            'question' => 'Qui pourrait compter sur moi, et sur qui puis-je compter ?'],
        'personnel' => ['label' => 'Développement personnel', 'color' => '#0891B2', 'light' => '#CFFAFE', 'dark' => '#164E63', 'plan' => 'Plan de Croissance (caractère) et Plan de Développement personnel (apprentissages)', 'read' => 'Romains 12.2 ; Philippiens 3.13–14',
            'what' => 'Votre croissance, vos apprentissages, votre connaissance de vous-même.',
            'low' => ['Vous avez peut-être l\'impression de stagner. Grandir commence par un petit apprentissage régulier.', ['Lire 10 pages par jour', 'Choisir un seul objectif de croissance pour ce mois']],
            'mid' => ['Vous progressez, mais un objectif clair vous aiderait à aller plus loin.', ['Fixer un objectif de croissance à trois mois', 'Faire le point chaque semaine dans votre journal']],
            'high' => ['Vous grandissez : transmettez ce que vous apprenez.', ['Partager un apprentissage avec quelqu\'un', 'Vous lancer un nouveau défi']],
            'question' => 'Qu\'est-ce que j\'apprends en ce moment sur moi-même ?'],
        'loisirs' => ['label' => 'Repos et loisirs', 'color' => '#65A30D', 'light' => '#ECFCCB', 'dark' => '#365314', 'plan' => 'Plan d\'Hygiène de vie', 'read' => 'Marc 6.31 ; Exode 20.8–10',
            'what' => 'Votre repos, vos loisirs, vos temps de joie et de sabbat.',
            'low' => ['Vous manquez sans doute de repos. Le repos n\'est pas une récompense, c\'est un commandement et une nécessité.', ['Bloquer une demi-journée sans travail chaque semaine', 'Renouer avec une activité qui vous fait du bien']],
            'mid' => ['Vous vous reposez, mais pas toujours assez ni de la bonne manière.', ['Distinguer repos (ressourçant) et distraction (écrans)', 'Planifier un loisir dans votre agenda']],
            'high' => ['Vous savez vous ressourcer : c\'est une sagesse précieuse.', ['Partager un loisir avec un proche', 'Protéger votre jour de repos']],
            'question' => 'Qu\'est-ce qui me repose vraiment ?'],
    ];

    public const HYGIENE = [
        'physique' => ['label' => 'Physique', 'color' => '#E11D48', 'light' => '#FFE4E6', 'dark' => '#881337', 'read' => '1 Corinthiens 6.19–20',
            'what' => 'Sommeil, alimentation, hydratation, activité physique.',
            'low' => 'Votre corps manque sans doute de soins de base. Commencez par le sommeil : tout le reste en dépend.',
            'mid' => 'Vos bases physiques tiennent, mais une ou deux habitudes feraient une vraie différence.',
            'high' => 'Vos habitudes physiques sont solides : elles vous donnent de l\'énergie pour le reste.',
            'habits' => ['Se coucher à heure fixe, sans écran 30 minutes avant', 'Boire un grand verre d\'eau au réveil', 'Marcher 20 minutes chaque jour']],
        'mental' => ['label' => 'Mental', 'color' => '#7C3AED', 'light' => '#EDE9FE', 'dark' => '#4C1D95', 'read' => 'Philippiens 4.6–8',
            'what' => 'Repos de l\'esprit, gestion du stress, calme, clarté.',
            'low' => 'Votre esprit semble surchargé. Des pauses régulières et un temps de silence peuvent vous aider ; si l\'anxiété est forte, parlez-en à un professionnel.',
            'mid' => 'Vous gérez le stress, mais votre esprit a besoin de vraies pauses.',
            'high' => 'Votre esprit est clair et apaisé : protégez ces conditions.',
            'habits' => ['Faire trois pauses de 5 minutes sans écran par jour', 'Écrire chaque soir ce qui vous préoccupe et le confier à Dieu', 'Couper les notifications le soir']],
        'relationnel' => ['label' => 'Relationnel', 'color' => '#DB2777', 'light' => '#FCE7F3', 'dark' => '#831843', 'read' => 'Ecclésiaste 4.9–10',
            'what' => 'Écoute, partage, expression de vos besoins, soutien.',
            'low' => 'Vous manquez peut-être de soutien ou d\'espace pour dire ce que vous vivez. Une relation de confiance est un vrai remède.',
            'mid' => 'Vos relations vous soutiennent, mais vous pourriez exprimer davantage vos besoins.',
            'high' => 'Vous êtes bien entouré et vous savez partager : c\'est une richesse.',
            'habits' => ['Appeler un proche chaque semaine', 'Exprimer un besoin clairement une fois par semaine', 'Écouter sans interrompre lors de la prochaine conversation']],
        'spirituel' => ['label' => 'Spirituel', 'color' => '#0891B2', 'light' => '#CFFAFE', 'dark' => '#164E63', 'read' => 'Psaume 23 ; Matthieu 11.28–29',
            'what' => 'Sens, alignement avec vos valeurs, paix intérieure, pratique.',
            'low' => 'Votre vie intérieure semble sèche ou dispersée. Un rendez-vous quotidien très simple avec Dieu peut tout changer.',
            'mid' => 'Votre vie spirituelle est présente ; plus de régularité vous apporterait plus de paix.',
            'high' => 'Votre vie intérieure est une source : elle nourrit tout le reste.',
            'habits' => ['Commencer la journée par un verset et une prière', 'Relire chaque soir la journée avec gratitude', 'Garder un temps de sabbat chaque semaine']],
        'ressources' => ['label' => 'Ressources', 'color' => '#D97706', 'light' => '#FEF3C7', 'dark' => '#78350F', 'read' => 'Psaume 90.12 ; Éphésiens 5.15–16',
            'what' => 'Gestion du temps, projets personnels, limites d\'énergie, récupération.',
            'low' => 'Votre temps et votre énergie semblent débordés. Apprendre à poser des limites est une priorité.',
            'mid' => 'Vous gérez vos ressources, mais vos limites sont parfois dépassées.',
            'high' => 'Vous gérez bien votre temps et votre énergie : une vraie maîtrise.',
            'habits' => ['Planifier chaque soir trois priorités', 'Dire non à une sollicitation de trop chaque semaine', 'Prévoir une vraie récupération après un effort intense']],
        'environnement' => ['label' => 'Environnement', 'color' => '#65A30D', 'light' => '#ECFCCB', 'dark' => '#365314', 'read' => 'Genèse 2.15',
            'what' => 'Ordre de votre espace, lumière, bruit, contact avec la nature.',
            'low' => 'Votre environnement vous fatigue peut-être sans que vous le remarquiez. De petits changements ont un grand effet.',
            'mid' => 'Votre environnement est correct ; quelques ajustements le rendraient plus ressourçant.',
            'high' => 'Votre environnement vous soutient : c\'est un appui discret mais réel.',
            'habits' => ['Ranger votre espace de travail chaque soir en 5 minutes', 'Ouvrir les volets et travailler près de la lumière', 'Passer 20 minutes dehors chaque jour']],
    ];

    public static function level(float $score): string
    {
        return $score < 5 ? 'low' : ($score < 7.5 ? 'mid' : 'high');
    }

    public static function levelLabel(string $level): string
    {
        return ['low' => 'À soigner en priorité', 'mid' => 'À consolider', 'high' => 'Point d\'appui'][$level];
    }
}
