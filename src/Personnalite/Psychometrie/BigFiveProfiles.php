<?php

namespace App\Personnalite\Psychometrie;

/**
 * Contenu détaillé du rapport Big Five (complète Interpretations::DOMAINS), par dimension et par niveau.
 * Contenu original KIINI SAWA.
 */
final class BigFiveProfiles
{
    public const DOMAINS = [
        'E' => ['color' => '#D97706', 'light' => '#FEF3C7', 'dark' => '#78350F',
            'high' => ['label' => 'Énergique et sociable', 'strengths' => ['Crée facilement du lien', 'Entraîne et motive', 'À l\'aise pour prendre la parole'], 'watch' => 'Prendre le temps du silence et de l\'écoute.',
                'daily' => 'Vous rechargez vos batteries avec les autres et dans l\'action : variez les rencontres, mais gardez des temps de pause.', 'spirit' => 'La louange partagée, le témoignage et la vie de groupe vous nourrissent. Le silence et la prière seul vous feront grandir.', 'grow' => 'Prévoir chaque jour dix minutes de silence.'],
            'mid' => ['label' => 'Équilibré entre action et retrait', 'strengths' => ['À l\'aise en groupe comme seul', 'Sait s\'adapter au contexte', 'Écoute et prend la parole'], 'watch' => 'Repérer ce qui vous recharge vraiment.',
                'daily' => 'Vous alternez naturellement les temps sociaux et les temps pour vous : respectez ce rythme.', 'spirit' => 'Vous pouvez être nourri par la communauté comme par la prière personnelle : cultivez les deux.', 'grow' => 'Noter ce qui vous donne de l\'énergie pendant une semaine.'],
            'low' => ['label' => 'Calme et réfléchi', 'strengths' => ['Écoute profonde', 'Réflexion avant d\'agir', 'Relations choisies et fidèles'], 'watch' => 'Oser prendre votre place dans le groupe.',
                'daily' => 'Vous puisez votre énergie dans le calme : protégez ces temps, et choisissez vos engagements sociaux.', 'spirit' => 'La méditation et la prière personnelle vous sont naturelles. La vie communautaire, à petite dose, vous enrichira.', 'grow' => 'Partager une fois par semaine votre avis dans un groupe.'],
        ],
        'O' => ['color' => '#7C3AED', 'light' => '#EDE9FE', 'dark' => '#4C1D95',
            'high' => ['label' => 'Curieux et créatif', 'strengths' => ['Imagination et créativité', 'Ouverture aux idées nouvelles', 'Goût d\'apprendre'], 'watch' => 'Ancrer vos idées dans le concret.',
                'daily' => 'Vous aimez explorer, apprendre et innover : choisissez quelques projets et menez-les à terme.', 'spirit' => 'La méditation créative, l\'art et la réflexion théologique vous nourrissent. Les disciplines simples et régulières vous stabiliseront.', 'grow' => 'Transformer une idée en projet concret ce mois-ci.'],
            'mid' => ['label' => 'Ouvert et ancré', 'strengths' => ['Conjugue nouveauté et repères', 'Esprit pratique', 'Curiosité mesurée'], 'watch' => 'Oser sortir parfois de vos habitudes.',
                'daily' => 'Vous savez accueillir la nouveauté sans perdre vos repères : vous êtes un bon pont entre les générations et les sensibilités.', 'spirit' => 'Vous appréciez à la fois la tradition et le renouveau : c\'est une richesse pour votre communauté.', 'grow' => 'Essayer une nouvelle activité ou une nouvelle lecture.'],
            'low' => ['label' => 'Concret et attaché aux repères', 'strengths' => ['Sens du concret', 'Fidélité aux traditions', 'Stabilité'], 'watch' => 'Accueillir les idées nouvelles avant de les juger.',
                'daily' => 'Vous préférez l\'éprouvé et le pratique : vous êtes un point d\'ancrage. Restez ouvert aux changements utiles.', 'spirit' => 'Les repères, la liturgie et la fidélité aux disciplines vous portent. Laissez aussi Dieu vous surprendre.', 'grow' => 'Écouter jusqu\'au bout une idée nouvelle avant de répondre.'],
        ],
        'A' => ['color' => '#059669', 'light' => '#D1FAE5', 'dark' => '#064E3B',
            'high' => ['label' => 'Bienveillant et coopératif', 'strengths' => ['Crée la confiance', 'Généreux et serviable', 'Apaise les tensions'], 'watch' => 'Apprendre à dire non et à défendre vos positions.',
                'daily' => 'Vous êtes un artisan de paix et de coopération : veillez à ne pas vous faire marcher dessus.', 'spirit' => 'L\'amour du prochain vous est naturel. Le courage de la vérité dite avec amour vous fera grandir (Éphésiens 4.15).', 'grow' => 'Exprimer un désaccord avec douceur cette semaine.'],
            'mid' => ['label' => 'Chaleureux et lucide', 'strengths' => ['Bienveillance et franchise', 'Sait défendre ses intérêts', 'Équilibre relationnel'], 'watch' => 'Doser franchise et douceur selon les personnes.',
                'daily' => 'Vous savez être chaleureux tout en restant lucide : c\'est un bel équilibre.', 'spirit' => 'Vous savez aimer sans naïveté : cultivez la compassion envers ceux qui vous coûtent.', 'grow' => 'Rendre un service discret à quelqu\'un qui vous agace.'],
            'low' => ['label' => 'Franc et exigeant', 'strengths' => ['Franchise', 'Esprit critique', 'Capacité à confronter'], 'watch' => 'Accompagner votre lucidité de douceur.',
                'daily' => 'Vous dites les choses et vous n\'avez pas peur de confronter : votre lucidité est utile si elle reste bienveillante.', 'spirit' => 'Votre amour de la vérité est précieux. La douceur et la patience sont un fruit de l\'Esprit à cultiver (Galates 5.22).', 'grow' => 'Commencer chaque remarque par un point positif.'],
        ],
        'C' => ['color' => '#2563EB', 'light' => '#DBEAFE', 'dark' => '#1E3A8A',
            'high' => ['label' => 'Organisé et persévérant', 'strengths' => ['Fiabilité', 'Organisation', 'Persévérance'], 'watch' => 'Garder de la souplesse face à l\'imprévu.',
                'daily' => 'Vous menez vos projets à terme et l\'on peut compter sur vous : gardez de la place pour le repos et l\'imprévu.', 'spirit' => 'Les disciplines spirituelles régulières vous viennent facilement. Gardez-vous du légalisme : la grâce prime sur la performance.', 'grow' => 'Prévoir un temps libre sans programme chaque semaine.'],
            'mid' => ['label' => 'Fiable et souple', 'strengths' => ['Fiable sur l\'essentiel', 'Spontanéité', 'Adaptabilité'], 'watch' => 'Structurer davantage vos priorités importantes.',
                'daily' => 'Vous êtes fiable sur l\'essentiel tout en restant souple : quelques rituels vous aideront à concrétiser vos priorités.', 'spirit' => 'Un rythme spirituel simple et régulier vous aidera à tenir dans la durée.', 'grow' => 'Choisir un rituel quotidien et le tenir 21 jours.'],
            'low' => ['label' => 'Spontané et flexible', 'strengths' => ['Flexibilité', 'Spontanéité', 'Créativité dans l\'imprévu'], 'watch' => 'S\'appuyer sur des rituels simples pour concrétiser.',
                'daily' => 'Vous préférez la spontanéité au plan : des rituels très simples et un partenaire de redevabilité vous aideront beaucoup.', 'spirit' => 'Vous vivez votre foi avec spontanéité. Un petit rituel quotidien (un verset, une prière) vous donnera de la constance.', 'grow' => 'Planifier chaque soir trois priorités pour le lendemain.'],
        ],
        'N' => ['color' => '#E11D48', 'light' => '#FFE4E6', 'dark' => '#881337',
            'high' => ['label' => 'Très sensible', 'strengths' => ['Grande sensibilité', 'Attention aux risques', 'Empathie pour ceux qui souffrent'], 'watch' => 'Prendre soin de votre repos et de votre paix intérieure.',
                'daily' => 'Vous ressentez fortement les tensions : vos rituels de repos, de prière et de confiance sont des alliés précieux.', 'spirit' => '« Déchargez-vous sur lui de tous vos soucis, car lui-même prend soin de vous » (1 Pierre 5.7). Si l\'anxiété devient envahissante, parlez-en à un professionnel.', 'grow' => 'Écrire chaque soir une inquiétude et la confier à Dieu.'],
            'mid' => ['label' => 'Sensibilité modérée', 'strengths' => ['Sensible sans être submergé', 'Conscience des risques', 'Capacité à rebondir'], 'watch' => 'Repérer les situations qui vous touchent le plus.',
                'daily' => 'Vous ressentez le stress sans être submergé : repérez vos déclencheurs pour mieux vous préparer.', 'spirit' => 'La prière régulière vous aide à garder la paix dans les moments chargés.', 'grow' => 'Identifier vos trois principaux déclencheurs de stress.'],
            'low' => ['label' => 'Calme et stable', 'strengths' => ['Calme face aux difficultés', 'Stabilité émotionnelle', 'Présence rassurante'], 'watch' => 'Rester à l\'écoute des émotions de votre entourage.',
                'daily' => 'Vous restez stable face aux difficultés : votre calme rassure. Veillez à rester attentif à ceux qui vivent les choses plus intensément.', 'spirit' => 'Votre paix est un don pour les autres : offrez-la à ceux qui sont inquiets.', 'grow' => 'Demander à un proche comment il vit une situation difficile.'],
        ],
    ];
}
