# Module Admin

**Interface utilisateur** : Complète (gabarit commun refait le 23/11/2026)

## Rôle

Back-office : dashboard, configuration de l'application, documentation, télémétrie, données de démo.

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/Admin/` |
| Templates | `templates/admin/` |
| Tests | `tests/Admin/` |

## Gabarit commun et menu (23/11/2026)

- Toutes les pages d'administration (y compris celles des autres modules : paiements, organisations, tests, textes du site…)
  étendent `templates/admin/layout.html.twig` : menu latéral (tiroir « Menu » sur téléphone), fil d'Ariane, titre,
  action principale, messages de confirmation. Même palette que l'application (`templates/shared/_design_tokens.html.twig`).
- Menu défini une seule fois : `App\Admin\Twig\AdminNavigationExtension::GROUPS` (Accueil, Les membres, Les contenus,
  Les chiffres, Réglages). Fonctions Twig : `admin_nav()`, `admin_current()`, `admin_todo()` (paiements, commandes de
  places, avis, brouillons en attente : pastilles du menu et bloc « À traiter » du tableau de bord).
- Blocs d'une page : `title`, `page_title`, `page_lead`, `page_actions` (une seule `ks-btn--primary`), `crumbs`, `content`.
- Composants : `ks-stat`, `ks-todo`, `ks-table ks-table--cards` (fiches sur téléphone : chaque `td` porte `data-label`),
  `ks-toolbar`, `ks-form-grid`, `ks-row-link`, `ks-sticky-actions` (`assets/styles/app.css`, section Administration).
- Ajouter une page : l'inscrire dans `GROUPS` ; `tests/Admin/AdminNavigationTest.php` vérifie que chaque page du menu
  s'ouvre avec le cadre commun, qu'aucune route `/admin` n'est oubliée, et l'absence de couleur en dur.

## Command

`SeedDataCommand`

## Controller

`ConfigurationController`, `DashboardController`, `DocController`

## Entity

`AppConfig`, `TelemetryEvent`

## Form

`AppConfigType`

## Repository

`AppConfigRepository`, `TelemetryEventRepository`

## Routes

| Nom | Méthode | URL |
|---|---|---|
| `admin_config` | ANY | `/admin/config` |
| `admin_dashboard` | ANY | `/admin` |
| `admin_doc` | ANY | `/admin/doc/{page}` |

## Templates

`admin/config/index.html.twig`, `admin/dashboard.html.twig`, `admin/doc.html.twig`, `admin/layout.html.twig`
