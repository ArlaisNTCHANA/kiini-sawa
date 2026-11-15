<?php

namespace App\Personnalite\Psychometrie;

/**
 * Banque d'items IPIP-NEO-120 (Johnson, 2014), International Personality Item Pool : domaine public.
 * Source : https://ipip.ori.org/30FacetNEO-PI-RItems.htm
 * Johnson, J. A. (2014). Measuring thirty facets of the Five Factor Model with a 120-item public domain
 * inventory: Development of the IPIP-NEO-120. Journal of Research in Personality, 51, 78-89.
 *
 * Adaptation KIINI SAWA :
 * - traduction française de travail (non encore validée par une étude) ;
 * - facette O6 « Libéralisme » retirée (items de vote politique propres aux États-Unis, alpha .63) :
 *   116 items, 29 facettes ; l'Ouverture est calculée sur 5 facettes ;
 * - sens de cotation des items 19 et 20 corrigé (inversés, comme dans la clé d'origine).
 *
 * Format : id => [facette, sens (+1 / -1), texte français, texte anglais d'origine].
 * Fidélité (alpha) de chaque facette dans l'échantillon d'origine (N = 619 150) : FACET_ALPHA.
 */
final class IpipItemBank
{
    public const DOMAINS = [
        'N' => 'Sensibilité émotionnelle',
        'E' => 'Extraversion',
        'O' => 'Ouverture',
        'A' => 'Agréabilité',
        'C' => 'Conscience',
    ];

    public const FACETS = [
        'N1' => 'Anxiété', 'N2' => 'Colère', 'N3' => 'Humeur sombre', 'N4' => 'Gêne sociale', 'N5' => 'Impulsivité', 'N6' => 'Vulnérabilité au stress',
        'E1' => 'Chaleur', 'E2' => 'Grégarité', 'E3' => 'Affirmation de soi', 'E4' => 'Niveau d\'activité', 'E5' => 'Goût du frisson', 'E6' => 'Gaieté',
        'O1' => 'Imagination', 'O2' => 'Sens esthétique', 'O3' => 'Écoute des émotions', 'O4' => 'Goût de la nouveauté', 'O5' => 'Curiosité intellectuelle',
        'A1' => 'Confiance', 'A2' => 'Droiture', 'A3' => 'Altruisme', 'A4' => 'Coopération', 'A5' => 'Modestie', 'A6' => 'Compassion',
        'C1' => 'Efficacité personnelle', 'C2' => 'Sens de l\'ordre', 'C3' => 'Sens du devoir', 'C4' => 'Ambition', 'C5' => 'Autodiscipline', 'C6' => 'Prudence',
    ];

    public const FACET_ALPHA = [
        'N1' => .78, 'N2' => .87, 'N3' => .85, 'N4' => .70, 'N5' => .69, 'N6' => .76,
        'E1' => .81, 'E2' => .79, 'E3' => .85, 'E4' => .69, 'E5' => .73, 'E6' => .79,
        'O1' => .74, 'O2' => .74, 'O3' => .65, 'O4' => .70, 'O5' => .73,
        'A1' => .85, 'A2' => .74, 'A3' => .73, 'A4' => .71, 'A5' => .73, 'A6' => .72,
        'C1' => .77, 'C2' => .83, 'C3' => .67, 'C4' => .79, 'C5' => .71, 'C6' => .88,
    ];

    /** Échelle de réponse (1 à 5). */
    public const SCALE = [
        1 => 'Pas du tout moi',
        2 => 'Plutôt pas moi',
        3 => 'Ni l\'un ni l\'autre',
        4 => 'Plutôt moi',
        5 => 'Tout à fait moi',
    ];

    public const ITEMS = [
        1 => ['N1', 1, 'Je me fais du souci pour beaucoup de choses.', 'Worry about things.'],
        2 => ['N1', 1, 'Je crains le pire.', 'Fear for the worst.'],
        3 => ['N1', 1, 'J\'ai peur de beaucoup de choses.', 'Am afraid of many things.'],
        4 => ['N1', 1, 'Je stresse facilement.', 'Get stressed out easily.'],
        5 => ['N2', 1, 'Je me mets facilement en colère.', 'Get angry easily.'],
        6 => ['N2', 1, 'Je m\'irrite facilement.', 'Get irritated easily.'],
        7 => ['N2', 1, 'Il m\'arrive de perdre mon sang-froid.', 'Lose my temper.'],
        8 => ['N2', -1, 'Je ne suis pas facilement agacé(e).', 'Am not easily annoyed.'],
        9 => ['N3', 1, 'Je me sens souvent triste.', 'Often feel blue.'],
        10 => ['N3', 1, 'Je ne m\'aime pas.', 'Dislike myself.'],
        11 => ['N3', 1, 'J\'ai souvent le moral à zéro.', 'Am often down in the dumps.'],
        12 => ['N3', -1, 'Je me sens bien dans ma peau.', 'Feel comfortable with myself.'],
        13 => ['N4', 1, 'J\'ai du mal à aller vers les autres.', 'Find it difficult to approach others.'],
        14 => ['N4', 1, 'J\'ai peur d\'attirer l\'attention sur moi.', 'Am afraid to draw attention to myself.'],
        15 => ['N4', 1, 'Je ne me sens à l\'aise qu\'avec mes amis.', 'Only feel comfortable with friends.'],
        16 => ['N4', -1, 'Les situations sociales délicates ne me dérangent pas.', 'Am not bothered by difficult social situations.'],
        17 => ['N5', 1, 'Il m\'arrive de faire des excès.', 'Go on binges.'],
        18 => ['N5', -1, 'Je fais rarement des excès.', 'Rarely overindulge.'],
        19 => ['N5', -1, 'Je résiste facilement aux tentations.', 'Easily resist temptations.'],
        20 => ['N5', -1, 'Je sais maîtriser mes envies.', 'Am able to control my cravings.'],
        21 => ['N6', 1, 'Je panique facilement.', 'Panic easily.'],
        22 => ['N6', 1, 'Je me laisse dépasser par les événements.', 'Become overwhelmed by events.'],
        23 => ['N6', 1, 'J\'ai l\'impression de ne pas savoir faire face.', 'Feel that I\'m unable to deal with things.'],
        24 => ['N6', -1, 'Je reste calme sous la pression.', 'Remain calm under pressure.'],
        25 => ['E1', 1, 'Je me fais facilement des amis.', 'Make friends easily.'],
        26 => ['E1', 1, 'Je me sens à l\'aise avec les gens.', 'Feel comfortable around people.'],
        27 => ['E1', -1, 'J\'évite les contacts avec les autres.', 'Avoid contacts with others.'],
        28 => ['E1', -1, 'Je garde les autres à distance.', 'Keep others at a distance.'],
        29 => ['E2', 1, 'J\'adore les grandes fêtes.', 'Love large parties.'],
        30 => ['E2', 1, 'En soirée, je parle avec beaucoup de gens différents.', 'Talk to a lot of different people at parties.'],
        31 => ['E2', -1, 'Je préfère être seul(e).', 'Prefer to be alone.'],
        32 => ['E2', -1, 'J\'évite la foule.', 'Avoid crowds.'],
        33 => ['E3', 1, 'Je prends les choses en main.', 'Take charge.'],
        34 => ['E3', 1, 'J\'essaie de diriger les autres.', 'Try to lead others.'],
        35 => ['E3', 1, 'Je prends le contrôle des situations.', 'Take control of things.'],
        36 => ['E3', -1, 'J\'attends que les autres montrent la voie.', 'Wait for others to lead the way.'],
        37 => ['E4', 1, 'Je suis toujours occupé(e).', 'Am always busy.'],
        38 => ['E4', 1, 'Je suis toujours en mouvement.', 'Am always on the go.'],
        39 => ['E4', 1, 'Je fais beaucoup de choses pendant mon temps libre.', 'Do a lot in my spare time.'],
        40 => ['E4', -1, 'J\'aime prendre les choses tranquillement.', 'Like to take it easy.'],
        41 => ['E5', 1, 'J\'adore les sensations fortes.', 'Love excitement.'],
        42 => ['E5', 1, 'Je recherche l\'aventure.', 'Seek adventure.'],
        43 => ['E5', 1, 'J\'aime prendre des risques inconsidérés.', 'Enjoy being reckless.'],
        44 => ['E5', 1, 'Il m\'arrive de faire des folies.', 'Act wild and crazy.'],
        45 => ['E6', 1, 'Je rayonne de joie.', 'Radiate joy.'],
        46 => ['E6', 1, 'Je m\'amuse beaucoup.', 'Have a lot of fun.'],
        47 => ['E6', 1, 'J\'aime la vie.', 'Love life.'],
        48 => ['E6', 1, 'Je vois le bon côté des choses.', 'Look at the bright side of life.'],
        49 => ['O1', 1, 'J\'ai une imagination débordante.', 'Have a vivid imagination.'],
        50 => ['O1', 1, 'J\'aime me laisser emporter par mon imagination.', 'Enjoy wild flights of fantasy.'],
        51 => ['O1', 1, 'J\'aime rêvasser.', 'Love to daydream.'],
        52 => ['O1', 1, 'J\'aime me perdre dans mes pensées.', 'Like to get lost in thought.'],
        53 => ['O2', 1, 'Je crois en l\'importance de l\'art.', 'Believe in the importance of art.'],
        54 => ['O2', 1, 'Je vois de la beauté là où d\'autres ne la remarquent pas.', 'See beauty in things that others might not notice.'],
        55 => ['O2', -1, 'Je n\'aime pas la poésie.', 'Do not like poetry.'],
        56 => ['O2', -1, 'Je n\'aime pas visiter les musées d\'art.', 'Do not enjoy going to art museums.'],
        57 => ['O3', 1, 'Je ressens mes émotions intensément.', 'Experience my emotions intensely.'],
        58 => ['O3', 1, 'Je ressens les émotions des autres.', 'Feel others\' emotions.'],
        59 => ['O3', -1, 'Je remarque rarement mes réactions émotionnelles.', 'Rarely notice my emotional reactions.'],
        60 => ['O3', -1, 'Je ne comprends pas les gens qui se laissent emporter par leurs émotions.', 'Don\'t understand people who get emotional.'],
        61 => ['O4', 1, 'Je préfère la variété à la routine.', 'Prefer variety to routine.'],
        62 => ['O4', -1, 'Je préfère m\'en tenir à ce que je connais.', 'Prefer to stick with things that I know.'],
        63 => ['O4', -1, 'Je n\'aime pas le changement.', 'Dislike changes.'],
        64 => ['O4', -1, 'Je suis attaché(e) aux façons de faire habituelles.', 'Am attached to conventional ways.'],
        65 => ['O5', 1, 'J\'aime lire des textes exigeants.', 'Love to read challenging material.'],
        66 => ['O5', -1, 'J\'évite les discussions philosophiques.', 'Avoid philosophical discussions.'],
        67 => ['O5', -1, 'J\'ai du mal à comprendre les idées abstraites.', 'Have difficulty understanding abstract ideas.'],
        68 => ['O5', -1, 'Les discussions théoriques ne m\'intéressent pas.', 'Am not interested in theoretical discussions.'],
        73 => ['A1', 1, 'Je fais confiance aux autres.', 'Trust others.'],
        74 => ['A1', 1, 'Je crois que les autres ont de bonnes intentions.', 'Believe that others have good intentions.'],
        75 => ['A1', 1, 'Je crois ce que les gens disent.', 'Trust what people say.'],
        76 => ['A1', -1, 'Je me méfie des gens.', 'Distrust people.'],
        77 => ['A2', -1, 'Je me sers des autres pour arriver à mes fins.', 'Use others for my own ends.'],
        78 => ['A2', -1, 'Je triche pour réussir.', 'Cheat to get ahead.'],
        79 => ['A2', -1, 'Je profite des autres.', 'Take advantage of others.'],
        80 => ['A2', -1, 'Je mets des bâtons dans les roues des autres.', 'Obstruct others\' plans.'],
        81 => ['A3', 1, 'Je me soucie des autres.', 'Am concerned about others.'],
        82 => ['A3', 1, 'J\'adore aider les autres.', 'Love to help others.'],
        83 => ['A3', -1, 'Les sentiments des autres me laissent indifférent(e).', 'Am indifferent to the feelings of others.'],
        84 => ['A3', -1, 'Je ne prends pas de temps pour les autres.', 'Take no time for others.'],
        85 => ['A4', -1, 'J\'aime bien une bonne dispute.', 'Love a good fight.'],
        86 => ['A4', -1, 'Il m\'arrive de crier sur les gens.', 'Yell at people.'],
        87 => ['A4', -1, 'Il m\'arrive d\'insulter les gens.', 'Insult people.'],
        88 => ['A4', -1, 'Je cherche à me venger.', 'Get back at others.'],
        89 => ['A5', -1, 'Je crois que je suis meilleur(e) que les autres.', 'Believe that I am better than others.'],
        90 => ['A5', -1, 'Je me tiens en haute estime.', 'Think highly of myself.'],
        91 => ['A5', -1, 'J\'ai une haute opinion de moi-même.', 'Have a high opinion of myself.'],
        92 => ['A5', -1, 'Je me vante de mes qualités.', 'Boast about my virtues.'],
        93 => ['A6', 1, 'Je suis touché(e) par le sort des sans-abri.', 'Sympathize with the homeless.'],
        94 => ['A6', 1, 'J\'éprouve de la compassion pour ceux qui sont moins bien lotis que moi.', 'Feel sympathy for those who are worse off than myself.'],
        95 => ['A6', -1, 'Les problèmes des autres ne m\'intéressent pas.', 'Am not interested in other people\'s problems.'],
        96 => ['A6', -1, 'J\'essaie de ne pas penser aux plus démunis.', 'Try not to think about the needy.'],
        97 => ['C1', 1, 'Je mène mes tâches à bien.', 'Complete tasks successfully.'],
        98 => ['C1', 1, 'J\'excelle dans ce que je fais.', 'Excel in what I do.'],
        99 => ['C1', 1, 'Je m\'acquitte de mes tâches avec aisance.', 'Handle tasks smoothly.'],
        100 => ['C1', 1, 'Je sais comment faire avancer les choses.', 'Know how to get things done.'],
        101 => ['C2', 1, 'J\'aime ranger.', 'Like to tidy up.'],
        102 => ['C2', -1, 'J\'oublie souvent de remettre les choses à leur place.', 'Often forget to put things back in their proper place.'],
        103 => ['C2', -1, 'Je laisse du désordre dans ma chambre.', 'Leave a mess in my room.'],
        104 => ['C2', -1, 'Je laisse traîner mes affaires.', 'Leave my belongings around.'],
        105 => ['C3', 1, 'Je tiens mes promesses.', 'Keep my promises.'],
        106 => ['C3', 1, 'Je dis la vérité.', 'Tell the truth.'],
        107 => ['C3', -1, 'J\'enfreins les règles.', 'Break rules.'],
        108 => ['C3', -1, 'Je ne tiens pas mes promesses.', 'Break my promises.'],
        109 => ['C4', 1, 'J\'en fais plus que ce qu\'on attend de moi.', 'Do more than what\'s expected of me.'],
        110 => ['C4', 1, 'Je travaille dur.', 'Work hard.'],
        111 => ['C4', -1, 'Je consacre peu de temps et d\'efforts à mon travail.', 'Put little time and effort into my work.'],
        112 => ['C4', -1, 'J\'en fais juste assez pour m\'en sortir.', 'Do just enough work to get by.'],
        113 => ['C5', 1, 'Je suis toujours prêt(e).', 'Am always prepared.'],
        114 => ['C5', 1, 'Je mène mes projets jusqu\'au bout.', 'Carry out my plans.'],
        115 => ['C5', -1, 'Je perds mon temps.', 'Waste my time.'],
        116 => ['C5', -1, 'J\'ai du mal à me mettre au travail.', 'Have difficulty starting tasks.'],
        117 => ['C6', -1, 'Je me lance sans réfléchir.', 'Jump into things without thinking.'],
        118 => ['C6', -1, 'Je prends des décisions hâtives.', 'Make rash decisions.'],
        119 => ['C6', -1, 'Je me précipite.', 'Rush into things.'],
        120 => ['C6', -1, 'J\'agis sans réfléchir.', 'Act without thinking.'],
    ];

    /** @return int[] identifiants des items d'une facette */
    public static function itemsOfFacet(string $facet): array
    {
        return array_keys(array_filter(self::ITEMS, fn ($i) => $i[0] === $facet));
    }

    public static function facetsOfDomain(string $domain): array
    {
        return array_values(array_filter(array_keys(self::FACETS), fn ($f) => $f[0] === $domain));
    }
}
