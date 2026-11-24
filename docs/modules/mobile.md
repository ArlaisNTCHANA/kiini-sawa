# Module Mobile

**Statut** : actif (Lots 1, 2, 3, 4 & 5 — application complète : authentification par jetons Bearer, profil/compte, quotidien hors connexion, rituels, journal, push Expo & rappels, progression des 6 plans & cercle SVG, passation des tests & Mon profil KIINI SAWA, Agenda, Revues hebdomadaire/mensuelle/rythmes, Partenaires & communauté, Lecteur natif de La Méthode).

## Rôle

Exposer l'API REST `/api/mobile/v1/...` utilisée par l'application mobile native Expo (`../mobile-v1/`). Ce module reprend la logique métier et les garanties de sécurité des modules existants (`Compte`, `Rapide`, `Rituels`, `Journal`, `Abonnement`, `Notification`, `PlanDeVie`, `Personnalite`, `Planner`, `Bilan`, `Social`, `Defis`, `Couple`, `Methode`) sans session ni cookie.

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/Mobile/` |
| Contrat API | [`docs/api-mobile.md`](../api-mobile.md) |
| Migrations | `migrations/Version20261119120000.php`, `Version20261119130000.php` |
| Tests | `tests/Mobile/` (`MobileApiTest.php`, `MobileNotificationTest.php`, `MobilePlanTest.php`, `MobileTestTest.php`, `MobileLot5Test.php`, `MobileAcademyTest.php`) |
| Pare-feu | `config/packages/security.yaml` (`mobile_api`, `^/api/mobile`) |

## Entités

- `App\Mobile\Entity\MobileToken` (table `mobile_token`) : session appareil par jeton, stockant uniquement les empreintes SHA-256 (`access_hash`, `refresh_hash`), les dates d'expiration (`20 min` / `60 jours`), `last_used_at`, `revoked_at` et `rotated` (`true` si remplacé par rotation). Relation `ON DELETE CASCADE` vers `user`.
- `App\Mobile\Entity\MobilePushDevice` (table `mobile_push_device`) : jeton push Expo pour l'appareil mobile (`token_hash` unique, `expo_token`, `platform`, `device_name`, `created_at`, `last_seen_at`). Relation `ON DELETE CASCADE` vers `user`.

## Services & Contrôleurs

- `App\Mobile\Service\ExpoPushSender` : expédition groupée vers l'API Expo (`https://exp.host/--/api/v2/push/send`), nettoyage automatique des tokens révoqués (`DeviceNotRegistered`), validation de format et mockable en test unitaire.
- `App\Mobile\Service\MobilePushSenderDecorator` : décore `PushSender` (`#[AsDecorator(decorates: PushSender::class)]`) pour que toute notification existante (`Notifier`, `ReminderService`) notifie aussi les appareils mobiles Expo sans modifier une seule ligne du web existant.
- `App\Mobile\Service\MobilePlanSerializer` : transformation riche des 6 plans du livre avec `PlanProgress`, 100 % libellés français, zéro terme banni.
- `App\Mobile\Service\MobileTestSerializer` : catalogue des tests, questions adaptées (blocs DISC, échelles 1-5 et 1-10), rapports détaillés et synthèse globale croisée « Mon profil KIINI SAWA ».
- `App\Mobile\Service\MobileMethodeSerializer` : sérialisation des 28 chapitres du livre KIINI SAWA, gestion d'accès texte intégral Pro vs extrait « L'essentiel » pour compte gratuit sans mention de prix.
- `App\Mobile\Controller\MobilePlannerController` : CRUD des tâches de l'agenda, date/intervalles, liaisons objectifs de croissance.
- `App\Mobile\Controller\MobileReviewController` : revues hebdomadaire et mensuelle, grille d'évaluation des 4 plans, vue synthétique des 5 rythmes.
- `App\Mobile\Controller\MobileCommunityController` : partenaires de redevabilité (invitations, acceptation/refus), défis collectifs et espace couple partagé.
- `App\Mobile\Controller\MobileMethodeController` : catalogue et lecteur de chapitres.
- `App\Mobile\Controller\MobileAcademyController` : L'Académie (voir `docs/modules/academie.md` et `docs/api-mobile.md` § 15). Réutilise `AcademyCatalog`, `AcademyLearning` (commun avec le site) et `AcademyJournal` ; clés en français, aucun prix.
- `App\Mobile\Controller\MobileBoussoleController` : Ma Boussole 360, la même que sur le site (voir `docs/modules/boussole.md`). `GET /api/mobile/v1/boussole/questions`, `POST …/reponses` (`{"reponses": [24 entiers de 1 à 5]}`, 201, calcul serveur et enregistrement), `GET …/dernier`, `GET …/historique`, `GET …/passations/{id}` (404 si la passation est à un autre compte). Clés JSON en français ; noms des plans issus de `PlanCatalog::PLANS`.

## Points d'attention

- Le contrôle d'origine strict (`SameOriginRequestSubscriber`) reste actif pour les sessions cookies et continue de rejeter tout en-tête `Origin` / `Sec-Fetch-Site` cross-site ; les requêtes natives portant uniquement `Authorization: Bearer ...` sans cookie ni `Origin` passent.
- En cas de réutilisation d'un `refreshToken` déjà remplacé par rotation (`rotated = true`), toutes les sessions mobiles actives de l'utilisateur sont révoquées.
- Les réponses PRO dans `/api/mobile/*` (`ProRequiredSubscriber`) ne contiennent jamais `upgradeUrl`, ni tarif, ni lien de paiement (conformité App Store / Google Play).
