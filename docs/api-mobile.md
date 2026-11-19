# API Mobile KIINI SAWA (`/api/mobile/v1`)

Contrat officiel pour l'application mobile native Expo (`mobile-v1/`). Toutes les réponses sont en JSON UTF-8, portent `Cache-Control: no-store, private` et datent en ISO 8601 (`DATE_ATOM`).

En cas d'erreur, l'API renvoie `{ "error": "Message en français", "code"?: "..." }`.

---

## 1. Authentification & Sécurité

- Pare-feu Symfony sans session (`mobile_api`, `stateless: true`) utilisant `Authorization: Bearer <accessToken>`.
- Jetons stockés **uniquement sous forme d'empreinte SHA-256** dans `mobile_token` (`ON DELETE CASCADE` vers `user`).
- **Jeton d'accès** : durée de vie **20 minutes** (`expiresIn: 1200`).
- **Jeton de rafraîchissement** : durée de vie **60 jours**, à usage unique avec rotation.
- **Détection de réutilisation** : si un jeton de rafraîchissement déjà remplacé par rotation (`rotated = true`) est présenté à nouveau, **toutes les sessions mobiles du compte sont immédiatement révoquées**. Une simple déconnexion préalable renvoie `401` sans révoquer les autres appareils.
- Les requêtes sans cookie de session n'ont pas besoin d'en-tête `Origin` (`SameOriginRequestSubscriber`) ; en revanche, tout en-tête `Origin` / `Sec-Fetch-Site` cross-site reste bloqué en `403`.
- Aucune mention de prix, de virement, de Mobile Money ni d'URL de paiement : les refus PRO renvoient `403` avec `{ "error": "Cette fonction fait partie de l'offre PRO, que vous pouvez gérer sur le site.", "code": "pro_required", "feature": "..." }`.

### Points d'accès d'authentification (publics)

| Méthode & URL | Corps JSON | Réponse | Notes |
|---|---|---|---|
| `POST /api/mobile/v1/connexion` | `{ email, password, appareil? }` | `200 { accessToken, refreshToken, expiresIn, user }` | Limité (IP 30/15m, compte 5/15m). `401` générique si identifiants faux ; `403 email_unverified` ou `admin_forbidden` après vérification du mot de passe. |
| `POST /api/mobile/v1/jeton` | `{ refreshToken }` | `200 { accessToken, refreshToken, expiresIn, user }` | Limité par IP (30/15m). Rotation à usage unique. `401` si expiré ou révoqué. |
| `POST /api/mobile/v1/deconnexion` | `{ refreshToken?, tous? }` | `200 { ok: true }` | `tous: true` (avec Bearer valide) révoque tous les appareils du compte. |

---

## 2. Profil & Compte

| Méthode & URL | Corps / Query | Réponse | Notes |
|---|---|---|---|
| `GET /api/mobile/v1/moi` | — | `200 { user }` | Inclut `account` (HMAC opaque), `profile`, `entitlements` (`isPro`, `source`, `features`, `limits`, `freeTests`), `plansCatalog` (6 plans du livre). |
| `PATCH /api/mobile/v1/moi` | `{ firstName?, lastName?, city?, country?, profession?, bio? }` | `200 { user }` | Mise à jour partielle du profil. |
| `DELETE /api/mobile/v1/compte` | `{ confirmation: "SUPPRIMER", currentPassword }` | `200 { ok: true }` | Exigence App Store / RGPD. Supprime toutes les données en cascade (`mobile_token` compris) et envoie l'e-mail de confirmation. |

---

## 3. Quotidien hors connexion (modèle `QuickDay`)

| Méthode & URL | Corps | Réponse |
|---|---|---|
| `GET /api/mobile/v1/aujourdhui` | — | `200 { date, account, name, next, habits, review, journal, types }` |
| `POST /api/mobile/v1/sync` | `{ account?, ops: [...] }` | `200 { results: { [uid]: "ok" \| "deja" \| "refusee" }, snapshot }` |

- Chaque opération porte `{ uid, type, date, at, ... }`, avec `uid` de 8 à 64 caractères `[A-Za-z0-9-]` généré par l'appareil (idempotence via `sync_operation`).
- Types acceptés : `habit.log`, `review.save`, `journal.add`.
- Règles serveur : dernière modification gagne, fenêtre `J-7 .. J+1`, 100 opérations max par envoi, `409 { code: "account" }` si `account` diffère du compte authentifié.

---

## 4. Rituels (`/api/mobile/v1/rituels`)

| Méthode & URL | Corps | Réponse |
|---|---|---|
| `GET /api/mobile/v1/rituels` | — | `200 { habits, missedYesterdayIds, limit, count }` |
| `POST /api/mobile/v1/rituels` | `{ title, frequency?, endDate?, recurrenceDay?, recurrenceWeek?, implementationWhen?, ... }` | `201 { habit }` (ou `403 pro_required` si plafond gratuit atteint) |
| `PATCH /api/mobile/v1/rituels/{id}` | Champs modifiés | `200 { habit }` |
| `DELETE /api/mobile/v1/rituels/{id}` | — | `200 { ok: true }` |

---

## 5. Journal & Carnets (`/api/mobile/v1/journal`)

| Méthode & URL | Corps / Query | Réponse |
|---|---|---|
| `GET /api/mobile/v1/journal` | `?limit=20&offset=0&type=...&carnetId=...` | `200 { entries, totalCount, hasMore, carnets, types }` |
| `POST /api/mobile/v1/journal` | `{ content, type?, carnetId?, location? }` | `201 { entry }` |
| `DELETE /api/mobile/v1/journal/{id}` | — | `200 { ok: true }` |
| `POST /api/mobile/v1/journal/carnets` | `{ name, color? }` | `201 { carnet }` |
| `DELETE /api/mobile/v1/journal/carnets/{id}` | — | `200 { ok: true }` |

---

## 6. Notifications & Rappels (`/api/mobile/v1/notifications`)

| Méthode & URL | Corps / Query | Réponse | Notes |
|---|---|---|---|
| `POST /api/mobile/v1/notifications/appareil` | `{ expoToken, platform, appareil? }` | `200 { ok: true, hasDevice: true }` | Enregistre un appareil physique pour les push Expo. Max 10 appareils par compte (les plus anciens sont élagués). |
| `DELETE /api/mobile/v1/notifications/appareil` | `{ expoToken }` | `200 { ok: true, hasDevice: boolean }` | Révoque l'appareil lors de la déconnexion ou de la désactivation. |
| `GET /api/mobile/v1/notifications/preferences` | — | `200 { preferences, hasPushDevice }` | Lit les préférences de notification et l'état de l'appareil. |
| `PATCH /api/mobile/v1/notifications/preferences` | Objet partiel de préférences | `200 { preferences, hasPushDevice }` | Met à jour l'heure préférée, le jour de revue hebdo et les types de rappels. |
| `POST /api/mobile/v1/notifications/essai` | — | `200 { ok: true, message: "..." }` | Déclenche un envoi push d'essai immédiat (limité à 3 essais par heure). |
| `GET /api/mobile/v1/notifications` | — | `200 { notifications, unreadCount }` | Liste in-app des notifications récentes reçues par le compte. |
| `POST /api/mobile/v1/notifications/{id}/lu` | — | `200 { ok: true }` | Marque une notification in-app comme lue. |
| `POST /api/mobile/v1/notifications/tout-lire` | — | `200 { ok: true }` | Marque toutes les notifications in-app comme lues. |

---

## 7. Mes 6 Plans & Progression (`/api/mobile/v1/plans`)

| Méthode & URL | Corps / Query | Réponse | Notes |
|---|---|---|---|
| `GET /api/mobile/v1/plans` | — | `200 { overallPercent, completedPlansCount, totalPlansCount, plans }` | Vue d'ensemble des 6 plans, pourcentage global et statut verrouillé/déverrouillé. |
| `GET /api/mobile/v1/plans/{key}` | — | `200 { plan: { key, label, question, desc, icon, locked, webPath, progress, content } }` | Contenu riche et détaillé pour `VIE`, `CROISSANCE`, `DEVELOPPEMENT`, `HYGIENE`, `INTENDANCE`, `TRANSMISSION`. Renvoie `403 pro_required` si le plan nécessite PRO et que le compte est gratuit. |

---

## 8. Mes tests & Mon profil KIINI SAWA (`/api/mobile/v1/tests`)

| Méthode & URL | Corps / Query | Réponse | Notes |
|---|---|---|---|
| `GET /api/mobile/v1/tests` | — | `200 { groups, synthesisSummary }` | Catalogue des tests groupés (Personnalité, Motivations et dons, Auto-évaluations) avec statut d'avancement pour chaque test. |
| `GET /api/mobile/v1/tests/{slug}/questions` | — | `200 { slug, name, bank, scale, items, totalCount }` | Questions d'un test spécifique (blocs forcé DISC ou échelles de 1 à 5 / 1 à 10). Renvoie `403 pro_required` si le test nécessite PRO pour un compte gratuit. |
| `POST /api/mobile/v1/tests/{slug}/reponses` | `{ answers: { [key]: value }, durationSeconds? }` | `201 { ok: true, id, headline, report }` | Enregistre la passation, calcule les scores, persiste l'évaluation et alimente les plans. |
| `GET /api/mobile/v1/tests/{slug}/rapport` | `?id=...` | `200 { id, slug, headline, instrument, scores, details }` | Rapport détaillé du test avec dimensions mesurées, points forts, vigilances, figure biblique et pistes d'action. |
| `GET /api/mobile/v1/tests/synthese` | — | `200 { locked, enough, count, done, missing, identity?, vocation?, convergence?, resources?, fragile?, priorities?, insights? }` | Synthèse croisée de l'ensemble des tests (« Mon profil KIINI SAWA »). Renvoie un aperçu sans prix si non PRO, ou le profil complet si PRO. |


