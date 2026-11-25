# Audit global KIINI SAWA — 24 novembre 2026

Audit complet couvrant le site web (`web/`, commit `968bebc`) et l'application mobile (`mobile-v1/`, commit `f4c3aba`), à l'issue des livraisons des 23 et 24 novembre 2026 : Académie Pratique (lots 1 à 4), Boussole 360 unifiée, Sanctuaire du Couple web + mobile + textes éditables, Redevabilité automatique, Feedback nourricier (3 témoins), Date de naissance / anniversaire, et messages « Saviez-vous que… ».

**Méthode** : vérification des suites de tests complètes, validation du schéma Doctrine, relecture ciblée du code sensible (sécurité, droits PRO, routage mobile, API, notifications) et évaluation produit.

---

## Verdict synthétique

| Axe | Note | Résumé |
|---|---|---|
| **Sécurité** | **9 / 10** | Socle très solide : vidéos encadrées (CSP + `youtube-nocookie` / Vimeo `dnt=1`), Markdown sûr, droits PRO vérifiés côté serveur, CSRF et limiteurs de fréquence en place, zéro prix dans l'app mobile. |
| **Technique** | **9 / 10** | Architecture modulaire propre, logique métier partagée entre web et mobile, **571 / 571** tests Symfony, **43 / 43** tests mobile, **0** erreur TypeScript, schéma Doctrine synchronisé. |
| **Cohérence fonctionnelle** | **8,5 / 10** | Lexique respecté et verrouillé par des tests automatiques des deux côtés ; 2 petits raccords mobile à finir (`notifyTips` et routage natif de `/academie` et `/boussole`). |
| **Pertinence fonctionnelle** | **7,5 / 10** *(potentiel 9,5 / 10)* | Chaque brique a du sens, mais le produit compte désormais plus de 15 modules majeurs. Le risque n°1 n'est plus technique : il faut geler les nouvelles fonctions, remplir l'Académie de vrais cours et publier l'application mobile. |

---

## Contrôles exécutés

| Contrôle | Résultat |
|---|---|
| Schéma Doctrine (`php bin/console doctrine:schema:validate --skip-mapping`) | **OK** (synchronisé) |
| Suite complète Symfony (`php bin/phpunit`) | **571 / 571** (10 680 assertions) |
| Moteur Boussole 360 (`node web/tests/boussole-engine.test.mjs`) | **15 / 15** |
| Application mobile — TypeScript (`npx tsc --noEmit`) | **0 erreur** |
| Application mobile — tests (`npx vitest run`) | **43 / 43** (9 fichiers) |
| État Git `web/` | Propre, synchronisé avec `origin/main` (`968bebc`) |
| État Git `mobile-v1/` | Propre localement, **1 commit d'avance sur `origin/main`** (`f4c3aba` non poussé) |

---

## 1. Audit de sécurité

### Points solides vérifiés
- **Académie — Vidéos (`src/Academie/Service/VideoEmbed.php` + `SecurityHeadersSubscriber.php`)** :
  - seuls YouTube et Vimeo sont acceptés ; seul l'identifiant vidéo est stocké en base ;
  - le lecteur est reconstruit côté serveur en `youtube-nocookie.com` et `player.vimeo.com` (`dnt=1`), et chargé uniquement au clic de l'apprenant ;
  - la directive CSP `frame-src` n'autorise que ces deux origines.
- **Académie — Rendu Markdown (`src/Academie/Service/LessonMarkdown.php`)** : `Parsedown` en `safeMode` (HTML brut échappé, liens `javascript:`/`data:` neutralisés), liens externes ouverts avec `rel="noopener noreferrer"`.
- **Académie — Pont vers les 6 plans (`src/Academie/Service/AcademyPlanBridge.php`)** :
  - vérification systématique des droits via `Entitlements` (plans PRO, limite de rituels gratuits, Sanctuaire du Couple ouvert) ;
  - idempotence sécurisée par contrôle de propriété avant mise à jour ;
  - aucun écrasement de la raison d'être ni de la Vision.
- **Boussole 360 (`BoussoleController.php` + `MobileBoussoleController.php`)** : jeton CSRF (`X-CSRF-Token`) sur le site + limiteurs de fréquence (10 passations/heure par compte, 30/heure par IP).
- **Redevabilité (`AccountabilityService.php`)** : limite à 3 invitations/jour par compte (gratuit et PRO), pause automatique si l'objectif ou le rituel cible est supprimé, pas d'écriture sur GET.
- **Feedback nourricier (`FeedbackController.php`)** : CSRF sur les actions POST, contrôle strict de propriété (404 sur les demandes d'autrui), seuil d'anonymat (`MIN_FOR_DETAIL`), limiteur de fréquence sur le formulaire public.
- **Date de naissance & RGPD (`BirthDate.php`)** : âge minimum 15 ans, accord parental 15–17 ans, donnée privée jamais exposée à d'autres membres.
- **Conformité Apple / Google** : aucun prix ni lien de paiement dans `mobile-v1/` ni dans les endpoints mobiles.

### Points d'attention (faibles, non bloquants)
- **S1 · CSP `'unsafe-inline' 'unsafe-eval'`** : toujours nécessaires tant qu'Alpine.js est utilisé en version standard (dette connue et documentée dans `SecurityHeadersSubscriber.php`).
- **S2 · Sauvegardes en production** : la richesse des données utilisateur (plans, journal, notes d'Académie, engagements de redevabilité, couple) rend indispensable la vérification périodique des sauvegardes automatiques de la base Hostinger.

---

## 2. Audit technique

### Points solides
- **Modularité** : séparation claire par domaine (`Academie`, `Boussole`, `Feedback`, `Notification`, `Social`, `Couple`, `Compte`, `Mobile`).
- **Logique métier unifiée** : l'API mobile réutilise directement les services du site (`AcademyCatalog`, `AcademyLearning`, `AcademyPlanBridge`, `BoussoleRecorder`, `FeedbackService`, `AccountabilityService`, `CoupleSanctuaryService`), évitant la dérive entre web et mobile.
- **Contrat API harmonisé** : les routes de l'API mobile (y compris le Sanctuaire du Couple et l'Académie) sont désormais en français et homogènes.
- **Tests de garde-fous** : parité Boussole JS/PHP, anti-doublon `SiteTextsTest`, `LexiconTest` (web et mobile), `DesignRulesTest` (zéro émoji), `SecurityGuardsTest`.

### Accrocs techniques relevés (petits, faciles à corriger)
- **T1 · Routage des notifications mobile incomplet (`mobile-v1/src/notifications/routing.ts`)** :
  - `resolveNotificationRoute()` ne mappe pas encore `/academie` (`/academie/...`) ni `/boussole`.
  - Conséquence : toucher une notification de l'Académie (leçon du jour) ou un message « Saviez-vous que… » vers la Boussole ouvre le **navigateur web** (où le membre n'est pas connecté) au lieu des écrans natifs `app/academie/` et `app/boussole/` déjà présents.
- **T2 · Dépôt `mobile-v1/` en avance d'un commit local** :
  - le commit `f4c3aba` (date de naissance mobile) n'est pas encore poussé sur `origin/main`.

---

## 3. Audit de cohérence fonctionnelle

### Points solides
- **Lexique maîtrisé** : les 6 plans dans l'ordre exact, le Sanctuaire du Couple séparé des 6 plans et toujours nommé « Sanctuaire du Couple », « Rituels », « Score de cohérence », vouvoiement partout, zéro émoji.
- **Vitrine honnête** : les captures sont clairement présentées comme le site sur téléphone, et l'application mobile est annoncée « bientôt disponible sur l'App Store et Google Play ».
- **Accueil mobile sobre** : une seule carte de contexte (`src/home/contextCard.ts`), affichée seulement quand il y a une action utile aujourd'hui.
- **Messages « Saviez-vous que… » (`TipSender.php`)** : dosage exemplaire (pas avant J+15, au plus 1 tous les 14 jours, jamais sur une fonction déjà utilisée, silence après 60 jours d'inactivité, espacement autour des autres e-mails importants, pas de conseil Académie tant qu'aucun parcours n'est publié).

### Écarts relevés
- **C1 · Réglage « Saviez-vous que… » absent dans l'application mobile** :
  - l'API (`MobileNotificationController`) gère `notifyTips`, mais `mobile-v1/app/notifications.tsx` et `mobile-v1/src/types/api.ts` n'exposent pas encore cet interrupteur (contrairement à `notifyBirthday`). Un utilisateur mobile ne peut pas désactiver ces messages depuis l'application.
- **C2 · Écrans mobiles récents non éprouvés visuellement sur appareil réel** :
  - Académie mobile, Feedback nourricier mobile, Boussole 360 mobile, Redevabilité mobile, calendrier natif de retraite et Date de naissance mobile sont compilés et testés par code, mais pas encore vérifiés visuellement sur téléphone ou émulateur.
- **C3 · Relecture éditoriale du Sanctuaire du Couple** :
  - les 143 textes sont désormais modifiables dans `/admin/textes`, mais les textes d'origine (dont 1 Corinthiens 13:4-7 condensé) restent à relire par le fondateur.

---

## 4. Audit de pertinence fonctionnelle

### Ce qui crée une vraie valeur différenciante
1. **Ma Boussole 360 unifiée** : porte d'entrée gratuite crédible, enregistrée dans le compte et reliée à la recommandation de parcours.
2. **Redevabilité automatique** : répond à un vrai problème humain (« celui qui décroche ne demande pas d'aide »), rarement aussi bien traité.
3. **Académie Pratique reliée aux 6 plans** : positionnement « cabinet d'accompagnement » qui se distingue nettement des plateformes de cours classiques.
4. **Feedback nourricier (3 témoins) + Sanctuaire du Couple** : alignés sur l'ADN du livre et très forts pour l'offre Duo et la recommandation organique.
5. **« Saviez-vous que… »** : permet de faire découvrir la richesse de la plateforme progressivement, sans alourdir le premier écran.

### Risques produit à surveiller
- **P1 · Obésité fonctionnelle** : la plateforme dépasse maintenant **15 modules majeurs**. Ajouter de nouvelles fonctionnalités avant d'avoir des retours de vrais utilisateurs augmenterait la complexité sans prouver la valeur.
- **P2 · Académie vide tant que les premiers parcours ne sont pas rédigés** : le studio `/admin/academie` est prêt, mais la perception haut de gamme dépend désormais du **contenu éditorial** (1 à 2 parcours phares rédigés par le fondateur), pas d'une option technique supplémentaire.
- **P3 · Application mobile non encore publiée** : l'application `mobile-v1/` est techniquement aboutie mais toujours sur le poste local (`.env` pointe encore sur une adresse locale).

---

## 5. Plan d'action recommandé (par ordre de priorité)

| Priorité | Action | Type | Effort |
|---|---|---|---|
| **1** | Ajouter `notifyTips` dans `mobile-v1/app/notifications.tsx` + `src/types/api.ts` (C1) et router `/academie` et `/boussole` vers les écrans natifs dans `mobile-v1/src/notifications/routing.ts` (T1). | Mobile | 15 min |
| **2** | Pousser `mobile-v1/` sur GitHub (`origin/main`) (T2). | Git | 1 min |
| **3** | **Geler toute nouvelle fonctionnalité** le temps de valider le produit sur le terrain (P1). | Produit | Décision |
| **4** | Rédiger **1 ou 2 vrais parcours phares** dans `/admin/academie` (dont 1 parcours gratuit sur le Plan de Vie) et relire les textes du Sanctuaire du Couple dans `/admin/textes` (C3, P2). | Fondateur / Contenu | 1 à 2 jours |
| **5** | Ouvrir `mobile-v1/` sur un vrai téléphone Android/iOS pour contrôler visuellement les écrans récents, puis configurer `EXPO_PUBLIC_API_URL=https://kiinisawa.com` et lancer le premier build Play Store (C2, P3). | Mobile / Terrain | Demi-journée |
| **6** | Observer 20 à 50 vrais utilisateurs actifs (taux de retour à J+7, usage réel de la Boussole, de l'Académie et de la Redevabilité) avant de rouvrir un nouveau chantier fonctionnel. | Produit | Continu |
