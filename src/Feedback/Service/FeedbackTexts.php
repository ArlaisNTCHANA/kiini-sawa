<?php

namespace App\Feedback\Service;

use App\Contenu\Service\EditableTextProvider;

/**
 * Textes du feedback nourricier écrits en PHP (messages à partager, notifications), modifiables dans
 * Admin › Textes du site (partie « feedback »). Variables entre accolades : {lien}, {date}, {nombre}, {prenom}.
 * Tournures neutres : l'application ne connaît pas le sexe des personnes.
 */
final class FeedbackTexts implements EditableTextProvider
{
    public static function defaults(): array
    {
        return [
            'feedback.message.tu' => "Bonjour ! J'avance sur mon Plan de Vie avec KIINI SAWA et ton regard me serait précieux : les qualités que tu vois chez moi, ce que je fais de mieux, et un point où je pourrais grandir. Cela prend 3 minutes{anonymat}.\n\nTu peux répondre jusqu'au {date} :\n{lien}\n\nMerci du fond du cœur !",
            'feedback.message.tu.anonyme' => ', et tu peux rester anonyme',
            'feedback.message.vous' => "Bonjour, j'avance sur mon Plan de Vie avec KIINI SAWA et votre regard me serait précieux : les qualités que vous voyez chez moi, ce que je fais de mieux, et un point où je pourrais grandir. Cela prend 3 minutes{anonymat}.\n\nVous pouvez répondre jusqu'au {date} :\n{lien}\n\nMerci beaucoup !",
            'feedback.message.vous.anonyme' => ', et vos réponses peuvent rester anonymes',
            'feedback.message.email_sujet' => "Votre regard me serait précieux",
            'feedback.notif.reponse.titre' => 'Un nouveau regard sur vous',
            'feedback.notif.reponse.detail' => 'Quelqu\'un a répondu à votre demande de feedback. Découvrez ce que vos proches voient chez vous.',
            'feedback.notif.reponse.attente' => 'Quelqu\'un a répondu à votre demande de feedback ({nombre} sur {seuil} avant de voir la synthèse).',
            'feedback.notif.fin.titre' => 'Votre lien de feedback est arrivé à son terme',
            'feedback.notif.fin.texte' => '{nombre} personne(s) vous ont répondu. Vous pouvez prolonger le lien d\'une semaine ou découvrir ce que vos proches voient chez vous.',
            'feedback.notif.relance.titre' => 'Vos proches ont peut-être oublié',
            'feedback.notif.relance.texte' => 'Votre demande de feedback a reçu {nombre} réponse(s). Un petit message de rappel suffit souvent : il en faut {seuil} pour découvrir la synthèse.',
        ];
    }

    public function editableTexts(): array
    {
        return self::defaults();
    }

    public function editableTextsSource(): string
    {
        return 'src/Feedback/Service/FeedbackTexts.php';
    }
}
