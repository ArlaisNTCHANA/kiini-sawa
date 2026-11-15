# Module Compte

**Interface utilisateur** : Partielle (connexion et onboarding ; pas d'inscription ni de mot de passe oublié)

## Rôle

Identité et accès : utilisateur, connexion, onboarding, création de comptes.

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/Compte/` |
| Templates | `templates/compte/` |
| Tests | — (à écrire) |

## Command

`CreateUserCommand`

## Controller

`OnboardingController`, `SecurityController`

## Entity

`User`, `UserProfile`, `UserSession`

## Repository

`UserProfileRepository`, `UserRepository`, `UserSessionRepository`

## Security

`LoginAuthenticator`

## Routes

| Nom | Méthode | URL |
|---|---|---|
| `app_login` | ANY | `/login` |
| `app_logout` | ANY | `/logout` |
| `app_onboarding` | ANY | `/onboarding` |

## Templates

`compte/login.html.twig`, `compte/onboarding.html.twig`
