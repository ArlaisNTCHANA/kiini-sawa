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

## Engagements de redevabilité (lot 1, 23/11/2026)

Une personne demande à un partenaire de confiance (avec ou sans compte KIINI SAWA) de l'interpeller sur un objectif du
Plan de Vie, un rituel ou un engagement libre.

- **Pages** : `/social/redevabilite` (Ma redevabilité : engagements, chiffres, historique, « Je suis partenaire »),
  `/social/redevabilite/nouveau` → `/apercu` (aperçu exact des e-mails et case « J'ai compris ») → `/creer`.
  Pages publiques du partenaire, ouvertes par lien personnel (jeton de 48 caractères, déclarées publiques dans
  `security.yaml` et `SecurityGuardsTest`) : `/redevabilite/{jeton}` (accepter, refuser, ne plus recevoir) et
  `/redevabilite/reponse/{jeton}` (réponse en un clic : appel, message, rencontre, prière, et un mot).
- **Règles** (`Social\Service\AccountabilityService`) :
  - consentement explicite de la personne, enregistré dans `ownerConsentAt` ;
  - **rien n'est envoyé au partenaire tant qu'il n'a pas accepté**, sauf l'invitation elle-même ;
  - au plus une interpellation tous les 7 jours par engagement (`minDaysBetweenNudges`) ;
  - chaque interpellation part au partenaire, avec **une copie à la personne** (deux envois séparés) ;
  - le partenaire ne voit que l'engagement, jamais le journal, les plans ou les tests ;
  - la personne peut mettre en pause, reprendre ou arrêter ; le partenaire peut refuser ou arrêter ;
  - offre gratuite : un engagement ouvert à la fois (`FREE_LIMIT`) ; nombre illimité en PRO.
- **Mesure (lot 1)** : interpellations, taux de réponse, délai moyen, actions du partenaire, historique.
- **E-mails** : `templates/social/accountability/email/` (invitation, interpellation, copie). Leurs corps
  (`_invitation_body`, `_nudge_body`) servent aussi à l'aperçu, pour que la personne voie exactement ce qui part.
- **Entités** : `AccountabilityCommitment`, `AccountabilityNudge` (migration `Version20261123100000`).
  Notification `NotificationType::ACCOUNTABILITY` (accord, refus, réponse du partenaire).
- **Tests** : `tests/Social/AccountabilityTest.php`.
- **Alertes automatiques** (23/11/2026, demande du fondateur : « la personne peut ne plus avoir la force de demander ») :
  - à la création, la personne choisit les signaux qui préviennent son partenaire sans qu'elle ait à le demander :
    rituel non validé N jours de suite (par défaut 3), objectif sans mise à jour depuis N jours (14), aucune connexion
    depuis N jours (7, pour tous les engagements). Seuils de 2 à 90 jours ;
  - `AccountabilityService::runDailyChecks()` est appelé chaque matin par `app:reminders:send` (tâche hPanel
    `cron-rappels.sh`, 7 h) ;
  - jamais deux alertes pour le même décrochage : une reprise doit avoir lieu après la dernière alerte du même motif ;
    au plus un message tous les 7 jours (alertes et demandes confondues) ; rien pendant une pause ;
  - l'e-mail au partenaire énonce le constat sans juger (« … n'a pas validé son rituel depuis 3 jours ») ;
    la personne reçoit une copie qui rappelle que c'est elle qui a choisi ce filet de sécurité.
- **Demande manuelle** : bouton « J'ai besoin d'un coup de main », avec un mot facultatif.
- **Mesure** : interpellations (par motif), taux de réponse, délai moyen, **reprises dans les 72 heures**
  (`AccountabilityNudge::resumedAt` : rituel validé, objectif mis à jour, ou retour dans l'application selon
  l'engagement), historique. `LifeGoal::updatedAt` (nouvelle colonne) sert au signal « objectif sans avancée ».
- **Lots suivants** : revue de la semaine (chiffres de redevabilité), application mobile.

