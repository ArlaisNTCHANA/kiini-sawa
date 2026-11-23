<?php

namespace App\Contenu\Service;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Source de textes modifiables écrits en PHP (et non dans un gabarit) : ils apparaissent dans Admin › Textes du site
 * comme les appels `t()`. Le texte d'origine reste dans le code ; la base ne garde que les modifications.
 * Ne doit dépendre d'aucun service (il est lu par le catalogue, lui-même utilisé par SiteTexts).
 */
#[AutoconfigureTag('app.editable_text_provider')]
interface EditableTextProvider
{
    /** @return array<string, string> code => texte d'origine */
    public function editableTexts(): array;

    /** Fichier où se trouve le texte d'origine, affiché dans l'administration. */
    public function editableTextsSource(): string;
}
