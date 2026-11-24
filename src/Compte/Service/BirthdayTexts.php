<?php

namespace App\Compte\Service;

use App\Contenu\Service\EditableTextProvider;
use App\Contenu\Service\SiteTexts;

/**
 * Textes de la date de naissance écrits en PHP (messages d'erreur, message d'anniversaire), modifiables dans
 * Admin › Textes du site. Tournures neutres et vouvoiement : l'application ne connaît pas le sexe des personnes.
 */
final class BirthdayTexts implements EditableTextProvider
{
    public static function defaults(): array
    {
        return [
            BirthDate::MISSING => 'Indiquez votre date de naissance.',
            BirthDate::INVALID => 'Cette date ne semble pas juste. Vérifiez-la.',
            BirthDate::TOO_YOUNG => 'KIINI SAWA est réservé aux personnes de 15 ans et plus. Nous serons heureux de vous accueillir le moment venu.',
            'naissance.erreur.accord_parents' => 'Vous avez moins de 18 ans : merci de confirmer que vos parents sont d\'accord.',
            'naissance.enregistree' => 'Votre date de naissance a été enregistrée.',
            'naissance.effacee' => 'Votre date de naissance a été effacée.',
            'anniversaire.titre' => 'Joyeux anniversaire, {prenom} !',
            'anniversaire.texte' => 'Une nouvelle année de vie commence. C\'est le bon moment pour relire votre Plan de Vie : ce qui vous fait vivre, ce que vous voulez bâtir, et la prochaine étape à franchir avec Dieu.',
            'anniversaire.bouton' => 'Relire mon Plan de Vie',
        ];
    }

    /** Le texte à afficher (modifié dans l'administration, sinon d'origine). Statique : un fournisseur de textes ne dépend d'aucun service. */
    public static function text(SiteTexts $texts, string $code, array $vars = []): string
    {
        $value = $texts->get($code, self::defaults()[$code] ?? $code);
        foreach ($vars as $k => $v) {
            $value = str_replace('{'.$k.'}', (string) $v, $value);
        }

        return $value;
    }

    public function editableTexts(): array
    {
        return self::defaults();
    }

    public function editableTextsSource(): string
    {
        return 'src/Compte/Service/BirthdayTexts.php';
    }
}
