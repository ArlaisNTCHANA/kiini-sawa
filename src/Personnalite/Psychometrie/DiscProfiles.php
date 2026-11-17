<?php

namespace App\Personnalite\Psychometrie;

/**
 * Contenu du rapport DISC, d'après la formation « DISC — Mieux se connaître, mieux communiquer » d'Arlais NTCHANA
 * (2026), reprise avec l'accord de son auteur et adaptée en restitution individuelle.
 */
final class DiscProfiles
{
    public const PROFILES = [
        'D' => [
            'more' => 'décider plus vite et vous affirmer davantage', 'name' => 'Dominant', 'color' => '#DC2626', 'colorName' => 'rouge', 'question' => 'Quoi ? Quel résultat ?',
            'axis' => 'Rythme rapide · orienté tâche',
            'essence' => 'Vous allez droit au but, vous cherchez le résultat et vous décidez vite.',
            'strengths' => ['Décide vite, tranche', 'Aime les défis', 'Orienté objectifs'],
            'watch' => ['Peut paraître brusque', 'Écoute peu', 'Impatient'],
            'fear' => 'Perdre le contrôle',
            'needs' => ['De l\'autonomie', 'Des objectifs clairs', 'Des résultats visibles'],
            'pressure' => ['adapted' => 'Vous canalisez votre énergie et déléguez.', 'natural' => 'Vous voulez trancher vite.', 'tension' => 'Vous devenez autoritaire, décidez seul et coupez court au débat.'],
            'cues' => ['speech' => 'Direct, bref', 'gesture' => 'Ferme, ample', 'mail' => 'Épurés, courts', 'stress' => 'Devient autoritaire'],
            'disagree' => 'Votre désaccord s\'exprime aussitôt : ce n\'est pas une attaque, c\'est une façon de tester le raisonnement. Dites-le aux autres.',
            'grow' => 'Prendre deux minutes d\'écoute avant de trancher ; nommer le comportement observé, jamais la personne.',
        ],
        'I' => [
            'more' => 'communiquer, convaincre et entraîner davantage', 'name' => 'Influent', 'color' => '#EAB308', 'colorName' => 'jaune', 'question' => 'Qui ? Avec qui ?',
            'axis' => 'Rythme rapide · orienté relation',
            'essence' => 'Vous communiquez, vous entraînez les autres et vous voyez le bon côté des choses.',
            'strengths' => ['Enthousiaste, fédérateur', 'Bon communicant', 'Créatif'],
            'watch' => ['Dispersé, peu structuré', 'Suit mal les détails', 'Optimisme excessif'],
            'fear' => 'Être rejeté ou ignoré',
            'needs' => ['De la reconnaissance', 'Des échanges', 'De la liberté d\'expression'],
            'pressure' => ['adapted' => 'Vous structurez et documentez.', 'natural' => 'Vous improvisez et embarquez les autres.', 'tension' => 'Vous vous dispersez, promettez trop et perdez le fil de vos engagements.'],
            'cues' => ['speech' => 'Rapide, animé', 'gesture' => 'Expressive', 'mail' => 'Conviviaux, longs', 'stress' => 'Devient brouillon'],
            'disagree' => 'Vous dites oui facilement : reformulez vos engagements par écrit pour éviter les malentendus.',
            'grow' => 'Annoncer le résultat avant le récit ; désigner qui suit chaque décision.',
        ],
        'S' => [
            'more' => 'être plus patient, disponible et constant', 'name' => 'Stable', 'color' => '#16A34A', 'colorName' => 'vert', 'question' => 'Comment ? Depuis quand ?',
            'axis' => 'Rythme posé · orienté relation',
            'essence' => 'Vous écoutez, vous soutenez et vous cherchez l\'harmonie, avec fidélité et constance.',
            'strengths' => ['Fiable et constant', 'Excellente écoute', 'Esprit d\'équipe'],
            'watch' => ['Difficulté à dire non', 'Résiste au changement', 'Évite le conflit'],
            'fear' => 'Le changement brutal',
            'needs' => ['De la sécurité', 'Du temps d\'adaptation', 'Un climat serein'],
            'pressure' => ['adapted' => 'Vous restez disponible pour les autres.', 'natural' => 'Vous avez besoin de temps.', 'tension' => 'Vous vous refermez, acceptez tout, puis décrochez brutalement.'],
            'cues' => ['speech' => 'Posé, calme', 'gesture' => 'Discrète', 'mail' => 'Personnels, chaleureux', 'stress' => 'Se referme'],
            'disagree' => 'Vous dites rarement non de front : votre désaccord passe par le silence ou le retard. Exprimez-le explicitement, tôt.',
            'grow' => 'Annoncer un délai clair plutôt que rester vague ; nommer un désaccord à froid.',
        ],
        'C' => [
            'more' => 'être plus rigoureux, précis et prudent', 'name' => 'Conforme', 'color' => '#2563EB', 'colorName' => 'bleu', 'question' => 'Pourquoi ? Sur quelle base ?',
            'axis' => 'Rythme posé · orienté tâche',
            'essence' => 'Vous analysez, vous vérifiez et vous cherchez la justesse, avec rigueur.',
            'strengths' => ['Rigoureux, précis', 'Esprit d\'analyse', 'Qualité du travail'],
            'watch' => ['Perfectionniste', 'Lent à décider', 'Critique, distant'],
            'fear' => 'L\'erreur et la critique',
            'needs' => ['Des données fiables', 'Des règles claires', 'Du temps pour vérifier'],
            'pressure' => ['adapted' => 'Vous tranchez et acceptez l\'imperfection.', 'natural' => 'Vous voulez vérifier.', 'tension' => 'Vous multipliez les contrôles et bloquez la décision.'],
            'cues' => ['speech' => 'Mesuré, précis', 'gesture' => 'Contenue', 'mail' => 'Ordonnés, détaillés', 'stress' => 'Se réfugie dans les faits'],
            'disagree' => 'Vous contestez la méthode, pas la personne : dites votre critère de validation, cela désamorce le blocage.',
            'grow' => 'Donner une réponse datée, même partielle ; fixer à l\'avance le niveau de qualité suffisant.',
        ],
    ];

    /** Perception croisée : PERCEPTION[moi][l'autre] = [ce que je perçois, ce que l'autre perçoit de moi, friction]. */
    public const PERCEPTION = [
        'D' => [
            'D' => ['Un concurrent, à qui tenir tête', 'Un rival direct', 'La lutte de pouvoir et le dernier mot'],
            'I' => ['Sympathique, mais trop bavard', 'Sec, il coupe l\'élan', 'Le manque de temps pour échanger'],
            'S' => ['Trop lent, il ne se positionne pas', 'Brutal, il ne m\'écoute pas', 'Le rythme imposé sans écoute'],
            'C' => ['Il freine tout avec ses détails', 'Rapide, mais peu rigoureux', 'La vitesse contre l\'exigence de preuve'],
        ],
        'I' => [
            'D' => ['Efficace, mais froid et cassant', 'Trop de mots, peu de résultats', 'Le temps passé à échanger'],
            'I' => ['On se comprend, on avance vite', 'Un complice, personne ne cadre', 'L\'absence de décision et de suivi'],
            'S' => ['Agréable, mais peu réactif', 'Envahissant, il parle beaucoup', 'L\'énergie imposée à l\'autre'],
            'C' => ['Rigide, il bloque les idées', 'Approximatif, peu fiable', 'L\'improvisation face au besoin de cadre'],
        ],
        'S' => [
            'D' => ['Il va trop vite, il me bouscule', 'Trop lent à se positionner', 'Le rythme et la décision imposée'],
            'I' => ['Agréable, mais dispersé', 'Discret, il participe peu', 'Le manque de suivi et de cadre'],
            'S' => ['Je me sens compris et en sécurité', 'Un allié naturel', 'L\'évitement mutuel des sujets qui fâchent'],
            'C' => ['Fiable, mais un peu froid', 'Consensuel, peu factuel', 'L\'exigence de preuves face au ressenti'],
        ],
        'C' => [
            'D' => ['Il décide sans preuve suffisante', 'Il bloque, il ralentit tout', 'La décision rapide sans données'],
            'I' => ['Brillant, mais approximatif', 'Froid, il casse l\'enthousiasme', 'Le manque de rigueur perçu'],
            'S' => ['Coopératif, mais peu factuel', 'Distant, un peu critique', 'Le ressenti opposé aux faits'],
            'C' => ['On se comprend, quitte à suranalyser', 'Un pair exigeant', 'La double vérification sans fin'],
        ],
    ];

    /** Interactions : INTERACTION[moi][l'autre] = [ce que je peux ajuster, ce que l'autre gagne à faire avec moi]. */
    public const INTERACTION = [
        'D' => [
            'D' => ['Fixer à l\'avance qui décide quoi', 'Débattre des faits, pas du statut'],
            'I' => ['Laisser un temps d\'échange avant de trancher', 'Arriver avec une demande claire et brève'],
            'S' => ['Annoncer le changement en amont', 'Dire son délai au lieu de le subir'],
            'C' => ['Accepter un temps de vérification borné', 'Donner un avis daté, même partiel'],
        ],
        'I' => [
            'D' => ['Annoncer le résultat avant le récit', 'Accorder deux minutes de contexte'],
            'I' => ['Désigner qui suit le dossier', 'Écrire ce qui a été décidé'],
            'S' => ['Laisser la parole et attendre la réponse', 'Dire clairement ce qui le gêne'],
            'C' => ['Préparer des faits avant de convaincre', 'Reconnaître l\'idée avant d\'en pointer les failles'],
        ],
        'S' => [
            'D' => ['Annoncer un délai clair, ne pas rester vague', 'Laisser un temps de réflexion avant de trancher'],
            'I' => ['Redemander un cadre écrit après l\'échange', 'Solliciter son avis : il ne le donnera pas seul'],
            'S' => ['Nommer le désaccord tôt, à froid', 'Se fixer une décision datée'],
            'C' => ['Appuyer son ressenti sur des exemples factuels', 'Reconnaître l\'impact humain, pas que les faits'],
        ],
        'C' => [
            'D' => ['Donner une réponse datée, même partielle', 'Préciser le niveau de preuve attendu'],
            'I' => ['Commencer par ce qui fonctionne', 'Appuyer sa proposition sur des faits'],
            'S' => ['Reconnaître l\'impact humain, pas que les faits', 'Illustrer son ressenti par des exemples'],
            'C' => ['Fixer un seuil de qualité écrit', 'Arrêter la vérification à la date prévue'],
        ],
    ];

    /** Combinaisons dominante + secondaire. */
    public const COMBOS = [
        'DI' => ['L\'entraîneur', 'Vous avancez vite et emmenez les autres avec vous : l\'énergie du résultat et de la conviction.'],
        'DS' => ['Le bâtisseur', 'Déterminé et constant : vous visez loin et tenez dans la durée.'],
        'DC' => ['Le stratège', 'Exigeant et rigoureux : vous voulez des résultats, et des résultats justes.'],
        'ID' => ['Le promoteur', 'Communicant et audacieux : vous convainquez et faites bouger les lignes.'],
        'IS' => ['Le conseiller', 'Chaleureux et attentif : vous créez du lien et mettez les gens à l\'aise.'],
        'IC' => ['Le créatif structuré', 'Inventif et soigné : vous savez donner envie et donner forme.'],
        'SD' => ['Le pilier engagé', 'Fiable et tenace : on peut compter sur vous, jusqu\'au bout.'],
        'SI' => ['L\'accompagnateur', 'À l\'écoute et bienveillant : vous soutenez et encouragez.'],
        'SC' => ['Le spécialiste fiable', 'Patient et méthodique : la qualité, dans la sérénité.'],
        'CD' => ['L\'architecte', 'Analytique et décidé : vous construisez des solutions solides.'],
        'CI' => ['Le pédagogue', 'Précis et communicant : vous rendez clair ce qui est complexe.'],
        'CS' => ['Le gardien de la qualité', 'Rigoureux et constant : vous assurez la fiabilité dans la durée.'],
    ];

    /** Lecture de l'écart entre profil naturel et adapté. */
    public static function gapReading(int $gap): array
    {
        return match (true) {
            $gap < 12 => ['Écart faible', 'Votre façon d\'agir en contexte ressemble à votre nature : votre situation actuelle vous demande peu d\'effort d\'adaptation.'],
            $gap < 25 => ['Écart modéré', 'Vous ajustez votre comportement à votre contexte : c\'est utile, à condition de vous ménager des temps où vous pouvez être vous-même.'],
            default => ['Écart marqué', 'Vous vous adaptez fortement à votre contexte. Si cela dure, c\'est un signal de fatigue possible : vérifiez ce qui vous oblige à vous contraindre, et parlez-en.'],
        };
    }
}
