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
| `PATCH /api/mobile/v1/moi` | `{ firstName?, lastName?, city?, country?, profession?, bio?, dateOfBirth?, birthDatePromptDismissed? }` | `200 { user }` | Mise à jour partielle du profil. `dateOfBirth` « AAAA-MM-JJ » (`null` ou vide : effacée) ; moins de 15 ans ou date impossible → `422` avec un message en français. `birthDatePromptDismissed: true` écarte l'invitation de l'accueil. `user.profile` renvoie `dateOfBirth` (à la personne seule) et `askBirthDate`. |
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
| `PATCH /api/mobile/v1/notifications/preferences` | Objet partiel de préférences | `200 { preferences, hasPushDevice }` | Met à jour l'heure préférée, le jour de revue hebdo et les types de rappels (dont `notifyBirthday`, message d'anniversaire, et `notifyTips`, messages « Saviez-vous que… »). |
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

---

## 9. Agenda & Tâches (`/api/mobile/v1/agenda`)

| Méthode & URL | Corps / Query | Réponse | Notes |
|---|---|---|---|
| `GET /api/mobile/v1/agenda` | `?date=YYYY-MM-DD` ou `?start=...&end=...` | `200 { date, total, tasks: [...] }` | Liste des tâches de l'agenda pour une date ou un intervalle donné, avec heure de début/fin, indicateur journée et liaison aux objectifs. |
| `POST /api/mobile/v1/agenda` | `{ title, date, isAllDay?, startTime?, endTime?, description?, growthGoalId? }` | `201 { id, title, description, date, isAllDay, isCompleted, startTime, endTime, linkedGoal, linkedGoalId }` | Crée une tâche dans l'agenda. |
| `PATCH /api/mobile/v1/agenda/{id}` | `{ isCompleted?, title?, description?, date?, startTime?, endTime? }` | `200 { ...tâche modifiée }` | Met à jour une tâche existante ou bascule son état de complétion. |
| `DELETE /api/mobile/v1/agenda/{id}` | — | `200 { success: true }` | Supprime une tâche de l'agenda. |

---

## 10. Revues & Rythmes (`/api/mobile/v1/revues`)

| Méthode & URL | Corps / Query | Réponse | Notes |
|---|---|---|---|
| `GET /api/mobile/v1/revues/rythmes` | — | `200 { rhythms: [...] }` | Liste des 5 respirations temporelles de KIINI SAWA (Jour, Semaine, Mois, Saison, An) avec état (`done`, `late`, `todo`, `later`) et actions. |
| `GET /api/mobile/v1/revues/hebdo` | — | `200 { weekStart, isCompleted, review?, plans, itemsByPlan, habitsRegularity, journalEntriesCount, recentReviews }` | Données de la revue hebdomadaire de la semaine en cours : victoires, difficultés, leçons, régularité des rituels et items des 4 plans à évaluer. |
| `POST /api/mobile/v1/revues/hebdo` | `{ wins?, struggles?, lesson?, priorities?: string[], score?: {}, progress?: {}, note?: {} }` | `200 { success: true, review: {...} }` | Enregistre la revue hebdomadaire, applique les notes et progressions aux plans stratégiques, et alimente le journal. |
| `GET /api/mobile/v1/revues/mensuelle` | — | `200 { isProRequired, month?, isCompleted?, review? }` | Données de la revue mensuelle (réservée aux membres Pro). |
| `POST /api/mobile/v1/revues/mensuelle` | `{ progress?, stagnation?, blocker?, adjustment?, decision? }` | `200 { success: true, review: {...} }` | Enregistre la revue mensuelle approfondie. |

---

## 11. Partenaires, Défis & Couple (`/api/mobile/v1/partenaires`, `/api/mobile/v1/defis`, `/api/mobile/v1/couple`)

| Méthode & URL | Corps / Query | Réponse | Notes |
|---|---|---|---|
| `GET /api/mobile/v1/partenaires` | — | `200 { totalActive, limit, isLimitReached, active: [...], pendingReceived: [...], pendingSent: [...] }` | Liste des partenaires de redevabilité actifs et des invitations reçues/envoyées. |
| `POST /api/mobile/v1/partenaires/invitation` | `{ email }` | `200 { success: true }` | Envoie une invitation de partenariat de redevabilité (soumis à la limite de la formule). Même réponse si l'adresse n'a pas de compte, pour ne pas révéler son existence. |
| `POST /api/mobile/v1/partenaires/{id}/repondre` | `{ action: 'ACCEPT' \| 'REJECT' }` | `200 { success: true, status }` | Accepte ou refuse une demande de partenariat reçue. Un refus supprime la demande (comme sur le site) : le demandeur pourra réinviter plus tard. |
| `GET /api/mobile/v1/defis` | — | `200 { total, challenges: [...] }` | Liste des défis collectifs actifs et avancement de l'utilisateur. |
| `GET /api/mobile/v1/couple` | — | `200 { hasCouple, isActive?, partnerName?, inviteCode?, vision?, verse? }` | Données de l'espace couple partagé entre conjoints. |

---

## 12. La Méthode (Livre KIINI SAWA) (`/api/mobile/v1/methode`)

| Méthode & URL | Corps / Query | Réponse | Notes |
|---|---|---|---|
| `GET /api/mobile/v1/methode/chapitres` | — | `200 { total, hasFullAccess, chapters: [...] }` | Sommaire des 28 chapitres du livre KIINI SAWA avec temps de lecture et accès libre/pro. |
| `GET /api/mobile/v1/methode/chapitres/{slug}` | — | `200 { slug, title, excerpt, minutes, previous, next, isPublic, isProRequired, hasFullAccess, contentHtml, sanctuary }` | Contenu d'un chapitre : texte intégral (si Pro ou public) ou extrait pratique « L'essentiel » (si gratuit). Aucune mention de prix ni paiement. |




## 13. Sanctuaire du Couple (`/api/mobile/v1/couple/...`, RFC-001)

Toutes ces routes demandent un couple actif et utilisable (les deux conjoints PRO ou Duo) ; sinon `403` avec `code` `no_couple`, `couple_pending` ou `couple_locked`. Mêmes règles que sur le web (`CoupleSanctuaryService`).

| Route | Corps | Réponse | Rôle |
|---|---|---|---|
| `GET /couple/accueil` | — | `200 { couple, activeChallenge, activeMeditation, retreat, counts, nextReviewDate, reviewDoneThisMonth, pingPresets }` | Vue d'ensemble du Sanctuaire. |
| `GET /couple/defis` | — | `200 { active, catalog }` | Défi en cours et catalogue. |
| `POST /couple/defis/commencer` | `{ code }` | `201 { challenge }` | Lance un défi (remplace celui en cours). |
| `POST /couple/defis/valider-jour` | `{ note? }` | `200 { streakDays, challenge }` ou `409 { error }` | Valide le jour pour le conjoint connecté. Le jour suivant s'ouvre **le lendemain** de la double validation (`challenge.locked`, `challenge.rhythmMessage`), sauf pendant un temps de retraite confirmé. |
| `GET /couple/meditations` | — | `200 { active, catalog }` | Méditation en cours et catalogue. |
| `POST /couple/meditations/commencer` | `{ code }` | `201 { meditation }` | Ouvre un parcours. |
| `POST /couple/meditations/valider-etape` | — | `200 { streakDays, meditation }` ou `409 { error }` | Le conjoint valide l'étape : elle n'avance que quand **les deux** l'ont validée (`stepDoneByMe`, `stepDoneByPartner`), et l'étape suivante s'ouvre le lendemain (`locked`), sauf pendant une retraite. |
| `GET /couple/retraite` | — | `200 { retreat \| null }` | Temps de retraite en attente, à venir ou en cours : `{ id, status: pending\|upcoming\|active\|finished, startsOn, endsOn, note, proposedByMe, proposedBy, canConfirm }`. |
| `POST /couple/retraite/proposer` | `{ startsOn, endsOn, note? }` (AAAA-MM-JJ) | `201 { retreat }`, `422` ou `409` | Un conjoint propose ; dates choisies par le couple, fin obligatoire, pas de début dans le passé. |
| `POST /couple/retraite/confirmer` | — | `200 { retreat }` ou `409` | L'autre conjoint confirme (impossible de confirmer sa propre proposition). |
| `POST /couple/retraite/arreter` | — | `200 { retreat: null }` | L'un ou l'autre arrête la retraite ou retire la proposition. |
| `POST /couple/signal` | `{ preset, message? }` | `200 { sent: true }` | Signal au conjoint (notification). |
| Projets, intentions, revue, rapport | — | — | `GET/POST /couple/projets`, `PATCH/DELETE /couple/projets/{id}`, `GET/POST /couple/intentions`, `DELETE /couple/intentions/{id}`, `GET/POST /couple/revue`, `GET /couple/rapport`. |

## 14. Ma redevabilité (`/api/mobile/v1/redevabilite`)

| Route | Corps | Réponse | Rôle |
|---|---|---|---|
| `GET /redevabilite` | — | `200 { commitments: [{ id, targetLabel, targetType, partnerName, status, createdAt, alerts: { habitDays, goalDays, inactiveDays }, nextHelpAllowedOn, stats: { messages, responses, responseRate, avgResponseHours, resumed }, history: [...] }], asPartner, canCreate, createUrl }` | Engagements de la personne et rôles de partenaire. La création se fait sur le site (`createUrl`), avec l'aperçu des e-mails et le consentement. |
| `POST /redevabilite/{id}/coup-de-main` | `{ message? }` | `200 { commitment }` ou `409 { error }` | « J'ai besoin d'un coup de main » : e-mail au partenaire, copie à la personne. Au plus un message tous les 7 jours (alertes comprises). |
| `POST /redevabilite/{id}/pause\|reprendre\|arreter` | — | `200 { commitment }` ou `404` | Gestion par la personne (engagement d'un autre compte : `404`). |

Les alertes automatiques partent du serveur chaque matin, sans action de l'application. `GET /revues/hebdo` contient aussi `accountability` (résumé des 7 derniers jours, ou `null`).


## 15. L'Académie (`/api/mobile/v1/academie`)

Mêmes règles que le site (`docs/modules/academie.md`) : catalogue, accès gratuit ou PRO, pont vers les plans sans doublon,
notes dans le Journal, rythme par e-mail. Clés en français, `no-store`, **aucun prix** (un parcours PRO est seulement
signalé `reservePro: true` ; l'ouvrir renvoie `403 { code: "pro_required" }`).

| Route | Corps | Réponse | Rôle |
|---|---|---|---|
| `GET /academie` | — | `200 { parcours: [{ adresse, titre, accroche, lieu, libelleLieu, icone, gratuit, reservePro, enRevision, nombreLecons, minutes, progression: { faites, total, pourcentage } \| null, termine }], enCours: [adresse], recommande: { parcours, plan, libellePlan } \| null, plans: [{ cle, libelle }], couple: { cle, libelle } }` | Catalogue : parcours publiés et ceux déjà commencés ; recommandation de la dernière Boussole 360 ; le Sanctuaire du Couple à part des 6 plans. |
| `GET /academie/{adresse}` | — | `200 { parcours: { …résumé, presentationHtml, modules: [{ titre, lecons: [{ adresse, titre, minutes, video, faite }] }], prochaineLecon, rythme: { mode, enPause }, proposerBoussole } }` ou `404` | Un parcours. |
| `POST /academie/{adresse}/rythme` | `{ rythme: "FREE" \| "DAILY" \| "THREE_PER_WEEK" }` | `200 { rythme }` ou `400` | Choisir ou reprendre son rythme (inscrit au parcours). |
| `GET /academie/{adresse}/{lecon}` | — | `200 { lecon: { adresse, titre, parcours, module, position, total, minutes, faite, progression, texteHtml, video: { fournisseur, lien } \| null, questions: [{ id, question, plusieurs, reponses: [{ id, libelle }], bonnes, explication }], pratique, notes, plans, precedente, suivante } }` | Une leçon (inscrit au parcours à la première ouverture). `pratique.type` vaut `exercice` (consigne, cible, bouton, libellés, `choix`, `blocage`) ou `point` (destinations au choix) ; `dejaEnregistre` et `reponse` pour refaire l'exercice. La vidéo s'ouvre dans le navigateur (`lien`). |
| `POST /academie/{adresse}/{lecon}/terminer` | `{ mode: "plan" \| "sans", texte?, precisions?, choix?, destination? }` | `200 { enregistreDans, leconSuivante, parcoursTermine, progression, redevabilite: { cible, question } \| null }` ou `422 { code: "PRACTICE_REFUSED", error }` | Terminer la leçon ; en mode `plan`, avec l'accord du membre, enregistrer la réponse dans le plan (mise à jour si déjà faite). `redevabilite` : proposer un Partenaire de Redevabilité (formulaire du site `/social/redevabilite/nouveau?cible=…`). |
| `POST /academie/{adresse}/{lecon}/notes` | `{ contenu, plan? }` | `201 { note: { id, contenu, plan, libellePlan, creeeLe } }` ou `422` | Note de leçon, enregistrée dans le Journal (carnet « Académie »). |
| `DELETE /academie/notes/{id}` | — | `200 { supprime: true }` ou `404` | Supprimer sa note (celle d'un autre compte : `404`). |

L'application garde le catalogue, les parcours et les leçons ouverts (clés `academie_…` du stockage local) pour les relire
hors connexion ; terminer une leçon et écrire une note demandent le réseau.

---

## 16. Feedback nourricier (`/api/mobile/v1/feedback`)

Même lien que sur le site (7 jours, prolongeable) ; les proches répondent toujours sur le site (`/avis/{jeton}`).

| Méthode & URL | Corps | Réponse | Notes |
|---|---|---|---|
| `GET /feedback` | — | `200 { request, messages, summary, past, needsFirstName, minForDetail, durationDays }` | `request` = dernière demande (ouverte, terminée ou fermée) ou `null` ; `messages` = `{ tu, vous }` prêts à partager (lien compris), `null` si le lien n'accepte plus de réponses. |
| `POST /feedback` | `{ forceRealName?, firstName? }` | `201` (même forme) ou `422 first_name_required` | Crée un lien (ou un nouveau lien après la fin du précédent). `firstName` est exigé si le profil n'en a pas. |
| `POST /feedback/prolonger` | `{}` | `200` ou `409` | Une semaine de plus (jamais plus de 21 jours devant soi) ; impossible pour une demande fermée. |
| `POST /feedback/fermer` | `{}` | `200` | Le lien ne fonctionne plus ; les résultats restent. |
| `GET /feedback/demandes/{id}` | — | `200 { createdAt, summary }` ou `404` | Résultats d'une demande passée (celle d'un autre compte : `404`). |

`summary` : `{ count, detail, qualities: [{ label, count }], words: [{ label, count }], notWords: [{ label, count }], strengths: [{ text, from }], improvements: [{ text, from }] }` ; le
détail n'apparaît qu'à partir de `minForDetail` réponses (anonymat), sauf si le prénom était demandé. Les notifications
`/mes-tests/feedback` ouvrent l'écran natif `app/feedback`.
