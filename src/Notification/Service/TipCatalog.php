<?php

namespace App\Notification\Service;

use App\Contenu\Service\EditableTextProvider;
use App\Contenu\Service\SiteTexts;

/**
 * Messages « Saviez-vous que… » : une fonctionnalité que le membre n'a jamais utilisée, présentée dans un e-mail court
 * avec un seul bouton (envoi : TipSender). L'ordre suit la méthode : faire le point, se connaître, demander le regard
 * des autres, avancer accompagné, puis les outils pour aller plus loin.
 *
 * Textes modifiables dans Admin › Textes du site ; chaque message s'active ou se désactive dans Admin › Configuration.
 */
final class TipCatalog implements EditableTextProvider
{
    /**
     * Code => [adresse du bouton, fonction de l'offre à vérifier (null = toujours gratuite), libellé pour l'administration].
     *
     * @var array<string, array{0: string, 1: string|null, 2: string}>
     */
    public const TIPS = [
        'revue' => ['/bilan/revue-hebdomadaire', null, 'Revue de la semaine'],
        'test' => ['/mes-tests/', null, 'Tests de personnalité'],
        'boussole' => ['/boussole', null, 'Ma Boussole 360'],
        'feedback' => ['/mes-tests/feedback', null, 'Feedback nourricier'],
        'partenaire' => ['/social', 'social', 'Partenaire de Redevabilité'],
        'journal' => ['/journal', 'journal', 'Journal'],
        'academie' => ['/academie', null, 'Académie'],
        'defis' => ['/defis', null, 'Défis collectifs'],
    ];

    public static function defaults(): array
    {
        return [
            'astuce.revue.titre' => 'Saviez-vous que vous pouvez faire le point chaque semaine ?',
            'astuce.revue.texte' => "La revue de la semaine prend une quinzaine de minutes : vous regardez vos victoires, vous évaluez vos plans et vous choisissez vos trois priorités pour la semaine suivante.\nC'est elle qui relie votre vision à votre agenda.",
            'astuce.revue.bouton' => 'Faire ma revue de la semaine',

            'astuce.test.titre' => 'Saviez-vous que vous pouvez découvrir comment Dieu vous a fait ?',
            'astuce.test.texte' => "Les tests de personnalité vous aident à mieux comprendre vos forces, vos points de vigilance et votre manière de fonctionner.\nChaque test donne un rapport détaillé, avec des pistes concrètes pour votre Plan de Vie.",
            'astuce.test.bouton' => 'Voir les tests',

            'astuce.boussole.titre' => 'Saviez-vous qu\'en cinq minutes, vous pouvez voir où vous en êtes sur vos six plans ?',
            'astuce.boussole.texte' => "Ma Boussole 360 : 24 affirmations, et vous voyez d'un coup d'œil vos plans forts, ceux qui demandent de l'attention, et trois pistes pour la semaine.\nRefaites-la de temps en temps : votre historique montre le chemin parcouru.",
            'astuce.boussole.bouton' => 'Faire ma Boussole 360',

            'astuce.feedback.titre' => 'Saviez-vous que vous pouvez demander à vos proches ce qu\'ils voient en vous ?',
            'astuce.feedback.texte' => "Avec le feedback nourricier, vous envoyez un lien à quelques personnes qui vous connaissent bien. Elles répondent sans créer de compte, et peuvent rester anonymes.\nVous recevez une synthèse : un regard extérieur qui confirme, ou nuance, ce que vous pensez de vous-même.",
            'astuce.feedback.bouton' => 'Demander un feedback',

            'astuce.partenaire.titre' => 'Saviez-vous que vous pouvez avancer avec un Partenaire de Redevabilité ?',
            'astuce.partenaire.texte' => "Un Partenaire de Redevabilité est une personne de confiance qui vous encourage et vous demande, avec bienveillance, où vous en êtes.\nVous l'invitez depuis KIINI SAWA, et vous avancez à deux vers ce qui compte pour vous.",
            'astuce.partenaire.bouton' => 'Inviter un Partenaire de Redevabilité',

            'astuce.journal.titre' => 'Saviez-vous que vous pouvez tenir votre journal dans KIINI SAWA ?',
            'astuce.journal.texte' => "Le journal garde vos réflexions et ce que vous apprenez en chemin.\nVous pouvez relier une note à un objectif de votre Plan de Vie, et la retrouver au moment de vos revues.",
            'astuce.journal.bouton' => 'Ouvrir mon journal',

            'astuce.academie.titre' => 'Saviez-vous que l\'Académie vous propose des parcours à votre rythme ?',
            'astuce.academie.texte' => "Des leçons courtes, une à la fois, au rythme que vous choisissez.\nVous pouvez même recevoir la leçon du jour par e-mail.",
            'astuce.academie.bouton' => 'Découvrir l\'Académie',

            'astuce.defis.titre' => 'Saviez-vous que vous pouvez relever un défi avec d\'autres membres ?',
            'astuce.defis.texte' => "Un défi collectif, c'est une action par jour pendant quelques jours, avec d'autres membres.\nAvancer ensemble aide à tenir.",
            'astuce.defis.bouton' => 'Voir les défis',

            'astuce.pro' => 'Cette fonction fait partie de la formule PRO.',
            'astuce.pied' => 'Vous recevez de temps en temps un message « Saviez-vous que… » pour découvrir une fonction de KIINI SAWA que vous n\'avez pas encore utilisée.',
            'astuce.desabonnement' => 'Ne plus recevoir ces messages',
        ];
    }

    /** Le texte à afficher (modifié dans l'administration, sinon d'origine). */
    public static function text(SiteTexts $texts, string $code): string
    {
        return $texts->get($code, self::defaults()[$code] ?? $code);
    }

    /** @return array<string, string> libellé => code, pour les cases de l'administration */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::TIPS as $code => [, , $label]) {
            $choices[$label] = $code;
        }

        return $choices;
    }

    public function editableTexts(): array
    {
        return self::defaults();
    }

    public function editableTextsSource(): string
    {
        return 'src/Notification/Service/TipCatalog.php';
    }
}
