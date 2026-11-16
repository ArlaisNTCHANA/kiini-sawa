# Module Défis

**Interface utilisateur** : Oui (depuis le 16/11/2026) — défis collectifs publics.

## Défis collectifs

Un défi (ex. « 21 jours de discipline ») a une **page publique** `/defi/{slug}` à partager sur les réseaux.

- **Admin** : Croissance › Défis (`/admin/defis`) : titre, adresse, premier jour, durée (3 à 100 jours), accroche,
  présentation, action de chaque jour (commune) et/ou action jour par jour (une ligne = un jour), publication.
  La fiche donne les liens suivis par plateforme (campagne `defi-{slug}`) et les chiffres (inscrits, validations du jour, réussites).
- **Inscription** : bouton « Je relève le défi ». Sans compte : le défi est retenu en session
  (`PendingInvitation::CHALLENGE_KEY`), la personne crée son compte (ou se connecte) et revient inscrite automatiquement.
- **Validation** : « J'ai fait ma part aujourd'hui », une fois par jour, seulement le jour même et pendant la période.
  Aussi depuis la carte du défi sur l'accueil. Les validations apparaissent dans l'agenda (filtre Défis).
- **Réussite** : au moins 80 % des jours validés (`ChallengeService::SUCCESS_RATIO`) ; statut `COMPLETED`.
- **Classement** : jours validés, prénom + initiale du nom uniquement.
- **Emails** (`NotificationType::CHALLENGE_DAY`, préférence « défis ») : bienvenue à l'inscription, puis l'action du
  jour chaque matin pendant le défi (tâche planifiée des rappels). Désabonnement en un clic.
- **Partage** : WhatsApp, Facebook, lien copié — tous avec des liens suivis ; message spécial une fois le défi relevé.

Code : `Service/ChallengeService`, `Controller/ChallengeController`, `Controller/Admin/ChallengeAdminController`,
gabarits `templates/defis/`. Tests : `tests/Defis/ChallengeTest.php`.

Les entités `ChallengeInvitation` et `ChallengeActivity` (défis entre partenaires, fil d'activité) restent dormantes.
