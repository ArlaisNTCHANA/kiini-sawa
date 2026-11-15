<?php

namespace App\Personnalite\Psychometrie;

/**
 * Textes d'interprétation : domaines et facettes du Big Five, 16 types de fonctionnement,
 * 4 styles comportementaux. Noms et textes originaux KIINI SAWA.
 *
 * Seuils (score 0-100, ou percentile quand les normes sont disponibles) : bas < 35, élevé > 65.
 */
final class Interpretations
{
    public const LOW = 35;
    public const HIGH = 65;

    public const DOMAINS = [
        'N' => [
            'what' => 'Votre manière de vivre le stress et les émotions difficiles.',
            'high' => 'Vous ressentez fortement les tensions, les inquiétudes et les déceptions. Cette sensibilité vous rend attentif(ve), mais peut vous épuiser : vos rituels de repos et de confiance sont des alliés précieux.',
            'mid' => 'Vous ressentez le stress sans être submergé(e) ; certaines situations vous touchent plus que d\'autres.',
            'low' => 'Vous restez calme et stable face aux difficultés. Veillez simplement à rester à l\'écoute des émotions de votre entourage.',
        ],
        'E' => [
            'what' => 'Votre énergie dans les relations et l\'action.',
            'high' => 'Les relations et l\'action vous donnent de l\'énergie. Vous entraînez naturellement les autres.',
            'mid' => 'Vous alternez avec aisance entre moments sociaux et temps pour vous.',
            'low' => 'Vous puisez votre énergie dans le calme et les relations choisies. Votre réflexion gagne en profondeur ce qu\'elle perd en exposition.',
        ],
        'O' => [
            'what' => 'Votre ouverture aux idées, à l\'imaginaire, à la beauté et au changement.',
            'high' => 'Curieux(se) et créatif(ve), vous aimez explorer les idées et les expériences nouvelles.',
            'mid' => 'Vous savez conjuguer nouveauté et repères éprouvés.',
            'low' => 'Vous préférez le concret, l\'éprouvé et les traditions. Vous êtes un point d\'ancrage pour les autres.',
        ],
        'A' => [
            'what' => 'Votre manière d\'être avec les autres : confiance, droiture, bienveillance.',
            'high' => 'Bienveillant(e) et coopératif(ve), vous créez de la confiance. Apprenez aussi à dire non et à défendre vos positions.',
            'mid' => 'Vous savez être chaleureux(se) tout en défendant vos intérêts.',
            'low' => 'Franc(he) et exigeant(e), vous n\'hésitez pas à confronter. Votre lucidité gagne à s\'accompagner de douceur.',
        ],
        'C' => [
            'what' => 'Votre organisation, votre fiabilité et votre persévérance.',
            'high' => 'Organisé(e), fiable et persévérant(e), vous menez vos projets à terme. Gardez de la souplesse pour l\'imprévu.',
            'mid' => 'Vous êtes fiable sur l\'essentiel tout en gardant de la spontanéité.',
            'low' => 'Spontané(e) et souple, vous gagnerez beaucoup à vous appuyer sur des rituels simples pour concrétiser vos projets.',
        ],
    ];

    /** Facette => [texte si élevé, texte si bas] */
    public const FACETS = [
        'N1' => ['Vous anticipez beaucoup et vous inquiétez facilement.', 'Vous restez serein(e) face à l\'incertitude.'],
        'N2' => ['La frustration monte vite chez vous.', 'Vous gardez votre calme quand les choses vous contrarient.'],
        'N3' => ['Vous traversez souvent des moments de découragement.', 'Vous gardez le moral et une bonne estime de vous.'],
        'N4' => ['Le regard des autres vous intimide.', 'Vous êtes à l\'aise dans les situations sociales délicates.'],
        'N5' => ['Il vous est difficile de résister à vos envies.', 'Vous maîtrisez bien vos envies et vos excès.'],
        'N6' => ['La pression peut vous submerger.', 'Vous restez solide dans les crises.'],
        'E1' => ['Vous êtes chaleureux(se) et créez facilement des liens.', 'Vous êtes réservé(e) et prenez du temps pour vous lier.'],
        'E2' => ['Vous aimez être entouré(e) de nombreuses personnes.', 'Vous préférez les petits groupes ou la solitude.'],
        'E3' => ['Vous prenez naturellement la parole et la direction.', 'Vous laissez volontiers les autres mener.'],
        'E4' => ['Vous avez un rythme soutenu, toujours en action.', 'Vous aimez un rythme posé.'],
        'E5' => ['Vous recherchez l\'intensité et l\'aventure.', 'Vous préférez la sécurité aux sensations fortes.'],
        'E6' => ['Vous rayonnez de joie et d\'enthousiasme.', 'Vous exprimez votre joie avec retenue.'],
        'O1' => ['Votre imagination est riche et vivante.', 'Vous restez concentré(e) sur le réel et le concret.'],
        'O2' => ['Vous êtes sensible à la beauté et à l\'art.', 'L\'art et l\'esthétique vous touchent moins.'],
        'O3' => ['Vous êtes à l\'écoute de vos émotions et de celles des autres.', 'Vous êtes peu porté(e) sur l\'expression des émotions.'],
        'O4' => ['Vous aimez la nouveauté et le changement.', 'Vous appréciez les habitudes et les repères stables.'],
        'O5' => ['Les idées et les débats de fond vous stimulent.', 'Vous préférez le pratique aux idées abstraites.'],
        'A1' => ['Vous faites spontanément confiance.', 'Vous êtes prudent(e) avant d\'accorder votre confiance.'],
        'A2' => ['Vous êtes droit(e) et sincère dans vos relations.', 'Vous pouvez être tenté(e) de jouer stratégique.'],
        'A3' => ['Vous aimez aider et prendre soin des autres.', 'Vous protégez votre temps et votre énergie.'],
        'A4' => ['Vous cherchez l\'accord et évitez l\'affrontement.', 'Vous n\'hésitez pas à affronter ou à riposter.'],
        'A5' => ['Vous êtes humble et discret(ète) sur vos réussites.', 'Vous avez confiance en votre valeur et le montrez.'],
        'A6' => ['La détresse des autres vous touche profondément.', 'Vous abordez les problèmes des autres avec distance.'],
        'C1' => ['Vous vous sentez capable et efficace.', 'Vous doutez parfois de votre capacité à réussir.'],
        'C2' => ['Vous aimez l\'ordre et l\'organisation.', 'Vous vous accommodez bien du désordre.'],
        'C3' => ['Vous tenez parole et respectez vos engagements.', 'Vous prenez des libertés avec les règles.'],
        'C4' => ['Vous visez haut et travaillez dur.', 'Vous ne cherchez pas à en faire plus que nécessaire.'],
        'C5' => ['Vous allez au bout de ce que vous commencez.', 'Vous avez du mal à vous mettre en route.'],
        'C6' => ['Vous réfléchissez avant d\'agir.', 'Vous agissez vite, parfois trop.'],
    ];

    /** Type de fonctionnement (approche de Jung) : 16 types, noms KIINI SAWA. */
    public const TYPES = [
        'ISTJ' => ['Le Pilier', 'Fiable et méthodique, vous tenez vos engagements et faites reposer les projets sur du solide.'],
        'ISFJ' => ['Le Soutien', 'Attentionné(e) et fidèle, vous prenez soin des autres avec constance et discrétion.'],
        'INFJ' => ['Le Discerneur', 'Intuitif(ve) et profond(e), vous percevez le sens caché des choses et accompagnez les autres vers leur vocation.'],
        'INTJ' => ['Le Bâtisseur de vision', 'Stratège indépendant(e), vous concevez des plans à long terme et les menez avec détermination.'],
        'ISTP' => ['Le Technicien', 'Pratique et calme, vous comprenez comment les choses fonctionnent et réglez les problèmes concrets.'],
        'ISFP' => ['L\'Artiste', 'Sensible et authentique, vous exprimez vos valeurs par vos actes et votre créativité.'],
        'INFP' => ['L\'Idéaliste', 'Guidé(e) par vos valeurs, vous cherchez le sens et défendez ce qui est juste avec douceur.'],
        'INTP' => ['Le Penseur', 'Curieux(se) et logique, vous aimez comprendre les systèmes et explorer les idées.'],
        'ESTP' => ['Le Fonceur', 'Énergique et réaliste, vous agissez vite et saisissez les occasions.'],
        'ESFP' => ['L\'Animateur', 'Chaleureux(se) et spontané(e), vous apportez de la vie et de la joie partout où vous passez.'],
        'ENFP' => ['L\'Éveilleur', 'Enthousiaste et inspirant(e), vous voyez le potentiel des personnes et des idées.'],
        'ENTP' => ['Le Défricheur', 'Inventif(ve) et vif(ve), vous aimez remettre en question et ouvrir de nouvelles voies.'],
        'ESTJ' => ['L\'Organisateur', 'Structuré(e) et décidé(e), vous mettez de l\'ordre et faites avancer les choses.'],
        'ESFJ' => ['Le Rassembleur', 'Sociable et dévoué(e), vous créez de la cohésion et veillez au bien-être de chacun.'],
        'ENFJ' => ['Le Mentor', 'Charismatique et bienveillant(e), vous inspirez et faites grandir les autres.'],
        'ENTJ' => ['Le Stratège', 'Leader déterminé(e), vous fixez un cap ambitieux et organisez les moyens de l\'atteindre.'],
    ];

    public const TYPE_AXES = [
        'EI' => ['E' => 'Extraversion : votre énergie vient du monde extérieur', 'I' => 'Introversion : votre énergie vient de votre monde intérieur'],
        'SN' => ['S' => 'Sensation : vous partez du concret et des faits', 'N' => 'Intuition : vous partez des idées et des possibles'],
        'TF' => ['T' => 'Pensée : vous décidez d\'abord par la logique', 'F' => 'Sentiment : vous décidez d\'abord par les valeurs et les personnes'],
        'JP' => ['J' => 'Jugement : vous aimez planifier et conclure', 'P' => 'Perception : vous aimez garder des options ouvertes'],
    ];

    /** Style comportemental (modèle DISC de Marston, 1928), noms KIINI SAWA. */
    public const STYLES = [
        'D' => ['name' => 'Le Meneur', 'essence' => 'Direct, décidé, orienté résultats.', 'strengths' => 'Prend des décisions, relève les défis, fait avancer.', 'watch' => 'Impatience, brusquerie, écoute insuffisante.', 'communicate' => 'Allez à l\'essentiel, proposez des options et laissez-le décider.'],
        'I' => ['name' => 'Le Rayonnant', 'essence' => 'Enthousiaste, sociable, communicant.', 'strengths' => 'Motive, rassemble, crée l\'élan.', 'watch' => 'Dispersion, promesses trop rapides, suivi irrégulier.', 'communicate' => 'Soyez chaleureux(se), laissez-lui de l\'espace pour parler, mettez les accords par écrit.'],
        'S' => ['name' => 'Le Constant', 'essence' => 'Patient, fiable, à l\'écoute.', 'strengths' => 'Stabilise, soutient, persévère, apaise.', 'watch' => 'Résistance au changement, difficulté à dire non.', 'communicate' => 'Prenez le temps, expliquez le pourquoi des changements, rassurez.'],
        'C' => ['name' => 'Le Méthodique', 'essence' => 'Rigoureux, précis, analytique.', 'strengths' => 'Assure la qualité, anticipe les risques, structure.', 'watch' => 'Perfectionnisme, critique, lenteur à décider.', 'communicate' => 'Apportez des faits, des détails et du temps pour réfléchir.'],
    ];

    public static function level(float $score): string
    {
        return $score >= self::HIGH ? 'high' : ($score <= self::LOW ? 'low' : 'mid');
    }
}
