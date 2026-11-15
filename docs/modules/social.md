# Module Social

**Interface utilisateur** : Partielle (2 partages sur 10 effectifs)

## Rôle

Réseau de redevabilité : connexions, partage, commentaires, liens partagés.

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/Social/` |
| Templates | `templates/social/` |
| Tests | — (à écrire) |

## Controller

`SocialController`

## Entity

`Comment`, `Connection`, `SharedLink`

## Enum

`ShareScope`

## Form

`AddConnectionType`

## Repository

`CommentRepository`, `ConnectionRepository`, `SharedLinkRepository`

## Routes

| Nom | Méthode | URL |
|---|---|---|
| `api_social_connection_add` | POST | `/api/social/connection/add` |
| `api_social_connection_permissions` | POST | `/api/social/connection/{id}/permissions` |
| `api_social_connection_respond` | POST | `/api/social/connection/{id}/respond` |
| `api_social_partner_shared_data` | GET | `/api/social/partner/{id}/shared-data` |
| `api_social_suggestion_respond` | POST | `/api/social/suggestion/{id}/respond` |
| `app_social` | ANY | `/social` |

## Templates

`social/index.html.twig`
