# Audit de cohérence du travail du 19 au 23/11/2026

Audit demandé par le fondateur le 23/11/2026. Il porte sur ce qu'une autre IA a livré du 19 au 23/11 : l'API mobile
(lots 1 à 5), l'application `mobile-v1/`, la parité des prix en FCFA, le Sanctuaire du Couple 360, la Boussole 360,
Ma Pause, la feuille de route et les RFC-001 et RFC-002. Cela représente 18 commits côté web (76 fichiers, environ
9 700 lignes) et 10 commits côté mobile.

**Méthode** : relecture du code et des différences Git, exécution de toutes les suites de tests, comparaison avec
les règles du projet (`AGENTS.md`, charte, lexique, journal `SUIVI-IA.md`) et avec les décisions déjà prises par le
fondateur.

## Verdict en une phrase

Le socle technique est sérieux : connexion mobile, isolement des données entre comptes, aucun prix affiché dans
l'application. Mais il y a **2 failles de sécurité à corriger avant toute publication sur le Play Store**, une suite de
tests **rouge depuis le 19/11** alors que le journal la dit verte, et surtout plusieurs **incohérences
fonctionnelles** : des promesses de la vitrine qui ne correspondent à rien d'accessible, des mots du livre détournés
de leur sens et une feuille de route qui invente des offres non décidées.

## Ce qui a été vérifié

| Contrôle | Résultat |
|---|---|
| Tests Symfony (`php bin/phpunit`) | **457 / 458** : 1 échec (`OrganisationTest::testPackPricesWithVolumeDiscount`) |
| Schéma de la base (`doctrine:schema:validate`) | **Non synchronisé** (voir T2) |
| Tests de la Boussole (`node --test`) | 15 / 15 |
| Application mobile : TypeScript (`tsc --noEmit`) | 0 erreur |
| Application mobile : tests (`vitest`) | 24 / 24 |

---

## 1. Cohérence technique

### Ce qui est bien fait
- L'API mobile est rangée dans son propre module `src/Mobile/`, ce qui suit la règle « un module par dossier ».
  Elle **réutilise les services existants** (`Entitlements`, `AssessmentService`, `Notifier`, `CoupleService`) au lieu
  de dupliquer leur logique.
- Le décorateur `MobilePushSenderDecorator` ajoute l'envoi des notifications sur téléphone sans toucher au code des
  rappels.
- Les réponses ne sont jamais mises en cache (`no-store`), les erreurs suivent un format JSON unique et les codes
  d'erreur sont stables (`pro_required`, `no_couple`…).

### Problèmes

**T1 · La suite de tests est rouge depuis le 19/11 à 17 h 43 (important).**
Le commit des prix FCFA (`c6c3c4d`) a modifié `tests/Organisation/OrganisationTest.php` à moitié : le prix de
20 000 FCFA est mis à jour en tête du fichier, mais la ligne 52 attend encore l'ancien total (750 000 au lieu de
1 500 000).
Les entrées suivantes du journal annoncent « 42/42 tests Mobile + Couple », c'est-à-dire une partie seulement des
tests. La suite complète n'a donc plus été lancée depuis. **Une affirmation du journal (« 0 erreur ») est fausse
depuis cette date.**
→ Corriger l'assertion. Exiger que chaque entrée du journal indique le résultat de la suite **complète**.

**T2 · Le schéma de la base n'est pas synchronisé (moyen).**
`doctrine:schema:validate` échoue, alors que `AGENTS.md` impose de le vérifier après chaque modification d'entité.
Deux causes :
- la migration des prix (`Version20261119183000`) change les valeurs mais pas les valeurs par défaut des colonnes ;
- la migration du couple (`Version20261121103000`) donne aux index des noms différents de ceux que Doctrine attend.

Aucun effet visible pour l'instant, mais la prochaine migration générée automatiquement embarquera ces écarts.
→ Ajouter une migration de rattrapage (valeurs par défaut et noms d'index).

**T3 · Les noms de l'API ne suivent pas un seul vocabulaire (mineur).**
Les routes du couple sont en anglais (`/couple/projects`, `/challenges/check-day`, `/meditations/complete-step`,
`/review`, `/report`), alors que le reste de l'API est en français (`/agenda`, `/partenaires`, `/revues/hebdo`,
`/rituels`, `/connexion`). Les deux applications vont vivre longtemps avec ce contrat.
→ À harmoniser maintenant, tant que l'application n'est pas publiée.

**T4 · Le refus d'un partenaire ne fait pas la même chose sur le web et sur le mobile (moyen).**
Sur le web, refuser une demande la supprime. Sur le mobile, la demande passe au statut `REJECTED` et reste en base :
le demandeur ne pourra plus jamais réinviter cette personne (réponse « Une demande existe déjà »).
Fichiers : `MobileCommunityController::respondPartner` et `SocialController::respondConnection`.
→ Aligner le mobile sur le web.

**T5 · Une même fonction est codée deux fois, de deux façons différentes (moyen).**
Deux « Boussole 360 » coexistent : 24 affirmations sur le web (`assets/js/boussole-engine.js`), 6 questions sur le
mobile (`app/boussole/index.tsx`). Les calculs, les textes et les résultats diffèrent, et aucun des deux n'enregistre
le résultat dans le compte. Voir aussi F4.

---

## 2. Cybersécurité

### Ce qui est bien fait
- **Jetons mobiles** :
  - seule une empreinte SHA-256 est gardée en base ;
  - le jeton d'accès vit 20 minutes, le jeton de rafraîchissement 60 jours, à usage unique ;
  - réutiliser un jeton déjà échangé révoque toutes les sessions du compte ;
  - 10 appareils au plus par compte ;
  - côté téléphone, les jetons sont rangés dans le trousseau sécurisé (`expo-secure-store`).
- **Comptes refusés sur le mobile** : les comptes non vérifiés, et les administrateurs, qui gardent ainsi leur
  connexion en deux étapes sur le site.
- **Limites de tentatives** à la connexion (par adresse IP et par compte) et au rafraîchissement des jetons.
- **Accès aux données d'autrui** : contrôle systématique du propriétaire (agenda, rituels, journal, carnets, projets
  et intentions du couple, demandes de partenariat).
- **Application** : HTTPS imposé en production, adresses de redirection assainies dans les notifications, cache
  local vidé à l'expiration de la session.
- **Aucun prix ni lien de paiement** dans l'application ni dans l'API : c'est conforme aux règles d'Apple et de
  Google.

### Failles

**S1 · Un compte gratuit peut passer un test PRO depuis le mobile (important).**
`POST /api/mobile/v1/tests/{slug}/reponses` enregistre le test **sans vérifier les droits**
(`MobileTestController::submit`). Seul l'écran des questions est protégé.
Sur le web, la même route protège l'affichage **et** l'envoi (`AssessmentController::take`). Comme la liste des
questions est connue, il suffit d'envoyer des réponses pour obtenir un Big Five, un ennéagramme ou un type complet
sans être PRO. C'est un contournement direct de l'offre gratuite resserrée décidée le 19/11.
Les tests mobiles ne vérifient le blocage que pour les questions.
→ Appeler `requireTest()` au début de `submit` (et pour les auto-évaluations, s'il y a lieu). Ajouter le test
correspondant.

**S2 · Changer ou réinitialiser son mot de passe ne déconnecte pas le téléphone (important).**
`PasswordResetController` et `ProfileController` (changement de mot de passe) ne révoquent pas les jetons mobiles.
Concrètement : une personne qui se fait voler son téléphone, ou dont le mot de passe a fuité, réinitialise son mot
de passe… et la session mobile du voleur reste valide **jusqu'à 60 jours**. Le même problème vaut pour le
changement d'adresse e-mail.
→ Appeler `MobileTokenService::revokeAll()` à chaque changement de mot de passe ou d'adresse.

**S3 · Le mobile révèle si une adresse a un compte (faible).**
L'invitation d'un partenaire répond `200` quand l'adresse n'a pas de compte et `201` quand elle en a un. Le
commentaire du code affirme le contraire. Le web répond `200` dans les deux cas.
→ Répondre `200` dans tous les cas.

**S4 · Petits points (faibles, à traiter quand on repassera sur ces fichiers)** :
- **Rafraîchissement simultané** : deux rafraîchissements envoyés en même temps avec le même jeton peuvent tous
  deux réussir (pas de verrou sur la ligne).
- **Temps de réponse à la connexion** : il est plus court quand l'adresse n'existe pas, ce qui permet en théorie de
  deviner les comptes.
- **Signaux au conjoint** : un même conjoint peut envoyer jusqu'à 5 signaux par minute, avec un message libre.
- **Blocage volontaire** : la limite de 5 tentatives par compte en 15 minutes permet à un tiers de bloquer la
  connexion mobile de quelqu'un (compromis classique, à connaître).

---

## 3. Cohérence fonctionnelle

C'est la partie la plus importante de cet audit.

**F1 · La vitrine promet ce que personne ne peut utiliser aujourd'hui (important).**
La section couple de la vitrine annonce « un véritable sanctuaire commun, votre maison à deux **sur le web et le
mobile** » : méditations à deux (L'Autel), défis synchronisés avec « flamme » (Le Jardin), signaux en un clic.
Or :
- ces fonctions **n'existent que dans l'application mobile** : le web n'a aucune page pour les méditations, les défis
  ou les signaux ;
- l'application mobile **n'est pas publiée**.

Un visiteur qui paie un Duo aujourd'hui ne trouve donc rien de ce qui est annoncé. C'est contraire à la règle du
projet : ne rien promettre ni inventer.
→ Au choix : retirer ces promesses de la vitrine jusqu'à la sortie de l'application, ou écrire « bientôt dans
l'application mobile ».

**F2 · De faux écrans et de faux prénoms sur la vitrine (important).**
La section « Entrez dans votre sanctuaire » montre trois écrans de téléphone dessinés à la main, avec « Bonjour,
Modeste », « Modeste & Linda », une Boussole à « 78 % » et « Jour 4/7 ».
Le journal du 17/11 dit pourtant : *pas de capture d'écran de l'application sur la vitrine (pas de photo ni de
témoignage non plus : ne rien inventer)*. Ces écrans montrent en plus des fonctions inaccessibles (voir F1). Leurs
textes sont écrits en dur, hors de `t()`, et ne sont donc pas modifiables dans l'administration (règle 8). Enfin, la
vitrine compte deux fois plus de styles en ligne qu'avant (45 → 88).
→ À valider par le fondateur : garder, transformer en schéma explicite (« aperçu »), ou retirer.

**F3 · « Le Sanctuaire » est détourné de son sens (important pour la marque).**
Dans le livre et dans l'application, **« Le Sanctuaire » est l'exercice écrit de chaque chapitre** (`BookLibrary`,
encarts « Comprendre cette étape », fiche `CONTEXTE_POUR_IA.md`). Le travail récent emploie le même mot pour :
- l'espace couple (« Sanctuaire du Couple 360 ») ;
- toute l'application (« Faire de KIINI SAWA un Sanctuaire de Croissance 360 », « Entrez dans votre sanctuaire ») ;
- de futures capsules audio (« Sanctuaire Audio »).

La vitrine emploie donc le même mot, à quelques lignes d'écart, pour deux choses différentes. C'est exactement ce
que le lexique interdit (« un seul mot par chose »). Le mot n'est pas encore dans `docs/lexique.md`, et aucun test ne
l'encadre.
→ Choisir un autre nom pour l'espace couple, par exemple « La Maison du couple », que la RFC-001 emploie déjà. Inscrire
le choix dans le lexique.

**F4 · « Boussole » : un mot déjà pris, deux instruments, aucun lien avec le reste (moyen).**
- **Le mot est déjà pris** : la vitrine dit « Le Plan de Vie est **la boussole** ». La « Boussole 360 » est une
  autre chose encore.
- **Deux instruments** : celui du web (24 affirmations) et celui du mobile (6 questions) donnent des résultats
  différents sous le même nom.
- **Aucun lien avec le reste** :
  - le résultat n'est enregistré nulle part, ni dans le compte ni dans « Mon profil KIINI SAWA » ;
  - il recouvre en partie les auto-évaluations existantes (Équilibre, Hygiène) sans s'y relier ;
  - la RFC-002 (jalon 4) s'appuie sur la Boussole pour recommander des parcours, ce qui est impossible tant que le
    résultat n'est pas conservé.
- **Textes figés** : la page `/boussole` est presque entièrement écrite en dur (6 textes modifiables sur 425 lignes).

→ Une seule Boussole (le web en référence). Enregistrer le résultat dans le compte après inscription. Rendre ses
textes modifiables.

**F5 · Prix en FCFA : doublés d'un coup (à confirmer par le fondateur).**

| Offre | Avant (15/11) | Depuis le 19/11 |
|---|---|---|
| PRO mensuel | 3 000 FCFA | 6 500 FCFA |
| PRO annuel | 25 000 FCFA | 52 500 FCFA |
| Duo | 39 000 FCFA | 78 500 FCFA |
| Place d'organisation | 10 000 FCFA | 20 000 FCFA |

Le journal parle d'une « décision du fondateur ». Elle va pourtant à l'encontre de la décision du 15/11 : deux
grilles de prix, dont une **adaptée à l'Afrique francophone**. Et la migration a **écrasé en production** les prix
réglés dans l'administration.
→ Le fondateur confirme ou annule. Dans les deux cas, les prix se règlent dans l'administration : une migration ne
devrait jamais les écraser.

**F6 · Les rituels du couple ne font pas ce qu'ils annoncent (moyen).**
- **Défis** : présentés « jour par jour ». En réalité, dès que les deux conjoints ont validé, le jour suivant
  s'ouvre aussitôt (`checkChallengeDay`). Un défi de 7 jours peut se finir en deux minutes.
- **Méditations** : présentées « à deux ». En réalité, **un seul conjoint** peut valider toutes les étapes
  (`completeMeditationStep`).
- **Flamme** : la série de jours (`streakDays`) n'est pas recalculée dans un test qui couvre le passage d'un jour à
  l'autre.

→ Ouvrir un seul jour par date du calendrier. Demander les deux validations pour la méditation, ou l'annoncer comme
individuelle.

**F7 · Des textes spirituels écrits par l'IA, sans relecture et non modifiables (moyen).**
Les versets, les méditations, les défis et les signaux (« Merci mon amour », « … est en train de prier pour toi »)
ont été **rédigés par l'IA** et écrits en dur dans `CoupleSanctuaryCatalog.php` :
- ils ne viennent ni du livre ni de l'auteur ;
- ils ne sont pas modifiables dans l'administration (règle 8 de `AGENTS.md`) ;
- ils n'ont pas été relus par un théologien, relecture prévue pour les figures bibliques ;
- ils tutoient, alors que l'application vouvoie.

De plus, 1 Corinthiens 13:4-7 est **condensé sans le signaler** (les versets 5 et 6 sont sautés), ce qui est délicat
pour une citation biblique.
→ Faire relire ces textes par l'auteur. Les passer dans le système de textes modifiables. Citer les versets en
entier, ou marquer les coupes par « … ».

**F8 · Des fonctions réservées au mobile alors que le mobile n'est pas sorti (moyen).**
Ma Pause, la Boussole express et tout le Sanctuaire du couple n'existent que sur le mobile. Les abonnés d'aujourd'hui
(web uniquement) ne voient rien. Cela inverse la priorité : le produit vendu est le web.
→ Décider de la règle : « toute nouvelle fonction arrive d'abord, ou en même temps, sur le web ».

**F9 · Petites régressions de vocabulaire (mineur).**
- La vitrine dit « challenges synchronisés » et « flamme commune ». Or le lot A du 17/11 avait justement remplacé
  « Défis & Challenges » par « Défis collectifs », et la règle est : pas d'anglais.
- `LexiconTest` n'interdit pas « challenge », ce qui explique que la régression soit passée.

→ Remplacer le mot, et ajouter « challenge » à `LexiconTest`.

---

## 4. Cohérence des RFC et de la feuille de route

### RFC-001 · Sanctuaire du Couple 360

| La RFC dit | Le code fait | Écart |
|---|---|---|
| Statut « Planifié » | Codé, migré et en production le 21/11 | Statut non mis à jour |
| Cible « Mobile & Web » | Mobile seulement | Web absent (voir F1, F8) |
| `CoupleMeditationDay`, parcours de 7 à 21 jours | Pas d'entité par jour ; une suite d'étapes finissable en une fois | Différent |
| « Validation mutuelle quotidienne » | Pas de contrôle de la date | Voir F6 |
| `GET/POST /couple/challenges`, `/couple/meditations` | `/challenges/start`, `/challenges/check-day`, `/meditations/complete-step`… | Contrat différent |
| Dossiers `app/couple/meditation/`, `revue/`, `_layout.tsx` | Fichiers simples, `salon.tsx`, pas de `_layout` | Mineur |
| Exemple de signal « ❤️ [Prénom] t'invite… » | Émoji et tutoiement | Contraire aux règles « zéro émoji » et « vous » |

→ Mettre la RFC à jour : statut « Livré (mobile) », contrat réel, écarts assumés ou à corriger.

### Feuille de route (`ROADMAP.md`)

- **Elle invente des offres qui n'ont pas été décidées** : « Famille (~159 €) », « PRO+ (150 €) », « Mentor IA ».
  Les décisions connues sont Gratuit, PRO, Duo et Organisation. Le tableau « 1 000 000 € / an » repose sur ces
  offres. **C'est au fondateur de les valider, pas à l'IA.**
- **« ARR » (revenu annuel récurrent) ne colle pas au modèle actuel** : les paiements sont manuels, sans
  renouvellement automatique (vitrine : « Sans renouvellement automatique »). Le revenu ne se reconduit pas tout seul.
- **Des fondations qui n'existent pas** :
  - le module `Media` est présenté comme le socle des capsules audio, alors qu'il sert à la **provenance des
    inscrits** (liens suivis) ;
  - le module `Coaching` sert de base au « Studio d'Accompagnement », alors qu'il est **dormant** et ne doit pas être
    exposé sans décision (`AGENTS.md`) ;
  - le texte parle de « 5 tests de personnalité », alors qu'il en existe 7, plus 2 auto-évaluations.
- **Services externes payants** : Cloudflare R2, Bunny Stream et Vimeo demandent un compte de paiement, alors que la
  société n'est pas créée. Ils obligent aussi à desserrer la politique de sécurité du site, qui est aujourd'hui
  entièrement auto-hébergée.
- **Le principe « chaque fonctionnalité sert un des 6 plans » est bon**, mais l'espace couple n'est pas un plan.
  Ailleurs, la RFC-002 ajoute `COUPLE` dans la liste des plans.

### RFC-002 · Académie Pratique

**Points solides** :
- Les 14 entités citées dans le tableau du pont vers les plans existent toutes, avec les bons noms (vérifié une par
  une).
- Les garde-fous sont pertinents : consentement visible, pas de doublon en cas de nouvel envoi, droits PRO
  respectés, aucune écriture silencieuse dans le Plan de Vie.
- Le studio de rédaction pour le fondateur est une bonne idée.

**Écarts** :
1. **Elle ignore ce qui existe déjà** : la Méthode, le **parcours de 90 jours** (`/parcours`, 13 semaines avec les
   écrans où écrire) et les encarts « Comprendre cette étape » font déjà « lire, puis écrire dans son plan ». Sans
   articulation, l'Académie deviendrait un **troisième chemin guidé** en parallèle des deux premiers.
   → La RFC doit dire comment les trois s'emboîtent (par exemple : l'Académie approfondit une semaine du parcours).
2. **Les cours de `formation/courses_dump.json` datent de janvier 2026**, avant les 6 plans, l'Intendance et le
   nouveau livre. C'est une matière à **réécrire avec l'auteur**, pas à importer telle quelle, même en brouillon.
3. **`COUPLE` est mis au rang des plans** (`primaryPlan`, filtres « Couple » à côté des 6 plans). C'est à garder
   séparé visuellement.
4. **YouTube « non répertorié »** pose plusieurs problèmes : traçage publicitaire, sortie du principe
   d'auto-hébergement, et données personnelles (RGPD) en Afrique comme en Europe. Bunny et R2 demandent une société
   (voir plus haut).
5. **Le jalon 4 dépend de la Boussole**, dont le résultat n'est pas conservé (voir F4).
6. **L'exemple de confirmation à l'écran contient « ✓ »** : c'est permis par la règle, à garder sobre.

---

## 5. Priorités recommandées

| # | Action | Type | Effort |
|---|---|---|---|
| 1 | Verrouiller `POST /tests/{slug}/reponses` (S1) et ajouter le test | Sécurité | Petit |
| 2 | Révoquer les jetons mobiles au changement de mot de passe ou d'adresse (S2) et ajouter les tests | Sécurité | Petit |
| 3 | Remettre la suite de tests au vert (T1) et rattraper le schéma (T2) | Technique | Petit |
| 4 | Aligner l'invitation et le refus de partenaire sur le web (S3, T4) | Sécurité et fonctionnel | Petit |
| 5 | Décider : prix FCFA (F5), faux écrans et prénoms (F2), promesses couple de la vitrine (F1) | Fondateur | Décision |
| 6 | Renommer l'espace couple et inscrire le nom dans le lexique ; retirer « challenges » (F3, F9) | Fonctionnel | Moyen |
| 7 | Une seule Boussole, avec le résultat enregistré dans le compte (F4, T5) | Fonctionnel | Moyen |
| 8 | Défis au rythme du calendrier, méditation vraiment à deux (F6) ; textes relus et modifiables (F7) | Fonctionnel | Moyen |
| 9 | Mettre à jour la RFC-001 ; faire valider par le fondateur les offres de la feuille de route ; articuler la RFC-002 avec le parcours de 90 jours | RFC | Petit |
| 10 | Harmoniser en français les routes de l'API couple avant la publication (T3) | Technique | Moyen |

Les points 1 à 4 sont à faire **avant toute publication sur le Play Store**.

**Mise à jour du 23/11/2026** : les points 1 à 4 sont corrigés et testés (S1, S2, S3, T1, T2, T4). La suite complète passe (460 / 460) et le schéma est synchronisé. Détail dans `SUIVI-IA.md`. Restent les points 5 à 10.

## Décisions du fondateur (23/11/2026)

| Point | Décision | Suite |
|---|---|---|
| F5 · Prix FCFA | **Parité avec l'euro conservée** (6 500 / 52 500 / 78 500 / 20 000 FCFA) | Clos. Les prix restent réglables dans l'administration |
| F3 · « Sanctuaire » | **Le mot est gardé avec ses deux sens** : l'exercice du livre et le Sanctuaire du Couple | Clos. Inscrit dans `docs/lexique.md` (toujours préciser « du Couple ») |
| Feuille de route | **Offres Famille, PRO+ et Mentor IA retirées** | Fait dans `ROADMAP.md`. Objectif recalculé avec Duo et PRO seulement |
| F1, F2 · Vitrine | **Les aperçus visuels de l'application sont voulus**, à condition que ce qu'ils montrent existe partout (web et mobile) | Fait le 23/11 : le Sanctuaire du Couple existe sur le web (`/couple/autel`, `/couple/defis`, signal). Reste : remplacer les dessins par de vraies captures |
| F6 · Rythme du couple | **Un jour à la fois, méditation validée par les deux, temps de retraite si les deux disent oui** (dates libres, fin obligatoire, sans limite de fréquence) | Fait le 23/11 (web, API et application mobile) |
| F7 · Textes du Sanctuaire | **Modifiables dans Admin › Textes du site** (143 textes, brouillon puis publication, retour au texte d'origine) | Fait le 23/11. Reste au fondateur : les relire (versets complets, ton vouvoyé ou tutoyé, théologie) |
