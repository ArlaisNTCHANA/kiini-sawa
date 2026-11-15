# Module Admin

**Interface utilisateur** : Partielle

## Rôle

Back-office : dashboard, configuration de l'application, documentation, télémétrie, données de démo.

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/Admin/` |
| Templates | `templates/admin/` |
| Tests | `tests/Admin/` |

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
