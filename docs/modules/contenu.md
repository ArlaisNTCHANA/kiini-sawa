# Module Contenu : textes du site modifiables

Objectif (demande du fondateur, 18/11/2026) : **ne plus mettre de texte « en dur » dans le code**, pouvoir modifier les textes dans l'administration, avec **brouillon puis publication** et retour en arrière.

## Principe
- Le code (ou un fichier `content/textes/`) reste le **texte d'origine** ; la base ne garde que les modifications (`site_text` : texte publié + brouillon ; `site_text_version` : historique). Supprimer la modification = retour au texte d'origine.
- Lecture : texte publié, sinon texte d'origine. Un administrateur en **mode aperçu** voit en plus les brouillons (session, bandeau d'aperçu) ; le public ne les voit jamais.
- Écriture (`Admin › Textes du site`, `/admin/textes`) : Enregistrer le brouillon → relire en aperçu → Publier (ou « Publier les brouillons »). Chaque publication est gardée : « Remettre dans le brouillon », « Revenir au texte d'origine ».

## Écrire un texte dans un gabarit
```twig
{{ t('vitrine.hero.titre', 'Texte d\'origine') }}                       {# texte simple #}
{{ t('code', 'Texte avec **gras** et [lien](/url)', 'inline') }}        {# mise en forme légère #}
{{ t('code', 'Bonjour {nom}', 'text', {nom: user.firstName}) }}         {# variables #}
{{ doc('legal.cgu') }}                                                   {# document long : content/textes/legal/cgu.md #}
```
- Un **code** = lettres minuscules, chiffres, `_`, `.`, `-`. Le premier segment est la « partie » (vitrine, legal…).
- Le catalogue (`SiteTextCatalog`) trouve les `t('code', 'origine')` en parcourant `templates/` : un nouveau texte apparaît tout seul dans l'administration. **Le même code ne doit pas être réutilisé avec deux textes différents** (le premier trouvé gagne).
- Le HTML saisi est toujours neutralisé (Markdown en mode sûr) ; les passages `[À COMPLÉTER…]` sont surlignés et comptés par `app:launch:check` (sur le texte **en ligne**).
- Un document long : un fichier `content/textes/<partie>/<nom>.md`, code `<partie>.<nom>`.
- **Textes écrits en PHP** (23/11/2026) : un service qui implémente `Contenu\Service\EditableTextProvider`
  (`editableTexts()` : code => texte d'origine, `editableTextsSource()`) apparaît automatiquement dans
  l'administration. Pour lire le texte affiché, utiliser `SiteTexts::get($code, $origine)`. Le fournisseur ne doit
  dépendre d'aucun service (le catalogue le lit, et SiteTexts dépend du catalogue).
  Premier fournisseur : `CoupleSanctuaryCatalog` (partie « couple », 143 textes), lus via `CoupleSanctuaryTexts`.

## Où en est la conversion
- **Fait** : vitrine publique (`dashboard/landing.html.twig`, 121 textes courts), 4 pages légales (documents),
  Sanctuaire du Couple (versets, défis, méditations, signaux : 143 textes, partie « couple »).
- **À faire (phases suivantes)** : textes écrits en PHP (PlanCatalog, navigation, saisons, rythmes, intendance, schémas du livre, assistant), emails et notifications, reçus, le livre (`content/livre/`, qui vient du manuscrit : prévoir ce qui se passe à la resynchronisation), et les textes des tests (déjà modifiables via `Admin › Textes des tests`, sans brouillon pour l'instant).
- Règle pour tout nouveau texte visible par l'utilisateur : `t()`/`doc()`, pas de texte brut dans le gabarit.

## Tests
`tests/Contenu/SiteTextsTest.php` : catalogue, brouillon invisible tant que non publié, aperçu réservé à l'administrateur, historique et retour à l'origine, HTML neutralisé, pages légales éditables (l'avertissement disparaît quand plus rien n'est à compléter), droits d'accès, jeton CSRF.
