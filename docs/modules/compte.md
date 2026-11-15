# Module Compte

**Interface utilisateur** : Complète (inscription, vérification de l'email, connexion, mot de passe oublié, profil, RGPD, onboarding, pages légales)

## Rôle

Identité et accès : création de compte, vérification de l'email, connexion, récupération du compte,
profil, droits RGPD (export, suppression), consentement, onboarding court et pages légales.

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/Compte/` |
| Templates | `templates/compte/` (emails dans `templates/compte/email/`), `templates/legal/` |
| Tests | `tests/Compte/` |

## Parcours

1. **Inscription** (`/inscription`) : prénom, email, mot de passe (10 caractères min.), acceptation des CGU et de la politique de confidentialité (horodatée dans `User.termsAcceptedAt`). Champ piège anti-robots et limite de 5 inscriptions par heure et par IP. Si l'adresse a déjà un compte, rien n'est révélé : son propriétaire reçoit un email.
2. **Vérification de l'email** (`/verification-email`) : code à 6 chiffres, valable 15 minutes, 5 essais, renvoi au plus une fois par minute. Tant que `User.isVerified` est faux, `VerifiedUserSubscriber` bloque tout le reste de l'application (redirection, ou 403 JSON pour l'API).
3. **Onboarding** (`/onboarding`) : prénom, raison d'être, 3 valeurs, un premier objectif. Crée un Plan de Vie minimal puis ouvre l'analyse de cohérence (`/life-plan?onglet=analysis`). « Plus tard » est possible.
4. **Mot de passe oublié** (`/mot-de-passe/oublie`) : même réponse que l'adresse existe ou non ; lien valable 1 heure, à usage unique, retiré de l'URL dès la première visite. Limite de 5 demandes par heure et par IP.
5. **Profil** (`/profil`) : informations, changement de mot de passe (mot de passe actuel exigé), changement d'email (code envoyé à la nouvelle adresse), export JSON de toutes les données, suppression définitive du compte (taper SUPPRIMER + mot de passe).

## Règles à respecter

- **Suppression** : `AccountDeleter` supprime la ligne `user` et s'appuie sur les clés étrangères `ON DELETE CASCADE`. Toute nouvelle relation vers `User`, ou vers une entité qui appartient à un utilisateur, doit déclarer `onDelete: 'CASCADE'` sur sa `JoinColumn`. Le test `ProfileTest::testDeleteRemovesEveryTraceOfTheUser` échoue sinon.
- **Export** : `UserDataExporter` parcourt automatiquement les relations Doctrine depuis l'utilisateur : un nouveau module est inclus sans rien modifier. Les champs secrets (`password`, `secretHash`) sont exclus.
- **Jetons** (`AccountToken`) : seule l'empreinte HMAC du code ou du lien est stockée.
- **Emails** : partent via Messenger. En local, ils sont envoyés immédiatement (visibles dans le profiler) et le code s'affiche dans un message `[dev]`. Expéditeur : variable `MAILER_FROM`.
- **Pages publiques** : à déclarer dans `access_control` (`config/packages/security.yaml`) et dans `SecurityGuardsTest::PUBLIC_ROUTES`.

## Command

`CreateUserCommand` (`app:create-user email motdepasse [--admin]`, compte vérifié d'office)

## Controller

`RegistrationController`, `PasswordResetController`, `ProfileController`, `OnboardingController`, `SecurityController`, `LegalController`

## Entity

`User`, `UserProfile`, `UserSession`, `AccountToken`

## Repository

`UserProfileRepository`, `UserRepository`, `UserSessionRepository`, `AccountTokenRepository`

## Security

`LoginAuthenticator`, `VerifiedUserSubscriber`

## Service

`AccountTokenService`, `AccountMailer`, `PasswordPolicy`, `UserDataExporter`, `AccountDeleter`

## Routes

| Nom | Méthode | URL |
|---|---|---|
| `app_login` | ANY | `/login` |
| `app_logout` | ANY | `/logout` |
| `app_register` | GET, POST | `/inscription` |
| `app_verify_email` | GET, POST | `/verification-email` |
| `app_verify_email_resend` | POST | `/verification-email/renvoyer` |
| `app_forgot_password` | GET, POST | `/mot-de-passe/oublie` |
| `app_reset_password` | GET, POST | `/mot-de-passe/reinitialiser/{selector}/{secret}` |
| `app_reset_password_form` | GET, POST | `/mot-de-passe/reinitialiser` |
| `app_onboarding` | GET, POST | `/onboarding` |
| `app_profile` | GET | `/profil` |
| `app_profile_info` | POST | `/profil/informations` |
| `app_profile_password` | POST | `/profil/mot-de-passe` |
| `app_profile_email` | POST | `/profil/email` |
| `app_profile_email_confirm` | POST | `/profil/email/confirmer` |
| `app_profile_email_cancel` | POST | `/profil/email/annuler` |
| `app_profile_export` | GET | `/profil/export` |
| `app_profile_delete` | POST | `/profil/supprimer` |
| `app_legal` | GET | `/mentions-legales`, `/confidentialite`, `/cgu`, `/cgv` |

## Templates

`compte/_auth_layout.html.twig` (gabarit commun des pages hors connexion), `login`, `register`, `verify_email`, `forgot_password`, `reset_password`, `onboarding`, `profile` ; `legal/*.html.twig` (versions de travail, passages `[À COMPLÉTER]`).
