# Module Journal

**Interface utilisateur** : Oui

## Rôle

Journal de réflexion et collections.

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/Journal/` |
| Templates | `templates/journal/` |
| Tests | `tests/Journal/` |

## Controller

`JournalController`

## Entity

`JournalCollection`, `ReflectionNote`

## Enum

`ReflectionType`

## Repository

`JournalCollectionRepository`, `ReflectionNoteRepository`

## Routes

| Nom | Méthode | URL |
|---|---|---|
| `api_journal_create_collection` | POST | `/api/journal/collections/create` |
| `api_journal_create_entry` | POST | `/api/journal/entries/create` |
| `api_journal_delete_collection` | DELETE | `/api/journal/collections/{id}` |
| `api_journal_delete_entry` | DELETE | `/api/journal/entries/{id}` |
| `api_journal_entries` | GET | `/api/journal/entries` |
| `app_journal` | ANY | `/journal` |

## Templates

`journal/index.html.twig`
