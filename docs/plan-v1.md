# Plan complet V1 — produit, cohérence, vente et technique

Établi le 15/11/2026. Il compare la V1 Symfony (`web/`) à l'ancienne version Next.js (`web-v0-nextjs/`, en production) et au livre KIINI ALIGN (`../docs/livre/`).

---

## 1. Réponses courtes

| Question | Réponse |
|---|---|
| **Ai-je copié toutes les fonctionnalités de la version précédente ?** | **Non, environ la moitié.** Le cœur est là : Plan de Vie, wizard, carte stratégique, analyse de cohérence, rituels, planner, journal, tests de personnalité. Il manque les comptes, l'abonnement et l'offre gratuite/PRO, les défis, le coaching, le feedback 360°, la revue hebdomadaire complète, la revue mensuelle, les notifications, le profil, l'export PDF, le partage en lecture et l'administration des utilisateurs (détail au §2). |
| **L'offre est-elle cohérente ?** | **Pas encore.** Le cœur méthodologique est fort, mais l'application parle trois langues différentes (livre, menu, URL), présente des plans qui se chevauchent, et l'utilisateur tombe sur des impasses : liens morts, « Bientôt disponible », options de partage sans effet (§3). |
| **Est-ce vendable aujourd'hui ?** | **Non.** Personne ne peut s'inscrire ni payer, il n'y a ni CGU ni politique de confidentialité, alors que l'application stocke des données très personnelles (journal, foi, personnalité). Une fois les phases 1 à 5 ci-dessous réalisées, **oui** : la proposition de valeur est réelle et différenciante (§4). |

---

## 2. Parité fonctionnelle : version précédente → V1

Légende : ✅ migré · 🟠 partiel · ❌ absent. La colonne « Proposition » est **à arbitrer par toi** :
- **MVP** : indispensable pour vendre ;
- **V1.x** : juste après le lancement ;
- **Plus tard** : selon la demande ;
- **Abandon** : à ne pas reprendre.

### 2.1 Comptes et accès

| Fonctionnalité (version précédente) | V1 | Proposition |
|---|---|---|
| Inscription (email + mot de passe) | ❌ | **MVP** |
| Vérification de l'email par code à 6 chiffres | ❌ | **MVP** |
| Mot de passe oublié / réinitialisation | ❌ | **MVP** |
| Connexion Google | ❌ | V1.x |
| Onboarding guidé (`OnboardingWizard`, 253 lignes) | 🟠 Un seul bouton « Commencer » | **MVP** (à repenser, §3.3) |
| Profil (`/profile`) et historique des plans | ❌ (le lien du menu mène à une 404) | **MVP** pour le profil, V1.x pour l'historique |
| Préférences de notification | ❌ | V1.x |

### 2.2 Plan de Vie et stratégies

| Fonctionnalité | V1 | Proposition |
|---|---|---|
| Wizard Plan de Vie (raison d'être, balance interne, passions, talents, tests, vision, objectifs BE/DO/HAVE/GIVE) | ✅ (mais il **efface puis recrée** les données à chaque sauvegarde) | **MVP** : corriger |
| Wizard Plan de Croissance (3 axes) | ✅ (intégré au wizard global) | MVP |
| Hub stratégique : Croissance, Développement, Transmission, Hygiène | ✅ `/mes-strategies` | MVP |
| Carte stratégique (Mermaid) | ✅ | MVP (c'est un argument de vente) |
| Analyse de cohérence et score d'alignement | ✅ | MVP (le moment où l'utilisateur comprend la valeur) |
| Lecture seule et impression PDF des plans (`/view/*`, `/print/*`) | 🟠 Envoi par email seulement | **MVP** pour le PDF (fonction PRO dans l'ancienne version) |
| Commentaires et suggestions de partenaires sur un plan | 🟠 Suggestions dans Social, pas sur le plan lui-même | V1.x |
| Pages dédiées Balance interne / Passions | 🟠 Fondues dans le wizard | OK, pas nécessaire |

### 2.3 Pratique quotidienne

| Fonctionnalité | V1 | Proposition |
|---|---|---|
| Rituels / habitudes (suivi, ancrage, fréquence) | ✅ | MVP |
| Planner jour / semaine / mois | ✅ | MVP |
| Vue année du planner (fonction PRO) | ❌ | Plus tard |
| Intention du jour | ✅ | MVP |
| Synchronisation Google Calendar | ❌ | Plus tard |
| Journal et collections | ✅ | MVP |
| Saisie rapide (*Quick Capture*) sur l'accueil | ✅ | MVP |
| Accueil « flux du jour » (ancrage de la raison d'être, trajectoire de croissance) | 🟠 Dashboard plus simple, citation codée en dur | **MVP** : citation du jour réelle, raison d'être affichée |
| Listes personnelles (`/lists`, 700 lignes) | ❌ | Plus tard |

### 2.4 Bilans et revues

| Fonctionnalité | V1 | Proposition |
|---|---|---|
| Revue hebdomadaire sur les 5 plans + journal (`WeeklyReviewWizard`, 583 lignes) | 🟠 Plan de Croissance seulement (3 axes) | **MVP** : l'étendre |
| Revue mensuelle (fonction PRO) | ❌ | V1.x |
| Analytics et graphiques avancés (fonction PRO) | ❌ | V1.x |

### 2.5 Découverte de soi

| Fonctionnalité | V1 | Proposition |
|---|---|---|
| Tests (DISC, MBTI, Ennéagramme, Big 5, intelligences multiples, hygiène) | ✅ | MVP |
| Analyse détaillée d'un test (`TestAnalysisView`, 720 lignes) | 🟠 Page de résultat plus courte | V1.x |
| Feedback de l'entourage sur sa personnalité (`/discovery/community`) | ❌ | V1.x |
| Feedback 360° par lien externe (`/f/[token]`) | ❌ | V1.x (fort pour le coaching) |

### 2.6 Social et accompagnement

| Fonctionnalité | V1 | Proposition |
|---|---|---|
| Connexions, invitations, réponses | ✅ | MVP |
| Partage granulaire (10 options) | 🟠 2 options réellement effectives sur 10 | **MVP** : soit les rendre effectives, soit en afficher moins |
| Tableau de bord d'un partenaire (`/social/[userId]`) | 🟠 Endpoint partiel | MVP |
| Défis collectifs (`ChallengesView`, 929 lignes) | ❌ Onglet « Bientôt disponible » | V1.x |
| Coaching / mentorat (consentement, messages, suggestions) | ❌ | V1.x, **MVP si offre B2B** (§4.3) |

### 2.7 Monétisation et administration

| Fonctionnalité | V1 | Proposition |
|---|---|---|
| Offres FREE / PRO / TEAM, essai de 14 jours | ❌ (prix en configuration, jamais appliqués) | **MVP** |
| 30 interrupteurs de fonctionnalités et 7 limites gratuites configurables | ❌ | **MVP** (reprendre le modèle) |
| Page Upgrade | ❌ | **MVP** |
| Paiement Stripe | ❌ (n'existait pas non plus dans l'ancienne version) | **MVP** |
| Admin : utilisateurs (édition, revue), analytics, revenus, citations, modèles d'email, feedback app, démo | 🟠 Dashboard, configuration et tests seulement | **MVP** pour utilisateurs, citations et modèles d'email ; V1.x pour le reste |
| Centre de notifications et emails de rappel | ❌ (notifications créées mais jamais affichées) | **MVP** (sans rappels, pas de rétention) |
| Multilingue FR/EN | ❌ | Plus tard |
| Installation mobile (PWA) | ❌ | Plus tard (l'app native existe) |

### 2.8 Ce que la V1 apporte en plus

- Une structure de données propre pour **5 plans** (Développement, Hygiène et Transmission ont leurs propres entités).
- Le calcul automatique des scores des tests et l'administration du référentiel de questions.
- L'organisation du code par module, et la documentation intégrée (`/admin/doc`).

---

## 3. Cohérence de l'offre

### 3.1 Trois vocabulaires pour la même méthode

| Livre KIINI ALIGN | Menu / écrans V1 | URL / code |
|---|---|---|
| **2 plans** : Plan de Vie (la Structure) et Plan de Croissance (le Système, 3 axes) | **5 plans** : Vie, Croissance, Développement, Hygiène de vie, Transmission | `croissance`, `developpement`, **`contribution`**, **`discipline`** |
| Pas de chapitre Développement, Hygiène ni Transmission | « Plan d'Hygiène de Vie », « Transmission » | `plan_dhygiene`, `PlanDeTransmission`, `contribution_plan` |

Même l'ancienne version mélangeait déjà « Transmission » et « Contribution », « Hygiène » et « Discipline ».

**Action :** fixer **un lexique unique** dans `docs/charte-projet.md` (nom affiché, nom court, URL), puis l'appliquer partout : menus, titres, URL, emails, livre.

### 3.2 Des plans qui se chevauchent

- L'axe **Compétence & Connaissance** du Plan de Croissance (le livre, chapitre 9) et le **Plan de Développement** (compétences, ressources, carrière) couvrent le même sujet.
- L'axe **Relation & Réseau** et le **Plan de Transmission** (mentorat) se recoupent aussi.
- L'utilisateur ne sait pas où saisir une compétence à acquérir.

**Décision produit à prendre** (§6) :
- **Option A — Aligner sur le livre** : 2 plans (Vie + Croissance) ; Développement, Hygiène et Transmission deviennent des sous-parties ou des « stratégies » du Plan de Croissance.
- **Option B — Assumer 5 plans** : mettre le livre à jour (nouveaux chapitres) et retirer l'axe Compétence du Plan de Croissance.

### 3.3 Le parcours utilisateur

**Aujourd'hui** : connexion (compte créé à la main) → un écran « Bienvenue » avec un bouton → un dashboard vide. L'utilisateur doit deviner qu'il faut lancer le wizard, qui compte 8 étapes avant tout bénéfice visible.

**Parcours cible** :

1. **Inscription** et vérification de l'email.
2. **Onboarding en 3 minutes** : prénom, intention, 3 valeurs, un premier objectif.
3. **Premier moment de valeur** : la **carte stratégique** et le **score de cohérence** s'affichent dès ce minimum. Le reste du wizard est proposé ensuite comme un enrichissement (« Votre plan est cohérent à 35 % : complétez votre vision pour gagner 20 points »).
4. **Premier rituel** créé et rattaché à l'objectif.
5. **Boucle d'engagement** :
   - **chaque jour** : rituels, intention, journal ;
   - **chaque semaine** : revue hebdomadaire, avec un rappel par email ou notification ;
   - **chaque mois** : revue mensuelle et évolution du score.
6. **Partage** avec un partenaire de redevabilité : c'est la rétention par le lien humain.

Aujourd'hui, les étapes 1, 2, 3 (partiellement) et 5 (pas de rappels) manquent ou sont incomplètes.

### 3.4 Les impasses à supprimer

| Impasse | Où |
|---|---|
| Liens `/profile` et `/notifications` → 404 | Menu utilisateur (`templates/base.html.twig`) |
| Onglet Défis « Bientôt disponible » | Social |
| 10 options de partage, 2 effectives | Social |
| Citation du jour codée en dur alors que l'entité `Quote` existe | Dashboard |
| « Plan de Vie » dans l'admin, qui édite le plan de l'admin lui-même | Admin |
| Un utilisateur non admin redirigé vers la doc admin (403) en revenant sur `/login` | Compte |

**Règle proposée** : ne rien afficher qui ne fonctionne pas. Un module non prêt est masqué, pas annoncé.

### 3.5 Trop de choses pour un premier contact

L'application compte 21 modules et environ 60 écrans hérités. Pour un nouvel utilisateur, **l'abondance fait fuir plus que le manque**. Le MVP vendable doit mettre en avant 5 choses : Plan de Vie, carte et score de cohérence, Rituels, Revue hebdomadaire, Partenaire. Le reste se découvre ensuite ou fait partie de l'offre PRO.

### 3.6 Un atout sous-exploité : le livre

Les 15 chapitres du livre correspondent presque un pour un aux modules :

| Chapitres du livre | Modules |
|---|---|
| 1–6 (But, Balance, Passions, Talents, Vision, Saisons) | Plan de Vie |
| 7 (Confirmations) et 9 (les 3 axes) | Plan de Croissance |
| 10 (Carte stratégique) | Stratégies |
| 11–12 (Habitudes, Enracinement) | Rituels et Planner |
| 13 (Revues) | Bilan |
| 14 (Découverte, Feedback) | Personnalité et Feedback |
| 15 (Défis) | Défis |

**Proposition** : afficher dans chaque écran l'extrait du chapitre correspondant (« Comprendre cette étape »). L'ancienne version avait une page « Méthode » (`/learn`), gratuite. L'application devient ainsi le compagnon du livre, et le livre le canal d'acquisition de l'application.

---

## 4. Vendabilité

### 4.1 Ce qui manque pour pouvoir encaisser

1. Inscription, vérification de l'email, récupération du compte.
2. **CGU, CGV, politique de confidentialité, mentions légales**. C'est obligatoire.
3. **RGPD** : export des données et suppression du compte par l'utilisateur, consentement explicite. Les données sont sensibles (spiritualité, personnalité, journal intime).
4. Stripe : abonnement mensuel et annuel, essai, factures, résiliation.
5. Limites gratuites et fonctions PRO appliquées.
6. Emails transactionnels réels (bienvenue, vérification, rappels, reçus).
7. Une page de présentation publique (landing page) avec les prix.

### 4.2 Proposition de valeur (à valider)

> « KIINI SAWA relie ce que vous êtes à ce que vous faites chaque jour. Définissez votre raison d'être et votre vision, voyez en un coup d'œil si vos habitudes servent vraiment vos objectifs (score de cohérence), et avancez avec un partenaire de redevabilité. »

Ce qui différencie KIINI SAWA de Notion, Fabulous ou Day One :
- la **cascade raison d'être → objectifs → ressources → habitudes** ;
- le **score de cohérence** ;
- la **redevabilité humaine** ;
- un **livre et une méthode** derrière l'outil.

### 4.3 Modèle économique

| Offre | Contenu | Prix (ancienne config) |
|---|---|---|
| **Gratuit** | Plan de Vie, 3 valeurs, 5 objectifs, 2 objectifs de croissance, 3 rituels, 1 partenaire, méthode | 0 € |
| **PRO** | Illimité, carte et analyse de cohérence, PDF, revues mensuelles, historique, défis, tests, coaching | 9,99 €/mois ou 79,99 €/an, essai de 14 jours |
| **TEAM / Organisation** (prévue dans le code, jamais construite) | Pour églises, réseaux de leadership, coachs : espace organisation, tableau de bord des membres, vue coach, licences groupées | **À définir** (par exemple 3 à 5 € par membre et par mois, ou un forfait annuel) |

**Recommandation** :
- Lancer **Gratuit + PRO** pour les particuliers.
- Construire **Organisation** dès que 2 ou 3 structures pilotes sont prêtes à payer. C'est la voie la plus réaliste vers un chiffre d'affaires significatif.

Le positionnement doit être tranché (§6) : **méthode chrétienne assumée** (le chapitre 1 s'intitule « But de Dieu ») ou **développement personnel généraliste**. Ce choix change la cible, les canaux de vente et les textes.

---

## 5. Plan complet par phases

Les estimations sont des jours de développement pour une personne. Les phases 0 à 5 forment le **MVP vendable**.

### Phase 0 — Décisions produit (toi, 1–2 jours)

- [ ] Positionnement : chrétien assumé ou généraliste.
- [ ] Architecture des plans : option A (2 plans, comme le livre) ou B (5 plans) — §3.2.
- [ ] Lexique unique (noms affichés, URL) — §3.1.
- [ ] Périmètre MVP : valider ou modifier la colonne « Proposition » du §2.
- [ ] Offres et prix ; faut-il prévoir l'offre Organisation dès le lancement ?
- [ ] Identifier 2 ou 3 organisations ou coachs pilotes.

### Phase 1 — Fondations techniques (4–5 j)

- [x] Mise à jour des dépendances (22 failles corrigées, 0 restante).
- [x] Contrôle d'accès central, limitation des tentatives de connexion, CSRF sur les API et le wizard.
- [x] Emails en POST, erreurs masquées, dates validées, en-têtes de sécurité (CSP), scripts servis localement.
- [x] Corrections rapides de l'audit : F1, F5, F6, F7, F8 ; liens morts du menu retirés ; dates en français.
- [x] Tests d'autorisation et d'isolation des données (46 tests au total).
- [ ] Dépôt Git distant et CI ; base MySQL propre avec une seule migration *(en attente de ta décision)*.

### Phase 2 — Entrée dans l'application et conformité (6–8 j)

- [x] Inscription, vérification de l'email (code à 6 chiffres), mot de passe oublié.
- [x] Page Profil (corrige le lien mort), changement de mot de passe et d'email.
- [x] RGPD : export des données, suppression du compte, consentement.
- [x] Pages CGU, CGV, confidentialité, mentions légales : **versions de travail**, passages `[À COMPLÉTER]` (société, hébergeur, médiateur) et relecture par un juriste avant la mise en ligne.
- [x] Onboarding court en 3 minutes, qui aboutit au premier score de cohérence (§3.3) : 40/100 avec deux actions proposées.
- [ ] Avant la mise en ligne : choisir le fournisseur d'emails (`MAILER_DSN`, `MAILER_FROM`), lancer un worker Messenger, auto-héberger les Google Fonts, mettre en place la suppression des comptes inactifs annoncée dans la politique de confidentialité.

### Phase 3 — Un cœur cohérent (10–14 j)

- [x] Appliquer le lexique et l'architecture des plans décidés en phase 0 (menus, titres, URL, templates). Relation & Réseau rattaché à la Croissance ; le Plan de Transmission affiche les projets de legs (ils n'apparaissaient nulle part).
- [x] Wizard : mise à jour incrémentale, sans effacer puis recréer ; sauvegarde par étape. Validation : titres et statuts contrôlés, liens limités aux objectifs de l'utilisateur.
- [x] Découper `UserPlanController` : chaque plan a sa page et son contrôleur dans son module.
- [x] Revue hebdomadaire étendue à tous les plans, aux rituels et au journal (`/bilan/revue-hebdomadaire`).
- [x] Dashboard réel : citation du jour (entité `Quote`, 22 versets LSG 1910, admin `/admin/citations`), raison d'être affichée, prochaines échéances.
- [x] Supprimer les impasses (§3.4) : Défis, partages non effectifs, liens morts de l'admin.
- [x] Encart « Comprendre cette étape » relié aux chapitres du livre (§3.6), module Méthode `/methode`.
- [x] Export PDF du Plan de Vie et du Plan de Croissance.
- [ ] Reste : déplacer le calcul du score de cohérence de Twig vers un service PHP (aujourd'hui dupliqué entre Plan de Vie et hub) ; rappel hebdomadaire par email (phase 4).

### Phase 4 — Rétention (5–7 j)

- [x] Centre de notifications (cloche + page), préférences dans le profil, désabonnement en un clic.
- [x] Rappels : revue hebdomadaire, revue mensuelle, rituels oubliés, échéances à 7 jours, partenaire inactif (`app:reminders:send`, à planifier chaque jour ; voir docs/modules/notification.md).
- [x] Revue mensuelle (`/bilan/revue-mensuelle`) et historique des plans (versions datées, `/plan-de-vie/historique`).
- [ ] À la mise en ligne : tâche planifiée quotidienne et worker Messenger chez l'hébergeur.

### Phase 5 — Monétisation (6–8 j)

- [x] Offres FREE / PRO avec limites et interrupteurs configurables (modèle de l'ancienne version), prix en euros et en francs CFA.
- [x] Essai de 14 jours à l'inscription ; paiements manuels (virement, Mobile Money, espèces) déclarés par le client et validés dans l'admin ; reçus PDF ; rappels de fin d'essai et de fin d'abonnement.
- [ ] Paiement en ligne (Stripe pour la carte en euros, agrégateur africain pour le Mobile Money) : **dès que la société est immatriculée** (voir docs/modules/abonnement.md).
- [x] Page Abonnement / « Passer à PRO » et invitations contextuelles (plans verrouillés, carte, PDF, limites).
- [x] Admin : utilisateurs (recherche, offre, PRO offert), paiements et revenus, citations. Modèles d'email : plus tard.
- [x] Landing page publique avec les prix (`/`).

**→ Bêta payante possible ici : environ 32 à 44 jours depuis le début.**

### Phase 6 — Accompagnement et social (10–14 j, selon le positionnement)

- [ ] Partage réellement granulaire (habitudes, bilans, hygiène…) et tableau de bord du partenaire.
- [ ] Coaching / mentorat : consentement, messages, suggestions d'objectifs.
- [ ] Feedback 360° par lien externe et feedback de l'entourage sur la personnalité.
- [ ] Défis collectifs.
- [x] Offre Organisation : espace, lien d'invitation et emails groupés, consentement au partage, rôles responsable / accompagnateur, tableau de bord d'engagement, packs de places, validation admin, reçus (voir docs/modules/organisation.md).

### Phase 7 — Mobile (6–8 j)

- [ ] API V1 avec le même contrat que l'ancienne version (`mobile-login`, `refresh`, `sync/pull`, `sync/push`).
- [ ] Bascule de `mobile/` vers la V1, mise à jour des écrans selon le nouveau lexique, republication sur les stores.

### Phase 8 — Lancement (en continu)

- [ ] Pilotes : 2 ou 3 organisations, 20 à 50 utilisateurs, entretiens.
- [ ] Mesures : inscription → onboarding terminé → première revue hebdomadaire → passage PRO.
- [ ] Ajustements, témoignages, puis ouverture publique.

### Plus tard (selon la demande)

Listes, découverte communautaire, Google Calendar et flux iCal, vue année du planner, multilingue, PWA, intégration de KIINI ACADEMY (compte unique), **reprise des données de l'ancienne version**.

### Synthèse du calendrier

| Jalon | Phases | Effort cumulé |
|---|---|---|
| Application sûre | 0–1 | ≈ 1 semaine |
| Application utilisable par un inconnu | 0–3 | ≈ 4–5 semaines |
| **Bêta payante (MVP vendable)** | 0–5 | **≈ 7–9 semaines** |
| Offre complète avec accompagnement et mobile | 0–7 | ≈ 11–14 semaines |

---

## 6. Décisions attendues de ta part

1. **Positionnement** : chrétien assumé ou généraliste ?
2. **Architecture des plans** : 2 plans (comme le livre) ou 5 plans (comme la V1) ?
3. **Lexique** : Hygiène ou Discipline ? Transmission ou Contribution ?
4. **Périmètre MVP** : valides-tu la colonne « Proposition » du §2 ?
5. **Offre Organisation (B2B)** au lancement ou après ?
6. **Pilotes** : quelles organisations ou quels coachs contacter ?

### 6.1 Décisions prises le 15/11/2026

| # | Décision | Conséquences |
|---|---|---|
| 1 | **Positionnement chrétien assumé** | Cible : églises, réseaux et coachs chrétiens. Le vocabulaire spirituel (« But de Dieu »…) reste visible dans l'application et le marketing. |
| 2 | **5 plans**, le livre sera mis à jour | Vie, Croissance, Développement, Hygiène, Transmission. L'axe « Compétence & Connaissance » **quitte le Plan de Croissance** pour rejoindre le Plan de Développement, qui garde ce titre. La Croissance garde « Caractère & Conviction » et « Relation & Réseau ». Il faudra des chapitres au livre pour Développement, Hygiène et Transmission. |
| 3 | **Lexique : « Hygiène » et « Transmission »** | « Discipline » et « Contribution » disparaissent des écrans, des URL et du code (phase 3). Le lexique de référence est dans `../docs/charte-projet.md` §1bis. |
| 4 | **Offre Organisation après le lancement**, avec des pilotes | Le MVP comprend Gratuit + PRO. L'offre Organisation est construite en phase 6, une fois 2 ou 3 structures pilotes engagées. |
| 5 | Périmètre MVP | Par défaut, la colonne « Proposition » du §2 s'applique, jusqu'à révision. |
| 6 | Pilotes | À identifier. |
