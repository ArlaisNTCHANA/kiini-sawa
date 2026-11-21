<?php

namespace App\Couple\Service;

/**
 * Catalogue des versets quotidiens, défis de couple, parcours de méditation et signaux de connexion
 * pour le Sanctuaire du Couple 360 (RFC-001).
 */
final class CoupleSanctuaryCatalog
{
    /** Versets inspirants en rotation quotidienne pour le foyer. */
    public const DAILY_VERSES = [
        ['reference' => 'Ecclésiaste 4:9-10', 'text' => 'Deux valent mieux qu\'un, parce qu\'ils retirent un bon salaire de leur travail. Car, s\'ils tombent, l\'un relève son compagnon.'],
        ['reference' => '1 Corinthiens 13:4-7', 'text' => 'L\'amour est patient, il est plein de bonté ; il excuse tout, il croit tout, il espère tout, il supporte tout.'],
        ['reference' => 'Josué 24:15', 'text' => 'Moi et ma maison, nous servirons l\'Éternel.'],
        ['reference' => 'Colossiens 3:14', 'text' => 'Par-dessus toutes ces choses, revêtez-vous de l\'amour, qui est le lien de la perfection.'],
        ['reference' => 'Proverbes 24:3-4', 'text' => 'C\'est par la sagesse qu\'une maison s\'élève, et par l\'intelligence qu\'elle s\'affermit.'],
        ['reference' => 'Éphésiens 4:2-3', 'text' => 'En toute humilité et douceur, avec patience, vous supportant les uns les autres avec amour, vous efforçant de conserver l\'unité de l\'Esprit par le lien de la paix.'],
        ['reference' => 'Cantique des Cantiques 8:7', 'text' => 'Les grandes eaux ne peuvent éteindre l\'amour, et les fleuves ne le submergeraient pas.'],
        ['reference' => '1 Pierre 4:8', 'text' => 'Avant tout, ayez les uns pour les autres un ardent amour, car l\'amour couvre une multitude de péchés.'],
        ['reference' => 'Proverbes 3:3-4', 'text' => 'Que la bonté et la fidélité ne t\'abandonnent pas ; lie-les à ton cou, écris-les sur la table de ton cœur.'],
        ['reference' => 'Romains 12:10', 'text' => 'Par amour fraternel, soyez pleins d\'affection les uns pour les autres ; par honneur, usez de prévenances réciproques.'],
    ];

    /** Défis à deux (Le Jardin des Défis). */
    public const CHALLENGES = [
        'ecoute_7j' => [
            'code' => 'ecoute_7j',
            'title' => '7 jours d\'écoute bienveillante',
            'subtitle' => 'Apprendre à écouter le cœur de l\'autre sans interrompre ni juger.',
            'theme' => 'COMMUNICATION',
            'durationDays' => 7,
            'days' => [
                1 => 'Posez une question ouverte sur sa journée et écoutez 5 minutes sans interrompre ni conseiller.',
                2 => 'Reformulez avec vos mots ce que votre conjoint vient de vous confier avant de répondre.',
                3 => 'Rangez tous les écrans pendant le repas et regardez-vous vraiment.',
                4 => 'Demandez : « Qu\'est-ce qui t\'a pesé cette semaine ? » et accueillez la réponse avec douceur.',
                5 => 'Remerciez-le/la pour une qualité que vous admirez en lui/elle et expliquez pourquoi.',
                6 => 'Parlez d\'un rêve commun pendant 15 minutes en laissant chacun s\'exprimer librement.',
                7 => 'Faites le bilan de ces 7 jours : qu\'est-ce qui vous a fait le plus de bien ?',
            ],
        ],
        'gratitude_14j' => [
            'code' => 'gratitude_14j',
            'title' => '14 jours de gratitude conjugale',
            'subtitle' => 'Nourrir la reconnaissance par une parole ou un geste précis chaque jour.',
            'theme' => 'INTIMITE',
            'durationDays' => 14,
            'days' => [
                1 => 'Dites merci pour une petite chose du quotidien que vous tenez habituellement pour acquise.',
                2 => 'Envoyez un message d\'encouragement inattendu en milieu de journée.',
                3 => 'Mentionnez devant Dieu (ou à voix haute) une raison pour laquelle vous êtes heureux de partager sa vie.',
                4 => 'Allégez spontanément une tâche que votre conjoint fait d\'habitude.',
                5 => 'Partagez un beau souvenir de vos débuts ensemble.',
                6 => 'Complimentez un trait de caractère qui vous inspire chez lui/elle.',
                7 => 'Prenez 10 minutes de calme côte à côte pour célébrer la semaine écoulée.',
                8 => 'Écrivez un petit mot glissé dans un sac, une poche ou sur la table.',
                9 => 'Remerciez-le/la pour sa patience dans un domaine précis.',
                10 => 'Préparez ou offrez une attention simple qu\'il/elle apprécie particulièrement.',
                11 => 'Honorez votre conjoint par une parole valorisante.',
                12 => 'Demandez : « Comment puis-je te soutenir concrètement demain ? »',
                13 => 'Partagez trois choses que vous aimez dans le foyer que vous bâtissez.',
                14 => 'Célébrez la fin du défi par un moment privilégié à deux.',
            ],
        ],
        'deconnexion_10j' => [
            'code' => 'deconnexion_10j',
            'title' => '10 soirs sans écran',
            'subtitle' => 'Protéger vos soirées pour retrouver la présence réelle et le repos.',
            'theme' => 'TEMPS',
            'durationDays' => 10,
            'days' => [
                1 => 'Posez les téléphones dans une autre pièce dès 21h00.',
                2 => 'Remplacez le défilement d\'écran par 15 minutes de discussion au calme.',
                3 => 'Lisez ensemble une page d\'un livre ou un passage biblique.',
                4 => 'Faites une courte marche ou prenez une tisane ensemble sans notification.',
                5 => 'Partagez vos 3 gratitudes de la journée avant de dormir.',
                6 => 'Planifiez ensemble une sortie ou un temps libre pour le week-end.',
                7 => 'Écoutez une musique douce et parlez de ce qui vous ressource.',
                8 => 'Priez ou méditez 5 minutes ensemble en clôture de journée.',
                9 => 'Observez comment votre sommeil et votre paix intérieure évoluent.',
                10 => 'Décidez ensemble d\'une règle durable pour protéger vos soirées.',
            ],
        ],
        'priere_7j' => [
            'code' => 'priere_7j',
            'title' => '7 jours de prière à deux',
            'subtitle' => 'Poser un autel simple et fidèle de 5 minutes chaque jour.',
            'theme' => 'SPIRITUALITE',
            'durationDays' => 7,
            'days' => [
                1 => 'Remerciez ensemble pour votre union et confiez cette semaine.',
                2 => 'Priez l\'un pour l\'autre : chacun exprime un besoin précis et l\'autre prie pour lui.',
                3 => 'Priez pour la paix et la sagesse dans vos décisions professionnelles et familiales.',
                4 => 'Déposez ensemble une inquiétude ou un fardeau actuel.',
                5 => 'Priez pour vos proches, vos enfants ou votre famille élargie.',
                6 => 'Priez pour votre vocation commune et ce que vous voulez transmettre.',
                7 => 'Bénissez votre foyer et décidez du rythme de prière que vous souhaitez garder.',
            ],
        ],
    ];

    /** Parcours de méditation guidée à deux (L'Autel). */
    public const MEDITATIONS = [
        'fondations_solides' => [
            'code' => 'fondations_solides',
            'title' => 'Bâtir sur le roc',
            'subtitle' => '5 étapes pour poser des fondations spirituelles et relationnelles solides.',
            'durationMinutes' => 7,
            'steps' => [
                1 => [
                    'title' => 'Choisir l\'alliance chaque jour',
                    'verse' => 'Ecclésiaste 4:9 — Deux valent mieux qu\'un.',
                    'reflection' => 'Un couple solide ne repose pas sur l\'absence de tempêtes, mais sur la décision renouvelée de marcher dans la même direction.',
                    'question' => 'Quel est le signe le plus concret qui te fait sentir que nous sommes une vraie équipe ?',
                ],
                2 => [
                    'title' => 'La grâce avant la perfection',
                    'verse' => 'Colossiens 3:13 — Supportez-vous les uns les autres, et pardonnez-vous réciproquement.',
                    'reflection' => 'Nous sommes deux êtres en croissance. Accueillir les limites de l\'autre avec grâce ouvre l\'espace où le changement devient possible.',
                    'question' => 'Dans quel domaine as-tu le plus besoin de ma bienveillance en ce moment ?',
                ],
                3 => [
                    'title' => 'Protéger l\'intimité du foyer',
                    'verse' => 'Proverbes 4:23 — Garde ton cœur plus que toute autre chose.',
                    'reflection' => 'Nos agendas, nos écrans et les attentes extérieures peuvent peu à peu voler notre temps à deux si nous ne protégeons pas intentionnellement notre sanctuaire.',
                    'question' => 'Quelle petite habitude pourrions-nous instaurer dès cette semaine pour protéger notre temps à deux ?',
                ],
                4 => [
                    'title' => 'Parler vrai avec douceur',
                    'verse' => 'Éphésiens 4:15 — En professant la vérité dans l\'amour, nous croîtrons à tous égards.',
                    'reflection' => 'La vérité sans amour blesse ; l\'amour sans vérité étouffe. La maturité du couple naît quand nous pouvons tout nous dire avec respect.',
                    'question' => 'Y a-t-il un sujet que nous évitons et que nous pourrions aborder calmement ensemble ?',
                ],
                5 => [
                    'title' => 'Bénir au-delà de notre maison',
                    'verse' => 'Josué 24:15 — Moi et ma maison, nous servirons l\'Éternel.',
                    'reflection' => 'Un foyer en paix devient naturellement une source de lumière, d\'hospitalité et d\'encouragement pour ceux qui l\'entourent.',
                    'question' => 'Comment souhaitons-nous que notre couple soit une bénédiction pour notre entourage cette année ?',
                ],
            ],
        ],
        'communication_coeur' => [
            'code' => 'communication_coeur',
            'title' => 'La communication du cœur',
            'subtitle' => '5 étapes pour passer des malentendus à une écoute profonde.',
            'durationMinutes' => 7,
            'steps' => [
                1 => [
                    'title' => 'Écouter pour comprendre, pas pour répondre',
                    'verse' => 'Jacques 1:19 — Que tout homme soit prompt à écouter, lent à parler.',
                    'reflection' => 'Souvent, nous préparons notre réponse pendant que l\'autre parle encore. L\'écoute véritable est un cadeau rare qui apaise immédiatement les tensions.',
                    'question' => 'À quoi vois-tu que je t\'écoute vraiment ?',
                ],
                2 => [
                    'title' => 'Nommer ses besoins sans accuser',
                    'verse' => 'Proverbes 15:1 — Une réponse douce calme la fureur.',
                    'reflection' => 'Dire « j\'ai besoin de… » plutôt que « tu ne fais jamais… » transforme un reproche en invitation à se rapprocher.',
                    'question' => 'Quel besoin simple aimerais-tu m\'exprimer aujourd\'hui sans crainte d\'être mal compris(e) ?',
                ],
                3 => [
                    'title' => 'Comprendre nos différences de rythme',
                    'verse' => 'Romains 12:10 — Par honneur, usez de prévenances réciproques.',
                    'reflection' => 'L\'un a besoin de parler tout de suite, l\'autre de réfléchir en silence. Honorer le rythme de l\'autre évite bien des blessures inutiles.',
                    'question' => 'Lors d\'un désaccord, de quoi as-tu besoin en premier : parler, respirer ou être rassuré(e) ?',
                ],
                4 => [
                    'title' => 'Réparer vite, ne pas laisser la rancœur s\'installer',
                    'verse' => 'Éphésiens 4:26 — Que le soleil ne se couche pas sur votre colère.',
                    'reflection' => 'La réconciliation rapide est un muscle. Plus nous choisissons l\'humilité tôt, moins les murs ont le temps de se construire.',
                    'question' => 'Quel geste ou quelle phrase t\'aide le plus à tourner la page après une tension ?',
                ],
                5 => [
                    'title' => 'Célébrer et encourager à voix haute',
                    'verse' => 'Proverbes 16:24 — Les paroles agréables sont un rayon de miel, douces pour l\'âme.',
                    'reflection' => 'Nos mots façonnent l\'atmosphère de notre maison. Choisir l\'encouragement quotidien fortifie la confiance mutuelle.',
                    'question' => 'Quelle parole d\'encouragement as-tu envie d\'offrir à ton conjoint ce soir ?',
                ],
            ],
        ],
        'intendance_couple' => [
            'code' => 'intendance_couple',
            'title' => 'Finances & Intendance en paix',
            'subtitle' => '4 étapes pour aligner vos décisions financières sur votre vision.',
            'durationMinutes' => 7,
            'steps' => [
                1 => [
                    'title' => 'De « mon argent » à « notre intendance »',
                    'verse' => 'Proverbes 21:5 — Les projets de l\'homme diligent ne mènent qu\'à l\'abondance.',
                    'reflection' => 'L\'argent n\'est pas un tabou ni un terrain de pouvoir : c\'est un outil au service de la vision commune du foyer.',
                    'question' => 'Quelle est ta plus grande inquiétude ou espérance liée à nos finances ?',
                ],
                2 => [
                    'title' => 'Clarté et transparence sans jugement',
                    'verse' => 'Luc 14:28 — Lequel de vous, s\'il veut bâtir une tour, ne s\'assied d\'abord pour calculer la dépense ?',
                    'reflection' => 'Regarder les chiffres ensemble dans le calme remplace le stress par la maîtrise et l\'unité.',
                    'question' => 'Quel rythme de point budget (mensuel, trimestriel) nous mettrait tous les deux en paix ?',
                ],
                3 => [
                    'title' => 'Épargner pour bâtir l\'avenir',
                    'verse' => 'Proverbes 13:11 — Celui qui amasse peu à peu augmente son bien.',
                    'reflection' => 'Chaque petite réserve construite avec discipline protège le foyer et prépare les grands projets.',
                    'question' => 'Quel projet concret voulons-nous financer en priorité cette année ?',
                ],
                4 => [
                    'title' => 'La joie de la générosité choisie',
                    'verse' => '2 Corinthiens 9:7 — Que chacun donne comme il l\'a résolu en son cœur.',
                    'reflection' => 'Décider ensemble de ce que nous donnons protège le cœur de la peur du manque et fait du foyer un canal de bénédiction.',
                    'question' => 'Quelle cause, famille ou œuvre aimerions-nous soutenir ensemble ?',
                ],
            ],
        ],
    ];

    /** Signaux de connexion rapides (« Ping d'amour »). */
    public const PING_PRESETS = [
        'meditation' => [
            'label' => 'Inviter à méditer',
            'title' => 'Invitation au Sanctuaire',
            'body' => '%s t\'invite à prendre 5 minutes pour votre méditation de couple.',
        ],
        'pensee' => [
            'label' => 'Une pensée pour toi',
            'title' => 'Une pensée de %s',
            'body' => '%s pense à toi avec tendresse aujourd\'hui.',
        ],
        'priere' => [
            'label' => 'Je prie pour toi',
            'title' => 'Dans mes prières',
            'body' => '%s est en train de prier pour toi en ce moment.',
        ],
        'merci' => [
            'label' => 'Dire merci',
            'title' => 'Merci mon amour',
            'body' => '%s voulait simplement te dire merci pour qui tu es.',
        ],
        'rendez_vous' => [
            'label' => 'Proposer un moment à deux',
            'title' => 'Moment à deux',
            'body' => '%s aimerait bloquer un moment privilégié à deux très bientôt.',
        ],
    ];

    /** @return array{reference: string, text: string} */
    public static function dailyVerse(?\DateTimeImmutable $date = null): array
    {
        $dayOfYear = (int) ($date ?? new \DateTimeImmutable())->format('z');

        return self::DAILY_VERSES[$dayOfYear % \count(self::DAILY_VERSES)];
    }

    /** @return array<string, mixed>|null */
    public static function challenge(string $code): ?array
    {
        return self::CHALLENGES[$code] ?? null;
    }

    /** @return array<string, mixed>|null */
    public static function meditation(string $code): ?array
    {
        return self::MEDITATIONS[$code] ?? null;
    }
}
