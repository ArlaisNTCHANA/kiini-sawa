<?php

namespace App\Personnalite\Psychometrie;

/**
 * Ennéagramme KIINI SAWA : 72 affirmations originales (8 par type) centrées sur les motivations profondes.
 * L'ennéagramme n'a pas de questionnaire libre validé : cet outil est un outil d'introspection.
 * Sa fidélité est mesurée sur les réponses réelles (voir NormService::reliability()).
 * Noms des types propres à KIINI SAWA.
 */
final class EnneagramBank
{
    public const TYPES = [
        1 => ['name' => 'Le Juste', 'motivation' => 'Faire ce qui est bien, avec intégrité.', 'fear' => 'Être dans l\'erreur, être corrompu(e).',
            'gift' => 'Vous élevez le niveau : rigueur, droiture, sens des responsabilités.', 'watch' => 'La critique intérieure, la rigidité, la colère rentrée.',
            'growth' => 'Accueillir la grâce : le bien n\'exige pas la perfection. « Ma grâce te suffit » (2 Co 12:9).'],
        2 => ['name' => 'Le Cœur ouvert', 'motivation' => 'Aimer, être utile et être aimé(e).', 'fear' => 'Ne pas être aimé(e), être inutile.',
            'gift' => 'Vous voyez les besoins avant tout le monde et y répondez avec chaleur.', 'watch' => 'S\'oublier, aider pour être aimé(e), avoir du mal à recevoir.',
            'growth' => 'Servir par liberté, non par besoin, et apprendre à reconnaître vos propres besoins.'],
        3 => ['name' => 'Le Conquérant', 'motivation' => 'Réussir et avoir de la valeur.', 'fear' => 'Être sans valeur, échouer.',
            'gift' => 'Énergie, efficacité, capacité à atteindre des objectifs et à entraîner les autres.', 'watch' => 'Confondre ce que vous faites et ce que vous êtes, l\'image, l\'épuisement.',
            'growth' => 'Votre valeur est donnée, pas gagnée. Choisir l\'authenticité plutôt que la performance.'],
        4 => ['name' => 'L\'Âme singulière', 'motivation' => 'Être authentique et trouver sa place unique.', 'fear' => 'N\'avoir ni identité ni signification.',
            'gift' => 'Profondeur, créativité, sensibilité à la beauté et à la souffrance.', 'watch' => 'La comparaison, le sentiment de manque, la mélancolie.',
            'growth' => 'Découvrir que votre singularité est faite pour servir, et goûter l\'ordinaire.'],
        5 => ['name' => 'Le Chercheur', 'motivation' => 'Comprendre et être compétent(e).', 'fear' => 'Être incapable, envahi(e), démuni(e).',
            'gift' => 'Lucidité, profondeur d\'analyse, indépendance d\'esprit.', 'watch' => 'Le retrait, la rétention de soi, l\'observation sans engagement.',
            'growth' => 'Passer du savoir à l\'engagement : la connaissance grandit quand elle est partagée.'],
        6 => ['name' => 'Le Gardien fidèle', 'motivation' => 'Être en sécurité et soutenu(e).', 'fear' => 'Être sans soutien, sans repère.',
            'gift' => 'Loyauté, sens du collectif, anticipation des risques.', 'watch' => 'Le doute, l\'anxiété, la méfiance ou le besoin d\'appui extérieur.',
            'growth' => 'Ancrer sa sécurité en Dieu et oser décider : « Ne crains rien, car je suis avec toi » (Es 41:10).'],
        7 => ['name' => 'L\'Explorateur joyeux', 'motivation' => 'Être libre, heureux(se), comblé(e).', 'fear' => 'Être privé(e), enfermé(e) dans la douleur.',
            'gift' => 'Enthousiasme, optimisme, créativité, goût des possibles.', 'watch' => 'La dispersion, la fuite de l\'inconfort, l\'inachevé.',
            'growth' => 'Trouver la joie dans la profondeur et la constance, traverser plutôt qu\'éviter.'],
        8 => ['name' => 'Le Protecteur', 'motivation' => 'Être fort(e) et maître de sa vie.', 'fear' => 'Être contrôlé(e), blessé(e), vulnérable.',
            'gift' => 'Courage, franchise, protection des plus faibles, capacité à décider.', 'watch' => 'La domination, l\'intensité qui écrase, la vulnérabilité refusée.',
            'growth' => 'Mettre sa force au service des autres et accepter d\'être vulnérable.'],
        9 => ['name' => 'L\'Artisan de paix', 'motivation' => 'Garder la paix intérieure et l\'harmonie.', 'fear' => 'Le conflit, la séparation.',
            'gift' => 'Écoute, apaisement, capacité à réunir et à voir tous les points de vue.', 'watch' => 'S\'effacer, remettre à plus tard, éviter les sujets qui fâchent.',
            'growth' => 'Votre voix compte : la paix véritable passe parfois par le conflit assumé (Mt 5:9).'],
    ];

    /** id => [type, affirmation] */
    public const ITEMS = [
        1 => [1, 'Je remarque immédiatement ce qui pourrait être amélioré.'],
        2 => [2, 'Je me sens bien quand je suis utile à quelqu\'un.'],
        3 => [3, 'J\'aime être reconnu(e) pour ce que j\'accomplis.'],
        4 => [4, 'Je me sens souvent différent(e) des autres.'],
        5 => [5, 'J\'ai besoin de comprendre en profondeur avant d\'agir.'],
        6 => [6, 'J\'anticipe ce qui pourrait mal tourner.'],
        7 => [7, 'J\'ai toujours de nouveaux projets en tête.'],
        8 => [8, 'Je dis les choses franchement, même si cela dérange.'],
        9 => [9, 'J\'évite autant que possible les conflits.'],
        10 => [1, 'J\'ai une petite voix intérieure qui me corrige souvent.'],
        11 => [2, 'J\'ai du mal à dire non quand on me demande de l\'aide.'],
        12 => [3, 'Je sais adapter mon image pour faire bonne impression.'],
        13 => [4, 'Être authentique compte plus pour moi que plaire.'],
        14 => [5, 'J\'ai besoin de beaucoup de temps seul(e) pour recharger mes batteries.'],
        15 => [6, 'La loyauté est pour moi une valeur essentielle.'],
        16 => [7, 'Je n\'aime pas me sentir limité(e) ou enfermé(e).'],
        17 => [8, 'Je prends naturellement les choses en main dans une crise.'],
        18 => [9, 'Je comprends facilement le point de vue de chacun.'],
        19 => [1, 'Je supporte mal le travail bâclé.'],
        20 => [2, 'Je devine ce dont les autres ont besoin avant qu\'ils le disent.'],
        21 => [3, 'Je me fixe des objectifs ambitieux et je fais tout pour les atteindre.'],
        22 => [4, 'Je ressens les émotions avec une grande intensité.'],
        23 => [5, 'Je préfère observer avant de m\'impliquer.'],
        24 => [6, 'J\'ai besoin de repères clairs et de personnes de confiance.'],
        25 => [7, 'Je retombe vite sur mes pieds en voyant le bon côté des choses.'],
        26 => [8, 'Je déteste me sentir contrôlé(e) ou manipulé(e).'],
        27 => [9, 'Je me laisse parfois porter plutôt que de décider.'],
        28 => [1, 'Je me sens responsable de faire les choses comme il faut.'],
        29 => [2, 'Je me sens blessé(e) quand on ne remarque pas ce que je fais pour les autres.'],
        30 => [3, 'J\'ai du mal à ralentir : il y a toujours quelque chose à accomplir.'],
        31 => [4, 'Il m\'arrive d\'avoir l\'impression qu\'il me manque quelque chose que les autres ont.'],
        32 => [5, 'Je protège mon énergie et mon temps.'],
        33 => [6, 'Je doute souvent de mes décisions.'],
        34 => [7, 'J\'évite de m\'attarder sur ce qui est triste ou pénible.'],
        35 => [8, 'Je protège ceux que j\'aime avec force.'],
        36 => [9, 'Je préfère céder plutôt que de créer une tension.'],
        37 => [1, 'Je me mets en colère intérieurement quand les règles ne sont pas respectées.'],
        38 => [2, 'Les relations passent avant tout le reste pour moi.'],
        39 => [3, 'L\'échec est l\'une de mes plus grandes craintes.'],
        40 => [4, 'J\'ai besoin d\'exprimer ce que je suis de façon personnelle.'],
        41 => [5, 'Les idées et la connaissance me passionnent.'],
        42 => [6, 'Je me méfie de ce qui paraît trop beau pour être vrai.'],
        43 => [7, 'Je m\'ennuie vite quand les choses deviennent routinières.'],
        44 => [8, 'Je respecte les gens qui me tiennent tête.'],
        45 => [9, 'Je recherche le calme et l\'harmonie autour de moi.'],
        46 => [1, 'Je m\'efforce d\'être exemplaire.'],
        47 => [2, 'J\'ai du mal à demander de l\'aide pour moi-même.'],
        48 => [3, 'Je me sens mal à l\'aise quand je ne suis pas performant(e).'],
        49 => [4, 'La beauté et le sens comptent beaucoup dans ma vie.'],
        50 => [5, 'Je me sens envahi(e) quand on attend trop de moi émotionnellement.'],
        51 => [6, 'Je me prépare à l\'avance à différents scénarios.'],
        52 => [7, 'J\'aime garder plusieurs options ouvertes.'],
        53 => [8, 'Je n\'aime pas montrer ma vulnérabilité.'],
        54 => [9, 'J\'ai tendance à remettre à plus tard ce qui est important pour moi.'],
        55 => [1, 'Je trouve difficile de me détendre tant que tout n\'est pas en ordre.'],
        56 => [2, 'Je donne beaucoup de moi-même pour les autres.'],
        57 => [3, 'Je mesure souvent ma valeur à mes résultats.'],
        58 => [4, 'Je me sens parfois incompris(e).'],
        59 => [5, 'J\'aime être compétent(e) et autonome.'],
        60 => [6, 'Le soutien d\'un groupe me rassure.'],
        61 => [7, 'Je cherche à vivre des expériences variées et stimulantes.'],
        62 => [8, 'Je vais droit au but.'],
        63 => [9, 'On me dit souvent que je suis apaisant(e).'],
        64 => [1, 'J\'ai des principes auxquels je ne déroge pas.'],
        65 => [2, 'J\'ai besoin de me sentir aimé(e) pour me sentir bien.'],
        66 => [3, 'Je suis motivé(e) par la compétition et les défis.'],
        67 => [4, 'Je suis attiré(e) par ce qui est profond, intense ou original.'],
        68 => [5, 'J\'analyse les situations de façon détachée.'],
        69 => [6, 'Je teste la fiabilité des gens avant de leur faire confiance.'],
        70 => [7, 'J\'ai du mal à me concentrer longtemps sur une seule chose.'],
        71 => [8, 'Je n\'aime pas dépendre des autres.'],
        72 => [9, 'Je perds parfois de vue mes propres priorités en suivant celles des autres.'],
    ];

    public static function wingsOf(int $type): array
    {
        return [1 === $type ? 9 : $type - 1, 9 === $type ? 1 : $type + 1];
    }
}
