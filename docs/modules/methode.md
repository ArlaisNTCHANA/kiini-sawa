# Module Methode

**Interface utilisateur** : Oui

## Rôle

Le livre dans l'application : les chapitres se lisent sur `/methode`, et chaque écran de méthode
affiche un encart « Comprendre cette étape » relié à son chapitre. L'application devient le
compagnon du livre, et le livre son canal d'acquisition (docs/plan-v1.md §3.6).

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/Methode/` |
| Chapitres affichés | `content/livre/*.md` (copie du manuscrit `KIINI SAWA/docs/livre/`) |
| Templates | `templates/methode/` |
| Tests | `tests/Methode/` |

## Utilisation

- Dans un gabarit : `{{ book_panel('wizard.1') }}`. Les clés et leur chapitre sont dans `BookLibrary::STEPS`.
- Après une modification du manuscrit : `php bin/console app:livre:sync`.
- Le manifeste et la page auteur ne sont pas publiés.

## Routes

| Nom | Méthode | URL |
|---|---|---|
| `app_methode_index` | GET | `/methode` |
| `app_methode_chapter` | GET | `/methode/{slug}` |
