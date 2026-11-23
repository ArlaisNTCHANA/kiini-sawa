# Journal de passation (pour l'IA ou le développeur qui prend la suite)

À lire après `AGENTS.md`. Ce fichier est mis à jour **à chaque chantier** : ce qui a été fait, où, pourquoi, ce qui
reste ouvert et les pièges rencontrés. Le plus récent en haut. Le détail technique de chaque module est dans
`docs/modules/*.md`.

**Règles de travail avec le fondateur** : il écrit en français, valide chaque chantier (« Yes, do it ») ; on livre,
teste (`php bin/phpunit`), commit, déploie (`bin/ops/envoyer-hostinger.sh`), vérifie la prod (page d'accueil 200,
journal `var/log/prod-AAAA-MM-JJ.log` sans erreur) ; **le fondateur fait lui-même `git push`** (refusé à l'IA).
Ne jamais afficher ni demander de mot de passe ; ne pas publier son nom (mentions légales en attente).

## 23/11/2026 — Relecture externe, points 3 et 4 : vitrine honnête sur le mobile, « Sanctuaire » toujours précisé

- **Point 3, vitrine** (aucune modification n'existait en production pour ces codes) :
  - `vitrine.app.01` passe de « Web & Application Mobile » à « Sur ordinateur et sur téléphone » ;
  - `vitrine.app.02` passe de « Entrez dans votre sanctuaire » à « Une interface calme, au service de votre chemin » ;
  - `vitrine.app.03` : « directement dans votre navigateur. L'application mobile arrive bientôt sur l'App Store et
    Google Play. » (« même hors connexion » est retiré) ;
  - `vitrine.app.note` : « Captures réelles du site KIINI SAWA ouvert sur un téléphone… » (ce sont des captures du
    site, pas de l'appli native) ;
  - `vitrine.s7.02` : « Le Sanctuaire du Couple, votre maison à deux sur le site (et bientôt dans l'application
    mobile)… ».
- **Point 4, « Sanctuaire »** :
  - site : « Notre Sanctuaire du Couple » (bloc de `/couple`), notifications « dans votre Sanctuaire du Couple »,
    signal « Invitation à méditer ensemble » ; « Le sanctuaire de votre croissance » (accueil) devient « L'espace de
    votre croissance » ;
  - méditation « Bâtir sur le roc », étape 3 : « notre sanctuaire » devient « notre foyer » (texte d'origine,
    modifiable dans l'administration) ;
  - mobile : « SANCTUAIRE DU COUPLE » sur l'accueil, et messages corrigés dans la méditation, le couple et Ma Pause.
- **Garde-fous** : `LexiconTest::testSanctuaireIsNeverUsedInAGeneralSense` (templates et src) et un test Vitest dans
  `mobile-v1/__tests__/lexicon.test.ts` (app et src). Ils refusent « votre / notre / un… sanctuaire » non suivi de
  « du Couple ». On a vérifié qu'ils échouent sur un vrai cas.
- **Tests** : suite web **486 / 486** ; mobile : Vitest 25/25, `tsc` 0 erreur.
- **Reste de la relecture** : point 2 (une seule Boussole, enregistrée dans le compte), puis 1 (accueil mobile
  allégé), puis 7 (API du couple en français, avant les stores).

## 23/11/2026 — Relecture externe : redevabilité sécurisée (point 6) et texte en double sur la vitrine (point 5)

- **Contexte** : le fondateur a fait relire le travail récent, avec 7 remarques. Ordre convenu : 6, 5, puis 3 et 4
  (formulations de la vitrine, « Sanctuaire »), 2 (une seule Boussole enregistrée), 1 (accueil mobile allégé), 7 (API
  du couple en français, avant la publication sur les stores).
- **6a, fausse alerte** : si le rituel ou l'objectif lié est supprimé, `AccountabilityService::pauseIfTargetMissing()`
  (appelé avant toute alerte dans `runDailyChecks`) met l'engagement en pause. L'engagement devient **libre**
  (`AccountabilityCommitment::detachTarget()`) : même libellé, alertes rituel et objectif retirées, alerte « plus de
  connexion » gardée. La personne est prévenue. Une fois repris, il n'est plus remis en pause.
- **6b, abus d'envoi** : limiteur `accountability_invite`, **3 invitations par jour et par compte**, en gratuit comme
  en PRO. Sans lui, créer, arrêter et recommencer permettait d'envoyer des e-mails en série, même en gratuit.
- **6c, mobile** : avant d'ouvrir le site, le bouton « Nouvel engagement (sur le site) » prévient qu'il faudra se
  connecter avec le même compte, puis actualise la liste au retour. Après la connexion, le site renvoie directement au
  formulaire (vérifié par un test).
- **5, vitrine** : la phrase de paiement de la carte PRO a son propre identifiant (`vitrine.s8.paiement`), au lieu de
  `vitrine.s8.22` partagé. « Les six tests » devient « Tous les tests » : il y a 7 questionnaires et 2
  auto-évaluations. Aucune modification n'existait en production pour ces codes. Garde-fou :
  `SiteTextsTest::testNoTextCodeIsUsedWithTwoDifferentOriginals` (on a vérifié qu'il échoue sur un vrai doublon).
- **Tests** : 3 de plus dans `AccountabilityTest` (rituel supprimé, limite d'invitations, retour au formulaire après
  connexion) et 1 dans `SiteTextsTest`. Suite complète **485 / 485** ; mobile : `tsc` 0 erreur, Vitest 24/24.

## 23/11/2026 — Vitrine : vraies captures de l'application (dernier point de l'audit, F1 et F2)

- Les trois écrans de téléphone dessinés à la main de `dashboard/landing.html.twig` (section `#apercu`) sont remplacés
  par de **vraies captures** de l'application web au format téléphone (390 × 844, ×2) :
  `assets/images/vitrine/apercu-boussole.jpg` (résultat de `/boussole`), `apercu-aujourdhui.jpg` (`/dashboard`),
  `apercu-couple.jpg` (`/couple`, bloc « Notre Sanctuaire »).
- **Compte d'exemple local** : `essai-pro` (prénom Modeste) et `essai-conjoint` (Linda), couple actif, 3 rituels.
  Mention sous les captures : « Captures réelles… compte d'exemple (Modeste et Linda sont des prénoms d'exemple) ».
- Légendes et textes alternatifs passés dans `t()` (`vitrine.app.shot1..3.titre|texte|alt`, `vitrine.app.note`).
  Les styles des dessins (`.ph-*`, `.phone-screen`) sont retirés.
- **Corrections au passage** : « Ma Boussole 360 en 3 minutes » (comme la page), « défis synchronisés » au lieu de
  « challenges ».
- **Méthode de capture** : Chrome sans interface, piloté par `puppeteer-core` (aucun navigateur téléchargé), script
  `capture.mjs` (connexion au compte d'exemple, questionnaire de la Boussole rempli, captures). Il n'est **pas encore
  dans le dépôt** (dossier temporaire de session) : à ajouter si l'on veut refaire les captures après un changement
  d'écran.
- **Vérifié** : section `#apercu` sur ordinateur et sur téléphone, en visiteur non connecté, sans débordement ni
  erreur. Suite complète **481 / 481**.
- **À déployer.**

## 23/11/2026 — Textes du Sanctuaire du Couple modifiables dans l'administration (point F7)

- **Fait** : le système « Textes du site » accepte maintenant des textes écrits en PHP :
  - interface `Contenu\Service\EditableTextProvider`, étiquetée `app.editable_text_provider` et lue par
    `SiteTextCatalog` ;
  - `CoupleSanctuaryCatalog` en est le premier fournisseur (`defaults()`, 143 textes : versets, défis jour par jour,
    étapes de méditation, signaux) ;
  - nouveau service `CoupleSanctuaryTexts`, qui lit les textes affichés (publié, sinon origine ; brouillons en aperçu
    administrateur) ;
  - tous les usages passent maintenant par ce service : `CoupleSanctuaryService`, `CoupleSanctuaryController`,
    `MobileCoupleController` ;
  - dans les signaux, `%s` devient `{prenom}`, plus lisible pour le fondateur ;
  - nouvelle partie « Sanctuaire du Couple » dans `/admin/textes`.
- **Tests** : `tests/Couple/CoupleSanctuaryTextsTest.php` (liste dans l'administration, brouillon invisible puis
  publication visible sur `/couple/defis`, variable `{prenom}` dans un signal, titre modifié dans l'API mobile).
  Suite complète **481 / 481**.
- **Piège** : un fournisseur de textes ne doit dépendre d'aucun service. Sinon, dépendance circulaire : le catalogue
  lit le fournisseur, et SiteTexts dépend du catalogue.
- **Reste au fondateur** : relire ces textes (écrits par une IA). Notamment 1 Corinthiens 13:4-7, condensé ; les
  signaux, au tutoiement ; la relecture théologique.

## 23/11/2026 — Mobile : calendrier natif pour les dates de retraite

- `mobile-v1/` : `@react-native-community/datetimepicker` 9.2.1, la version recommandée par Expo. Il remplace la
  saisie JJ/MM/AAAA dans `src/components/couple/RetreatCard.tsx` (composant `DateField`) :
  - sur iPhone, un sélecteur compact intégré ; sur Android, la fenêtre de calendrier ; sur le web (aperçu de
    développement), la saisie au clavier reste ;
  - dates passées impossibles, et la fin ne peut pas précéder le début.
- **Piège** : `npx expo install` échoue (ERESOLVE) à cause d'un écart déjà présent entre react-dom 19.3.1 et react
  19.3.0.
  - `--legacy-peer-deps` **retire** du lockfile react-native-reanimated, react-native-worklets, react-dom et
    @react-native/babel-preset : à ne pas utiliser.
  - `npm install <paquet> --force` n'ajoute que le paquet voulu : c'est cette commande qui a été retenue.
- Vérifié : `tsc` 0 erreur, Vitest 24/24, exports iOS et Android réussis, `expo-doctor` 20/20. Le sélecteur n'a pas
  été affiché dans un simulateur.

## 23/11/2026 — Redevabilité : revue de la semaine et application mobile (lots 2 et 4 terminés)

- **Revue de la semaine** : encart « Ma redevabilité cette semaine » :
  - il donne les messages (alertes et coups de main), les réponses, les reprises dans les 72 heures et les mots du
    partenaire, avec la question « Qu'est-ce que vos partenaires ont changé pour vous cette semaine… » ;
  - il n'apparaît que s'il y a un engagement ouvert ou un message dans la semaine ;
  - source : `AccountabilityService::weeklySummary()`, sur le web et dans `GET /api/mobile/v1/revues/hebdo`.
- **Mobile** :
  - API : `MobileAccountabilityController` (liste avec chiffres, règles d'alerte et historique ; coup de main ;
    pause, reprise, arrêt) ;
  - écran `app/redevabilite/index.tsx` ; entrée de menu « Ma redevabilité » ; routage des notifications ; encart dans
    `app/revues/hebdo.tsx`.
  La création reste sur le site (aperçu exact des e-mails et consentement) : bouton « Nouvel engagement (sur le
  site) ».
- **Tests** : `AccountabilityTest` compte 10 tests (encart de la revue ; API : liste, coup de main avec copie, délai
  de 7 jours, pause, isolement entre comptes). Routage mobile testé. Suite web **477 / 477** ; mobile : `tsc`
  0 erreur, Vitest 24/24, export iOS réussi.
- **Non vérifié** : l'écran mobile n'a pas été affiché dans un simulateur (seulement compilé). L'encart web, lui, a
  été vérifié dans le navigateur.
- **État de la redevabilité** : lots 1 à 4 faits. Reste l'essai réel des e-mails en production.

## 23/11/2026 — Redevabilité : alertes automatiques au partenaire (le cœur de la fonction)

- **Remarque du fondateur** : « demander à être interpellé » ne suffit pas. Celui qui décroche n'a souvent plus la
  force de demander. L'automatique devient donc le cœur de la fonction, la demande manuelle un complément.
- **Fait** :
  - seuils sur `AccountabilityCommitment` (`alertHabitDays`, `alertGoalDays`, `alertInactiveDays`) ;
  - motifs, constat et reprise sur `AccountabilityNudge` ;
  - `LifeGoal::updatedAt` (rempli au `PrePersist` et au `PreUpdate`) ; migration `Version20261123110000` ;
  - `AccountabilityService::runDailyChecks()` / `dueSignal()` / `recordResumptions()`, branchés dans
    `SendRemindersCommand` (ligne « redevabilité (alertes) ») ;
  - formulaire : étape 4 « Quand prévenir votre partenaire, sans que vous ayez à le demander ? » ;
  - aperçu : liste des alertes choisies et exemple réel d'alerte ; consentement mis à jour ;
  - page : règles actives, bouton « J'ai besoin d'un coup de main », statistique « reprises dans les 72 h », motif et
    badge « reprise » dans l'historique ;
  - invitation et page du partenaire : les alertes y sont mentionnées.
- **Vérifié** :
  - la tâche quotidienne tourne bien en production : `var/log/rappels.log`, chaque jour à 7 h depuis le 17/11 ;
  - `User::lastActiveAt` est bien mis à jour (`LastActivitySubscriber`, au plus une fois par heure, sur le web et le
    mobile) ;
  - `targetsFor()` lit maintenant les objectifs et rituels en base (les collections de l'utilisateur peuvent être
    incomplètes en mémoire).
- **Tests** : 4 de plus dans `AccountabilityTest` :
  - alerte « rituel manqué », une seule par décrochage ;
  - rien avant le seuil, en pause ou si l'alerte est désactivée ;
  - alerte « inactivité » et reprise à 72 h ;
  - choix enregistrés depuis le formulaire et visibles dans l'aperçu.
  Suite complète **475 / 475**.
- **Reste** : revue de la semaine (chiffres), application mobile, essai réel des e-mails en production.

## 23/11/2026 — Engagements de redevabilité, lot 1 (interpeller par un partenaire, avec copie)

- **Demande du fondateur** : l'application envoie un e-mail à un partenaire de redevabilité pour qu'il interpelle la
  personne, avec une copie à celle-ci. Le partenaire indique son action, la personne sait ce qui va se passer, et
  l'ensemble se mesure.
- **Choix par défaut** (non tranchés par le fondateur, recommandations appliquées) :
  - partenaire **avec ou sans compte** ;
  - offre gratuite limitée à 1 engagement ouvert, illimité en PRO ;
  - nom « Engagement de redevabilité » et page « Ma redevabilité ».
  Tous ces choix sont modifiables.
- **Fait** :
  - entités `AccountabilityCommitment` et `AccountabilityNudge`, `AccountabilityService`, `AccountabilityController` ;
  - pages et e-mails décrits dans `docs/modules/social.md` ;
  - entrée « Ma redevabilité » dans la rubrique Ensemble, et carte d'accès sur `/social` ;
  - notification `ACCOUNTABILITY`, migration `Version20261123100000`.
- **Garde-fous** :
  - aperçu exact des e-mails et case « J'ai compris » obligatoire ;
  - accord du partenaire avant toute interpellation ;
  - lien « Ne plus recevoir » dans chaque e-mail ;
  - au plus 1 interpellation tous les 7 jours ;
  - un simple clic sur un bouton de l'e-mail ne vaut pas réponse : le partenaire confirme sur la page (pas
    d'écriture sur une requête GET) ;
  - limite `public_form_ip` sur les pages publiques ; jetons de 48 caractères.
- **Tests** : `tests/Social/AccountabilityTest.php` (4 scénarios : aperçu et consentement, parcours complet sans
  compte, refus et isolement, limite gratuite et partenaire inscrit). Suite complète **471 / 471**.
- **Vérifié dans le navigateur** (local) : création avec aperçu, acceptation par le partenaire, interpellation,
  réponse « J'ai prié pour lui/elle », chiffres affichés (100 % de réponses). Aucune erreur dans la console.
- **À savoir** : en local, les e-mails ne partent pas ; en production, ils passent par Brevo. Dans les mails, le
  partenaire est tutoyé dans la question proposée (« Où en es-tu… »), qui s'adresse à la personne ; le reste du texte
  vouvoie.
- **Reste** : lots 2, 3 et 4 (voir `docs/modules/social.md`).

## 23/11/2026 — Sanctuaire du Couple : rythme quotidien, méditation à deux, temps de retraite (point F6)

- **Décisions du fondateur** :
  - un jour de défi par date du calendrier, et un message discret qui met en avant la régularité plutôt que la vitesse ;
  - méditation validée par les deux conjoints, une étape par jour ;
  - « Temps de retraite » possible si les deux disent oui : **dates choisies par le couple, sans limite de durée ni de
    fréquence** (refus explicite du fondateur). La seule garde technique est une date de fin obligatoire, pour qu'une
    retraite ne reste pas ouverte par oubli.
- **Modèle** :
  - nouvelle entité `Couple\Entity\CoupleRetreat` (proposée par, confirmée par, dates, intention, confirmée le, arrêtée
    le) ;
  - trois colonnes sur `CoupleMeditation` (`stepValidatedByPartnerA/B`, `lastStepCompletedAt`, méthode
    `validateStepFor()`) ;
  - migration `Version20261123090000`.
- **Règles** (`CoupleSanctuaryService`, communes au web et au mobile) :
  - `isChallengeDayLocked` : le jour N s'ouvre le lendemain du jour où le jour N-1 a été validé par les deux, sauf
    pendant une retraite ;
  - `isMeditationStepLocked` : même règle pour les étapes de méditation ;
  - `proposeRetreat`, `confirmRetreat` (impossible de confirmer sa propre proposition), `stopRetreat` (l'un ou l'autre) ;
  - `challengeDaysDuring` alimente l'historique. Chaque étape notifie l'autre conjoint.
- **Web** :
  - carte « Temps de retraite » (`templates/couple/sanctuary/_retreat.html.twig`) sur `/couple/defis` et `/couple/autel` ;
  - routes `/couple/retraite/proposer|confirmer|arreter` ;
  - états « Vous » et le conjoint pour la méditation ; message de rythme ; bandeau « propose une retraite » sur
    `/couple`.
- **API mobile** :
  - `locked`, `rhythmMessage`, `stepDoneByMe` et `stepDoneByPartner` dans les réponses ;
  - `retreat` dans `/couple/home` ;
  - nouvelles routes `GET /couple/retreat` et `POST /couple/retreat/propose|confirm|stop` (documentées dans
    `docs/api-mobile.md` § 13, ajouté avec tout le contrat du Sanctuaire).
- **Application mobile** (`mobile-v1/`) : composant `src/components/couple/RetreatCard.tsx` (dates au format JJ/MM/AAAA),
  défis et méditation avec message de rythme, double validation, messages d'erreur du serveur affichés, bandeau de
  retraite sur l'accueil du couple. `tsc` 0 erreur, Vitest 24/24, export iOS réussi.
- **Tests** : `MobileCoupleSanctuaryTest` (rythme, double validation, retraite proposée, refusée à soi-même,
  confirmée, arrêtée) et `CoupleSanctuaryWebTest` (7 tests). Suite complète **467 / 467**, schéma synchronisé. Vérifié
  dans le navigateur en local : validation par un seul conjoint, proposition de retraite, aucune erreur.
- **Reste** : point F7 (textes du Sanctuaire relus par l'auteur et modifiables dans l'administration) et vraies
  captures pour la vitrine.

## 23/11/2026 — Le Sanctuaire du Couple sur le web

- **Pourquoi** : la vitrine promet le Sanctuaire « sur le web et le mobile » et le fondateur veut des aperçus de
  l'application **à condition que tout existe partout**. Jusqu'ici, les méditations, les défis, les intentions et les
  signaux n'existaient que sur le mobile.
- **Fait** : `Couple\Controller\CoupleSanctuaryController`, qui réutilise `CoupleSanctuaryService` (même logique que
  l'API mobile, aucune duplication) :
  - bloc « Notre Sanctuaire » en tête de `/couple` (`templates/couple/sanctuary/_home.html.twig`) : parole du jour,
    flamme commune, les 4 pièces (L'Autel, Le Jardin des défis, L'Atelier → `#vision`, Le Salon → `#revue`), et
    formulaire de signal au conjoint ;
  - `/couple/autel` : parcours de méditation (commencer, valider une étape) et intentions de prière (ajout et retrait,
    contrôle du couple propriétaire) ;
  - `/couple/defis` : défis (commencer, valider le jour avec un mot pour le conjoint, statut des deux conjoints) ;
  - `POST /couple/signal`.
  Toutes les actions demandent un couple utilisable (les deux PRO ou Duo) et le jeton CSRF `couple`. Les composants
  utilisés sont les composants `ks-…`, et il n'y a aucun émoji.
- **Tests** : `tests/Couple/CoupleSanctuaryWebTest.php` (6 tests : les 4 pièces, accès fermé, parcours complet,
  intentions isolées entre couples, jour de défi ouvert après les deux validations, signal reçu). Suite complète
  **466 / 466**, schéma synchronisé.
- **Vérifié dans le navigateur** (local, compte `essai-pro` et conjoint `essai-conjoint@kiinisawa.test`, accès dans
  `var/compte-de-test.local`) : défi lancé puis validé avec un mot, méditation lancée, intention ajoutée, signal
  envoyé, affichage sur téléphone (375 px, sans défilement horizontal), aucune erreur dans la console ni dans le
  journal du serveur.
- **Documentation** : `docs/modules/couple.md` et RFC-001, désormais au statut « Livré ».
- **Reste** :
  - point F6 de l'audit, qui touche le service commun au web et au mobile : ouvrir un jour de défi par date du
    calendrier, et faire valider la méditation par les deux conjoints ;
  - point F7 : textes du Sanctuaire relus par l'auteur et modifiables dans l'administration ;
  - remplacer les dessins de la vitrine par de vraies captures.

## 23/11/2026 — Décisions du fondateur sur l'audit (prix, Sanctuaire, vitrine, offres)

- **Prix FCFA** : parité avec l'euro conservée (6 500 / 52 500 / 78 500 / 20 000 FCFA). Le point F5 de l'audit est clos.
- **« Sanctuaire »** : le mot garde ses deux sens, l'exercice du livre et le Sanctuaire du Couple. C'est inscrit dans
  `docs/lexique.md` : toujours écrire « Sanctuaire du Couple » pour l'espace couple.
- **Feuille de route** : les offres Famille, PRO+ et Mentor IA sont retirées de `ROADMAP.md` (jamais décidées).
  L'objectif d'1 M€ est recalculé avec les seules offres existantes (Duo : environ 2 500 foyers ; PRO : environ
  2 500 membres). Le Livre de Famille reste une idée, sans offre dédiée.
- **Vitrine** : le fondateur veut montrer à quoi ressemble l'application. Les aperçus restent, à condition que ce qu'ils
  montrent **existe partout** : il faut donc porter sur le web les méditations à deux, les défis et les signaux du
  Sanctuaire du Couple (aujourd'hui sur le mobile seulement), puis remplacer les dessins par de vraies captures.
- **Reste de l'audit** : le Sanctuaire du Couple sur le web (ci-dessus), une seule Boussole avec le résultat
  enregistré dans le compte (F4), défis au rythme du calendrier et méditation vraiment à deux (F6), textes du couple
  relus et modifiables (F7), mise à jour de la RFC-001, lien entre la RFC-002 et le parcours de 90 jours, routes en
  français (T3).

## 23/11/2026 — Audit de cohérence du travail du 19 au 23/11 et corrections urgentes (points 1 à 4)

- **Audit** : `docs/audit-coherence-ia-2026-11-23.md` (technique, cybersécurité, fonctionnel, RFC). Les décisions du
  fondateur restent ouvertes : prix FCFA (F5), faux écrans et prénoms de la vitrine (F2), promesses couple de la vitrine
  (F1), nom de l'espace couple qui détourne « Le Sanctuaire » du livre (F3), une seule Boussole (F4), offres inventées
  dans `ROADMAP.md`.
- **S1, tests PRO** : `POST /api/mobile/v1/tests/{slug}/reponses` vérifie maintenant `Entitlements::requireTest()`
  (`MobileTestController::submit`). Avant, un compte gratuit pouvait passer un test PRO en envoyant directement ses
  réponses. Test : `MobileTestTest::testFreeUserCannotSubmitProTestAnswers`.
- **S2, sessions mobiles** : réinitialiser ou changer son mot de passe, ou changer d'adresse e-mail, révoque toutes
  les sessions mobiles (`MobileTokenService::revokeAll`, dans `PasswordResetController::reset`,
  `ProfileController::changePassword` et `confirmEmailChange`). Tests ajoutés dans `PasswordResetTest` et `ProfileTest`.
- **T1, test en échec** : l'assertion FCFA de `OrganisationTest::testPackPricesWithVolumeDiscount` (oubliée lors du
  passage à 20 000 FCFA le 19/11) est corrigée. La suite était rouge depuis le 19/11 à 17 h 43.
- **T2, schéma** : migration de rattrapage `Version20261123080000` (valeurs par défaut des prix FCFA, noms des index
  du couple). `doctrine:schema:validate` repasse au vert. Elle ne modifie pas les prix enregistrés.
- **S3 et T4, partenaires sur le mobile** : l'invitation répond `200` dans tous les cas (avant : `201` quand le compte
  existait, ce qui révélait les comptes). Un refus supprime la demande comme sur le site (avant : statut `REJECTED`
  conservé, qui empêchait toute nouvelle invitation). Test :
  `MobileLot5Test::testPartnerInvitationDoesNotRevealAccountsAndRejectAllowsReinvite`. `docs/api-mobile.md` est mis à
  jour. L'application mobile ne dépendait d'aucun des deux comportements.
- **Résultat** : suite complète **460 / 460**, schéma synchronisé. **À déployer** (migration à exécuter sur Hostinger).
- **Règle pour la suite** : chaque entrée de ce journal indique le résultat de la suite **complète** (`php bin/phpunit`),
  pas d'un sous-ensemble.
- **Reste** : les points 5 à 10 de l'audit.

## 22–23/11/2026 — Correctif `/login`, Plan « Standout », Ma Boussole 360 & `RFC-002` Académie Pratique
- **Correctif redirection `/login` (`web/`, commit `69a4e33`)** : `LoginAuthenticator` nettoie désormais tout `target_path` pointant sur `/api/...` et ne redirige plus jamais un utilisateur vers `/api/rapide/aujourdhui` après connexion (test de non-régression dans `tests/Rapide/QuickDayTest.php`).
- **Plan « Standout » (Mobile & Vitrine)** :
  - `mobile-v1/` (commits `19c29e7`, `434668b`) : **Ma Boussole 360** (`BoussoleRadar.tsx` + `app/boussole/index.tsx` + cache SQLite + partage) et **Ma Pause de 5 minutes** (`app/pause/index.tsx`, libre d'horaire, hors-ligne via `useSync()`). `tsc --noEmit` (0 erreur), Vitest **24/24**.
  - `web/` (commits `c1f85b0`, `01d4785`, `7063f86`) : section aperçus mobiles (*Ma Boussole 360*, *Modeste*, *Modeste & Linda*) sur `landing.html.twig` (sans émojis, icônes Lucide : `DesignRulesTest` 3/3) et diagnostic public **`/boussole`** (`templates/shared/boussole.html.twig` + `assets/js/boussole-engine.js` : 24 affirmations symétriques 2+2 par plan, 18 textes de tranche, matrice complète 30 dynamiques croisées, gestion profils plats & égalités, 12 jeux d'actions calibrées). Validé par `tests/boussole-engine.test.mjs` (**15/15 profils limites**) et `SecurityGuardsTest` (**7/7**). Déployé et vérifié en production sur `https://kiinisawa.com/boussole`.
- **Spécification `RFC-002` (`web/`, commits `eab6935`, `2dd94cc`)** : `docs/roadmap/RFC-002-academie-pratique.md` — Académie Pratique sans certificats où chaque leçon alimente directement un des 6 plans via `AcademyPlanBridge`, avec Studio Auteur `/admin/academie` (brouillon / publié / retiré pour édition), stratégie vidéo Bunny Stream / Vimeo et import depuis `formation/courses_dump.json`.
- **Reste à faire (prochains chantiers)** :
  1. **Publication Google Play Store (`mobile-v1/`)** : passer `EXPO_PUBLIC_API_URL=https://kiinisawa.com`, ajouter `versionCode: 1` dans `app.json`, configurer `eas.json` et générer le `.aab`.
  2. **Implémentation Jalon 1 de `RFC-002` (Académie Pratique)** : entités, migration, `AcademyPlanBridge` et back-office `/admin/academie`.

## 21/11/2026 — Sanctuaire du Couple 360 (`RFC-001`) : Backend, API Mobile & Écrans natifs
- **Roadmap & Spécifications** : création de `docs/roadmap/ROADMAP.md` (trajectoire 1M € ARR) et `docs/roadmap/RFC-001-sanctuaire-couple-360.md`, indexés dans `docs/index.md`.
- **Backend Symfony (`web/`, commits `6c3325b`, `9ae348d`, `00e6077`)** :
  - Nouvelles entités `CoupleChallenge`, `CoupleChallengeDay`, `CoupleMeditation` + `streakDays` / `lastActivityDate` sur `Couple` + `INTENTION` sur `CoupleItem`. Migration `Version20261121103000` exécutée sur Hostinger.
  - `CoupleSanctuaryCatalog` (versets quotidiens, 4 défis de couple jour par jour, 3 parcours de méditation guidée, 5 signaux *Ping*) et `CoupleSanctuaryService` (validation croisée, streak, notifications push `NotificationType::COUPLE_SANCTUARY`).
  - `MobileCoupleController` : 17 routes sous `/api/mobile/v1/couple/...` (Vue 360 `/home`, projets, intentions, défis, méditations, ping, revue mensuelle `/review` et rapport croisé `/report`).
  - Tests : `tests/Mobile/MobileCoupleSanctuaryTest.php` (**42/42 tests Mobile + Couple passés**).
  - Vitrine `landing.html.twig` : section `#couple` enrichie avec les 4 pièces (*L'Autel*, *Le Jardin des Défis*, *L'Atelier*, *Le Salon*).
- **Mobile Expo (`mobile-v1/`, commits `c7a9324`, `3ec9d9c`)** :
  - 4 écrans natifs dans `app/couple/` (`index.tsx`, `meditation.tsx`, `challenges.tsx`, `projets.tsx`, `salon.tsx`).
  - Porte d'entrée progressive sur l'Accueil (`app/(tabs)/index.tsx`, visible uniquement si couple actif) + cache SQLite hors-ligne (`loadCoupleSanctuaryCache` / `saveCoupleSanctuaryCache`).

## 19/11/2026 — Sécurité mobile, parité tarifaire Euro/FCFA et synchronisation `kiini-mobile`
- **Audit sécurité mobile (`mobile-v1/`, commit `c0fd149`)** :
  - Protection anti-Open Redirect dans `src/notifications/routing.ts` et garde-fou défensif dans `app/_layout.tsx` (tests ajoutés dans `__tests__/notifications.test.ts`).
  - Forçage HTTPS strict en production (`!__DEV__`) dans `src/api/client.ts`.
  - Purge complète de `kv_store` (hors `account` et `push_token`) sur expiration 401 (`clearReadableCacheOnSessionExpiry()` dans `src/storage/localDb.ts`).
- **Parité stricte des tarifs FCFA / Euro (`web/`, commit `c6c3c4d`, migration `Version20261119183000`)** :
  - Décision du fondateur : même prix équivalent en Europe et en Afrique (taux fixe `1 € = 655,957 XAF`).
  - `AppConfig` & base de production Hostinger mis à jour : PRO mensuel **6 500 FCFA** (9,99 €), PRO annuel **52 500 FCFA** (79,99 €), Duo couple **78 500 FCFA** (119,99 €), Organisation **20 000 FCFA** / place / an (30,00 €).
- **Dépôt Git Mobile** : ancienne version (`mobile/`) archivée sur la branche `v0-legacy` de `github.com/ArlaisNTCHANA/kiini-mobile` ; `mobile-v1/` relié et poussé sur `origin/main`.

## 19/11/2026 — Application mobile native V1 : Lot 5 (Revues, Agenda, Partenaires, Défis, Couple & La Méthode)
- **Objectif** : Achever le périmètre complet de l'application mobile native KIINI SAWA.
- **Backend Symfony (`src/Mobile/`)** :
  - `MobilePlannerController` : `/api/mobile/v1/agenda` (GET liste bornée par date ou intervalle, POST création, PATCH mise à jour/complétion, DELETE suppression, isolation stricte par utilisateur).
  - `MobileReviewController` : `/api/mobile/v1/revues/rythmes` (les 5 respirations temporelles de KIINI SAWA via `Rhythms::forUser`), `/api/mobile/v1/revues/hebdo` (GET état et items des 4 plans, POST enregistrement appliquant scores/progrès aux plans et au journal via `WeeklyReviewService::save`), `/api/mobile/v1/revues/mensuelle` (GET/POST réservé Pro sans mention de prix).
  - `MobileCommunityController` : `/api/mobile/v1/partenaires` (liste des partenaires actifs et invitations reçues/envoyées), `/api/mobile/v1/partenaires/invitation` (invitation par email avec contrôle du quota gratuit), `/api/mobile/v1/partenaires/{id}/repondre` (acceptation ou refus), `/api/mobile/v1/defis` (défis collectifs et avancement), `/api/mobile/v1/couple` (vision commune, verset d'ancrage, code d'invitation conjoint).
  - `MobileMethodeSerializer` & `MobileMethodeController` : `/api/mobile/v1/methode/chapitres` (sommaire des 28 chapitres du livre KIINI SAWA, temps de lecture, statuts libre/pro), `/api/mobile/v1/methode/chapitres/{slug}` (texte intégral pour les membres Pro et chapitres publics, ou extrait pratique « L'essentiel » et exercice « Le Sanctuaire » pour les comptes gratuits avec zéro prix ni lien de paiement Apple 3.1.1).
  - Tests unitaires & intégration : `tests/Mobile/MobileLot5Test.php` (5 nouveaux scénarios complets). Suite mobile Symfony : **34 tests, 343 assertions (100 % passants)**. Suite Symfony globale : **455 tests, 8479 assertions (0 régression, 0 échec)**.
- **Mobile Expo (`mobile-v1/`)** :
  - `types/api.ts`, `storage/localDb.ts`, `api/client.ts` : typages stricts, cache SQLite complet pour l'agenda, les revues, les partenaires et les chapitres du livre hors connexion.
  - `i18n/fr.ts` : dictionnaires `planner`, `reviews`, `community`, `methode` conformes au lexique (vouvoiement, zéro terme banni, zéro émoji, zéro mention de tarif).
  - Écrans natifs créés :
    - `app/agenda/index.tsx` (agenda interactif au jour le jour, cases à cocher, création rapide avec modal, suppression).
    - `app/revues/hebdo.tsx` (assistant de revue hebdomadaire : victoires, difficultés, leçons, 3 priorités, évaluation des 4 plans).
    - `app/revues/rythmes.tsx` (tableau de bord des 5 rythmes de vie avec statuts colorés et boutons d'action).
    - `app/partenaires/index.tsx` (partenaires de redevabilité avec invitations et gestion des demandes, onglets défis collectifs et couple).
    - `app/methode/index.tsx` (sommaire visuel des 28 chapitres du livre).
    - `app/methode/[slug].tsx` (lecteur natif du livre, typographie soignée, bascule de taille de texte A-/A/A+, encart « Le Sanctuaire », navigation précédent/suivant, ProGate d'extrait gratuit).
  - Routage et menu : `app/(tabs)/menu.tsx` et `src/notifications/routing.ts` connectent directement toutes les routes vers les écrans natifs.
  - Tests & bundle : **23 tests Vitest passants**, **0 erreur TypeScript**, bundle iOS Expo exporté avec succès (3 552 modules, 5.8 MB HBC).

## 2026-11-19 · Application Mobile KIINI SAWA (Expo SDK 58 / React Native) : Lot 4

- **Lot 4 (« Me connaître » : Mes tests & Mon profil KIINI SAWA)** :
  - **Backend Symfony (`web/src/Mobile/`)** :
    - `MobileTestSerializer` : catalogue structuré des tests par groupes (`personnalite`, `motivations_et_dons`, `auto_evaluations`), questions adaptées pour les blocs de choix forcé DISC (24 blocs de 4 mots) et les échelles de Likert 1-5 / 1-10, rapports détaillés avec points forts, vigilances, figures bibliques et synthèse croisée `ProfileSynthesis`.
    - `MobileTestController` : endpoints `/api/mobile/v1/tests` (GET), `/tests/{slug}/questions` (GET avec `requireTest`), `/tests/{slug}/reponses` (POST pour passation et enregistrement via `AssessmentService`), `/tests/{slug}/rapport` (GET), `/tests/synthese` (GET avec aperçu neutral gate si gratuit ou complet si PRO).
    - `MobileTestTest` : 6 tests unitaires couvrant le catalogue, les questions DISC, l'obligation PRO sans prix, la soumission et consultation de rapport, l'isolation et la synthèse.
    - Suite complète Symfony : **450 tests passants (8 405 assertions), 0 erreur**.
  - **Mobile (`mobile-v1/`)** :
    - Écran `app/tests/index.tsx` : catalogue complet par groupes, carte d'accès à la synthèse « Mon profil KIINI SAWA », statuts d'avancement, temps estimés et consultation des rapports.
    - Écran `app/tests/[slug]/passer.tsx` : passation interactive fluide, support des blocs DISC (choix du plus (+) et du moins (-)) et des échelles 1-5 / 1-10, jauge d'avancement, validation complète et enregistrement.
    - Écran `app/tests/[slug]/rapport.tsx` : rapport complet adapté au test (profil naturel vs adapté pour DISC, axes de Jung, centre et intégration de l'Ennéagramme, top 3 des dons spirituels, jauges Big Five).
    - Écran `app/tests/synthese.tsx` : synthèse croisée « Mon profil KIINI SAWA » (zone de vocation, convergences des tests, ressources, fragilités et priorités de cheminement).
    - Navigation et routage : synchronisés depuis le menu et les notifications.
    - Tests Vitest : 22 tests passants, compilation iOS bundle 100 % OK.
- **Reste à livrer pour le prochain lot** :
  - **Lot 5** : Revues & bilans (hebdo, mensuel), Agenda (`planner`), Partenaires (`social`), Ensemble & La Méthode.

## 2026-11-19 · Application Mobile KIINI SAWA (Expo SDK 58 / React Native) : Lots 2 & 3

- **Lot 2 (Notifications push distantes Expo & rappels quotidiens)** :
  - **Backend symfony (`web/src/Mobile/`)** :
    - Entité `MobilePushDevice` (table `mobile_push_device` avec user_id cascade, hash SHA-256 du token Expo, horodatage, nom d'appareil, plateforme).
    - `ExpoPushSender` : validation regex stricte `Expo(nent)?PushToken[...]`, limitation à 10 appareils par compte, envoi groupé HTTP vers `https://exp.host/--/api/v2/push/send`, élagage automatique des appareils révoqués (`DeviceNotRegistered`), mockable en test via closure statique.
    - `MobilePushSenderDecorator` : décore `PushSender` (`#[AsDecorator(decorates: PushSender::class)]`) pour injecter sans toucher au code existant l'envoi Expo Push dans `ReminderService` et `Notifier`.
    - `MobileNotificationController` : endpoints `/api/mobile/v1/notifications/appareil` (POST/DELETE), `/preferences` (GET/PATCH), `/essai` (POST, rate limit 3/h), `/` (GET), `/{id}/lu` (POST), `/tout-lire` (POST).
    - Tests `MobileNotificationTest` : 6 méthodes testant l'enregistrement d'appareil, la limitation du test, l'isolation des notifications et le décorateur push.
  - **Mobile (`mobile-v1/`)** :
    - `expo-notifications` & `expo-device` installés et configurés.
    - Écran `app/notifications.tsx` : activation appareil, notification d'essai immédiate, réglages fins (heure matinale, jour hebdomadaire, 8 types d'alertes), liste in-app et marquer tout comme lu.
    - Service `src/notifications/service.ts` & `src/notifications/routing.ts` : synchronisation token, listeners de notification au tap pour router vers l'écran natif adéquat.
    - Accès direct via l'icône cloche dans `app/(tabs)/index.tsx` et carte dédiée dans `app/(tabs)/menu.tsx`.
- **Lot 3 (Mes 6 Plans & Progression)** :
  - **Backend symfony (`web/src/Mobile/`)** :
    - `MobilePlanSerializer` : sérialise les 6 plans (`VIE`, `CROISSANCE`, `DEVELOPPEMENT`, `HYGIENE`, `INTENDANCE`, `TRANSMISSION`) avec progression `PlanProgress`, 100 % libellés français, zéro terme banni, et contenu riche.
    - `MobilePlanController` : `GET /api/mobile/v1/plans` (vue d'ensemble des 6 plans, pourcentage global, compteurs) et `GET /api/mobile/v1/plans/{key}` (fiche détaillée d'un plan avec contrôle `Entitlements::require()` renvoyant 403 `pro_required` sans url de paiement).
    - Tests `MobilePlanTest` : 5 méthodes testant la progression, les libellés, le verrouillage PRO et l'isolation des données.
  - **Mobile (`mobile-v1/`)** :
    - Composant `src/components/plans/PlansCircle.tsx` : Cercle SVG interactif reproduisant fidèlement la figure du livre KIINI SAWA (Plan de Vie au centre doré, 5 satellites reliés par rayons et anneaux de progression, cibles tactiles accessibles).
    - `app/(tabs)/plans.tsx` : affichage hors-ligne instantané via cache SQLite, barre de progression globale, cercle SVG tactile, liste des cartes avec pourcentages, prochaines étapes et liens d'action.
    - `app/plan/[key].tsx` : écran de lecture native riche complet pour chacun des 6 plans, checklist des étapes franchies, sections détaillées adaptées à chaque plan, cache SQLite et modal `<ProGate />` si verrouillé.
    - Tests Vitest : 21 tests unitaires passants (`notifications.test.ts`, `apiClient.test.ts`, `syncEngine.test.ts`, `lexicon.test.ts`).
    - Validation Expo iOS Bundle : `npx expo export --platform ios` compilé à 100 % avec zéro erreur.
- **Reste à livrer pour les prochains lots** :
  - **Lot 4** : Mes tests & Mon profil KIINI SAWA (passation des tests, lecture des rapports et synthèse).
  - **Lot 5** : Revues & bilans (hebdo, mensuel), Agenda (`planner`), Partenaires (`social`), Ensemble & La Méthode.

## 19/11/2026 — Application mobile native V1 : Lot 1 (API `/api/mobile/v1` + socle Expo `mobile-v1/`)
- **Choix validés avec le fondateur** : nouveau projet indépendant `../mobile-v1/` (sans toucher à `mobile/`) ; approche **consultation seulement** pour l'offre PRO (zéro prix, zéro lien de paiement dans l'app/API, conformité Apple 3.1.1 / EU DSA) + architecture prête pour IAP (`Subscription::$billingSource`, hook `useEntitlements` et composant `<ProGate>` côté mobile) ; français uniquement et thème clair du livre au lancement.
- **Module `src/Mobile/`** : entité `MobileToken` (table `mobile_token`, empreintes SHA-256 uniquement, `rotated` pour distinguer réutilisation suspecte et simple déconnexion, `ON DELETE CASCADE` vers `user`, migration `Version20261119120000`), colonne `billing_source` sur `subscription`, pare-feu `mobile_api` sans session (`MobileBearerAuthenticator`), rotation à usage unique des jetons (`MobileTokenService`), limitation de débit (`mobile_login_ip`, `mobile_login_account`, `mobile_refresh`), purge dans `app:reminders:send`, `ProRequiredSubscriber` adapté sans `upgradeUrl` sur `/api/mobile/*`.
- **Points d'accès `/api/mobile/v1`** : `connexion`, `jeton`, `deconnexion`, `moi` (GET/PATCH), `compte` (DELETE avec `SUPPRIMER` + mot de passe), `aujourdhui` & `sync` (contrat `QuickDay`), `rituels` (CRUD + plafond gratuit), `journal` & `journal/carnets` (pagination SQL bornée, filtres). Documentation complète : `docs/api-mobile.md`, `docs/modules/mobile.md`.
- **Tests Symfony** : `tests/Mobile/MobileApiTest.php` (12 scénarios dont mode strict `APP_SAME_ORIGIN_STRICT=1`, réutilisation de jeton, isolation utilisateurs, suppression cascade). **Piège noté** : dans les tests fonctionnels avec `AbstractBrowser`, appeler `restart()` avant toute requête devant être dépourvue d'en-tête `Referer` automatique.
- **Reste pour les lots suivants** : Lot 2 (notifications Expo Push + préférences), Lot 3 (lecture des 6 plans), Lot 4 (Mes tests & rapports), Lot 5 (revues, agenda, partenaires, couple/organisation, La Méthode).

## 19/11/2026 — Audit de sécurité : contre-vérification, 4 derniers détails
- Invitations d'organisation sans licence : plafond de 5/jour **par compte** (et non par organisation), et 2 créations d'organisations par compte et par jour (`org_create`). Avec licence : par organisation.
- `json_safe` : les séparateurs U+2028/U+2029 sont maintenant remplacés par ` `/` ` littéraux (le remplacement précédent ne faisait rien) ; `JSON_PARTIAL_OUTPUT_ON_ERROR` retiré (en conflit avec `JSON_THROW_ON_ERROR`), `JSON_INVALID_UTF8_SUBSTITUTE` ajouté pour qu'un texte mal encodé ne fasse pas échouer la page.
- Écran de vérification « leurre » : jetons CSRF contrôlés et délai de 60 s sur le renvoi, mêmes messages qu'une vraie inscription (test comparatif).
- `.env.prod` : `TRUSTED_HOSTS='^(www\.)?kiinisawa\.com$'` (ni localhost ni IP en production ; le développement garde `.env`). Tests : `tests/Shared/FinalChecksTest.php`.

## 19/11/2026 — Audit de sécurité : 4 lots de corrections (commits par lot)
- **Lot 1 (XSS)** : filtre Twig `|json_safe` (JSON_HEX_*) à la place de tout `|json_encode|raw` ; Mermaid `securityLevel: 'strict'` + `htmlLabels: false` + filtre `|mermaid_label` ; directive Alpine du rituel via `data-title` ; `ksEscape()` avant `insertAdjacentHTML` dans l'assistant. Test : `tests/Shared/Twig/XssHardeningTest.php`.
- **Lot 2** : Web Push : liste blanche d'hôtes officiels + contrôle DNS (adresses privées refusées, à l'inscription et à chaque envoi), 10 appareils max, essai limité à 3/h (`PushEndpointValidator`, `RateLimits`). Mode rapide : identifiant opaque de compte (HMAC) lié aux données et aux saisies en attente ; effacement local à la déconnexion (+ désabonnement push) et au changement de compte. **Écart assumé** : sur une session expirée (401) la copie des données du jour est effacée tout de suite, mais les saisies en attente sont gardées (liées à leur compte) pour ne pas perdre un journal écrit hors ligne. Anti-spam : invitations d'organisation (5/jour sans licence, 100 avec), couple, plan par e-mail, mot de passe actuel (profil), formulaires publics. Tests : `PushSecurityTest`, `QuickDayTest`, `AntiSpamTest`.
- **Lot 3** : agenda borné à 366 jours (400 sinon) ; `UserRepository::findAdmins()` ; journal paginé en SQL (50 max, décalage plafonné, COUNT) ; rappels par lots de 200 avec `em->clear()`. Test : `DosProtectionTest`.
- **Lot 4** : contrôle d'origine « fail-closed » dès qu'un cookie de session est présent (variable `APP_SAME_ORIGIN_STRICT`, 1 par défaut, 0 dans phpunit sauf `SameOriginStrictTest`) ; `trusted_hosts` (variable `TRUSTED_HOSTS`, défaut `localhost`, `127.0.0.1`, `kiinisawa.com`, `www.kiinisawa.com` : **ajouter tout autre nom utilisé, ex. une IP pour tester sur téléphone, dans `.env.local`**) ; jetons CSRF sur intendance, axes, principes, assistant, auto-évaluations ; droits PRO sur axes, principes, PDF des auto-évaluations ; rituels : off-by-one corrigé (le nouveau rituel n'est pas dans la collection) ; `DocController` ADMIN + Parsedown sûr ; parrainage : alias d'e-mail et récompense par IP (migration `Version20261119054400`) ; inscription et changement d'e-mail sans énumération ; déconnexion en formulaire POST + jeton (`logout_form`) ; plus aucune route « toutes méthodes » ; `nelmio/api-doc-bundle` retiré ; scripts `bin/ops` en 600/700. Tests : `CsrfTokensTest`, `AccessControlTest`, `ReferralSecurityTest`, `EnumerationTest`, `SameOriginStrictTest`, `OpsScriptsTest`.
- Tests : `FixedCsrfTokenManager` (environnement de test seulement) remplace le gestionnaire de jetons ; les compteurs de limitation sont vidés au début de chaque exécution (`tests/bootstrap.php`). Vérifié aussi dans le navigateur local : déconnexion par formulaire, enregistrement automatique de l'assistant avec vrai jeton (200), jeton absent ou falsifié (400).
- **À vérifier après déploiement** : `chmod 600 .env.local.php` doit rester lisible par PHP-FPM (même utilisateur sur Hostinger) ; le site doit répondre depuis kiinisawa.com ; l'application mobile native (jetons, sans cookie) n'est pas touchée par le mode strict.

## 19/11/2026 — Offre gratuite resserrée : 2 tests gratuits (option A)
- Décision du fondateur : l'offre gratuite contenait trop (les 6 tests, leurs rapports et leurs PDF). Désormais **DISC et Dons spirituels** gratuits (liste réglable dans Admin › Configuration, `AppConfig::freeTests`, migration `Version20261119045553`) ; les 4 autres tests, les PDF des rapports et les lectures croisées sont PRO. L'ancien interrupteur « Tests » (`enableDiscoveryFree`) n'était lu par aucun écran : retiré du formulaire.
- Les résultats déjà obtenus restent consultables (seuls passer/repasser un test PRO est bloqué). Tableau gratuit/PRO, vitrine (`vitrine.s8.09`, `s8.22`), tests, page publique de résultat partagé, liste « premiers pas » et e-mail du jour 3 (renvoient vers un test gratuit) mis à jour. Détail dans `docs/modules/abonnement.md`.
- Mesurer l'effet : `/admin/entonnoir` (comptes, passages à PRO) avant et après ; revoir la liste (ou passer à un rapport allégé, option B) si les gens s'arrêtent trop tôt. Les anciens utilisateurs de production gardent leurs résultats ; à la mise en ligne la liste vaut la valeur par défaut (colonne vide).

## 18/11/2026 — Passation de la version mobile native (Expo)
- Le fondateur confie l'application mobile native à une autre IA. Instructions complètes : **`docs/passation-mobile-expo.md`** (mission, état des lieux, règles permanentes, contrainte des stores et du paiement manuel, API à construire dont l'authentification par jeton, contrat du mode rapide à réutiliser, design, étapes et critères, publication, questions à poser au fondateur).
- Points d'attention transmis : l'ancienne app `mobile/` est gelée (branchée sur l'ancien site) ; `docs/api.md` est périmé ; l'application ne doit ni vendre ni afficher de prix tant que le fondateur n'a pas tranché (règles Apple/Google sur les achats intégrés) ; la soumission aux stores attend les pages légales complétées.

## 18/11/2026 — PWA : « Aujourd'hui en un geste » hors connexion (étape 1)
- Demande : améliorer l'application installable avant de lancer l'application native (qui réutilisera l'API). Détail et règles dans `docs/modules/rapide.md`.
- Page `/rapide` + `GET /api/rapide/aujourdhui` + `POST /api/rapide/sync` : rituels, bilan du jour, journal, prochaine étape ; saisies faites hors ligne gardées dans IndexedDB puis envoyées sans doublon (`sync_operation`, migration `Version20261118203319`). Service worker v3 : garde la coquille, renvoie les écrans du quotidien vers `/rapide` hors connexion. Lien dans le menu mobile, raccourci du manifeste, page « hors ligne » qui y mène.
- Vérifié dans le navigateur : saisie hors ligne simulée → rechargement → envoi automatique. **Reste à vérifier sur un vrai téléphone** (iPhone : installer sur l'écran d'accueil ; couper le réseau ; cocher un rituel ; rétablir).
- Piège de test : `createUser` laisse la collection `habits` vide en mémoire ; vider l'EM (`clear()`) avant la requête. Et `DailyLog` est dans `App\PlanDHygiene\Entity` (l'import erroné a été commis deux fois).
- Reste (étapes suivantes de la PWA) : lecture hors ligne des plans et du journal complet, revues de la semaine et du mois hors ligne, notifications iPhone à vérifier sur appareil.

## 18/11/2026 — Textes du site modifiables dans l'administration (phase 1)
- Demande du fondateur : ne plus mettre de texte dans le code, tout pouvoir modifier ; **brouillon puis publication** ; périmètre voulu : vitrine et pages légales, contenu de la méthode, emails et notifications, livre. Phase 1 faite : le moteur (module `Contenu`, voir `docs/modules/contenu.md`), la vitrine (121 textes), les 4 pages légales (documents Markdown `content/textes/legal/`).
- `Admin › Textes du site` (`/admin/textes`) : recherche, filtres, brouillon, mode aperçu, publication, historique, retour à l'origine. `app:launch:check` compte les `[À COMPLÉTER]` du texte **en ligne** (le juriste peut donc compléter les pages légales sans développeur).
- La conversion de la vitrine a été faite par script puis comparée au rendu d'origine (HTML identique) ; les codes sont `vitrine.sN.NN` (N = section, ordre), stables tant qu'on n'en réutilise pas.
- Migration `Version20261118194935` (tables `site_text`, `site_text_version`) : à jouer en production (le déploiement le fait).
- Reste (phases 2 à 4) : textes écrits en PHP, emails/notifications et reçus, livre, et brouillon/publication pour les textes des tests.

## 18/11/2026 — Schémas du Plan de Vie interactifs
- `Methode\Service\BookFigures` : le contenu de 7 schémas du livre (convictions, cinq réseaux, héritage, trois horizons, corps-âme-esprit, Équation du But avec ses trois exemples, cascade de l'alignement), tiré mot pour mot des tableaux et listes de `content/livre/`. Fonction Twig `book_figure_data(name)` ; `methode/_figure.html.twig` affiche alors le composant `shared/_cycle_figure.html.twig` et replie l'image du livre en dessous. Un schéma sans entrée reste une image (racines, trois témoins, revues, intendance).
- Tests : `BookFiguresTest` (chaque phrase figure dans le chapitre source : si le livre est resynchronisé et change, le test le dit) et `BookFiguresPagesTest` (étapes 1, 2, 10, 11, 12, 13 de l'assistant).
- Piège : l'assistant Plan de Vie n'est accessible qu'après l'onboarding, et les étapes Croissance/Hygiène exigent PRO (compte local de test : onboarding « Plus tard », puis étape 1 gratuite).

## 18/11/2026 — Barre mobile, « Mes rythmes », schéma des saisons interactif
- **Barre du bas (téléphone)** : les libellés sont toujours visibles sous les icônes (10 px, sans majuscules ni espacement, pour tenir à cinq entrées sur 375 px).
- **« Mes rythmes »** (`/bilan/rythmes`, `Bilan\Service\Rhythms`, `templates/bilan/rhythms.html.twig`) : jour, semaine, mois, saison, année, chacun « fait / à faire / en retard / pas encore l'heure ». « En retard » n'apparaît que pour qui a déjà l'habitude du rythme (jamais pour un débutant). Lien depuis Bilan et la navigation « Mon quotidien » ; revue du mois verrouillée PRO comme ailleurs.
- **Schéma des saisons interactif** : composant réutilisable `shared/_cycle_figure.html.twig` (rangée d'étapes à toucher + détail, Alpine) utilisé sur « Ma saison » avec `Seasons::KINDS`, « Vous êtes ici » sur la saison en cours ; l'image du livre reste en dessous, repliée. Autres schémas : le cycle de l'intendant a déjà son étapes vivantes (`stw-steps`) ; les schémas du Plan de Vie (équation, convictions, réseaux, héritage, hygiène, horizons) restent des images du livre, à rendre interactifs avec le même composant si souhaité.
- **Bug corrigé** : `NextStep` (déjà en production) importait `App\Bilan\Entity\DailyLog` au lieu de `App\PlanDHygiene\Entity\DailyLog` : toute personne ayant un Plan d'Hygiène aurait eu une erreur 500 sur l'accueil dès 18 h. Les tests passaient parce que leurs comptes n'avaient pas de plan d'hygiène ; test de régression ajouté (`RhythmsTest::testDayRhythmWithAHygienePlan`). Piège : tester aussi avec un compte complet.

## 18/11/2026 — Bibliothèque de personnages rattachée au type (phase 3)
- 16 types × 2 figures : lectures `TYPE:<CODE>` (`content/lectures/types.json`, 32 lectures sur 29 fiches). `TypeProfiles` : slots remplacés (Boaz, Esther, Daniel, Marie mère de Jésus, Noé, Jonathan, Samson, Élie, Salomon, Élisabeth, etc. ; plus de « Paul à Athènes » ni « Moïse et Jéthro »). Rapport « Type » : fiches complètes (3 épisodes en PDF), 13 pages pour les 16 types, aucune page orpheline.
- Tests : couverture des slots (ennéagramme, dons, type) et clés des fichiers `content/lectures/*.json` (une clé inconnue était ignorée en silence).
- Les rapprochements « type » sont les plus fragiles (les récits ne décrivent pas de types) : le texte le dit explicitement dans « Ce que le texte ne permet pas de dire ».
- Les trois tests (ennéagramme, dons, type) sont maintenant rattachés à la bibliothèque. Reste : relecture par le fondateur (champ `relecture`).

## 18/11/2026 — Bibliothèque de personnages rattachée aux dons (phase 2)
- 15 dons × 2 figures : chaque figure a une fiche avec une lecture `GIFT:<CODE>` (`content/lectures/dons.json`, 29 lectures sur 21 fiches existantes) ; nouvelle fiche Philippe (`content/personnages/10-philippe.json`). `GiftProfiles` : slots remplacés par des personnages individuels (plus de groupes ni de paraboles : Les sept serviteurs, Églises de Macédoine, Bon Samaritain…).
- Rapport des dons : section « Des figures bibliques qui ont ces dons » après les 3 dons principaux (fiches complètes à l'écran ; en PDF 2 épisodes par fiche, 21 pages, aucune page orpheline sur 8 combinaisons mesurées).
- Citations des lectures vérifiées contre la LSG avec un script temporaire (extrait des « … » dans les traits) : à refaire pour toute nouvelle lecture. Piège : un macro Twig ne voit pas le contexte (paramètre `maxEpisodes` à passer explicitement).
- Reste : phase type (16 types × 2 slots, lecture `TYPE:<CODE>`).

## 18/11/2026 — Bibliothèque de personnages rattachée à l'ennéagramme (phase 1)
- Fiches pilotes migrées en JSON ; 7 nouvelles fiches (Jonas, Marthe, Jacob, Saül, Jérémie, Samson, Isaac) dans `content/personnages/09-*.json` ; lectures par test (`lenses`, clé `ENN:<type>`) dans `content/lectures/enneagramme.json` pour 11 fiches existantes.
- `EnneagramProfiles` : Tabitha→Barnabé, David le psalmiste→David, L'Ecclésiaste→Thomas, Jacques et Jean→Moïse. Chaque personnage du rapport ennéagramme a une fiche « Son profil » (test de couverture).
- Reste : mesurer la pagination PDF des 9 rapports ennéagramme ; phases dons (`GIFT:`) et type (`TYPE:`) ; fiches toutes « en cours de relecture ».
- Pagination PDF ennéagramme mesurée (9 types, 15 pages chacun, aucune page < 35 %) : en PDF, la citation finale et la « question à méditer » passent en tête de la page « Pour grandir », et les fiches à lecture ne montrent que 5 épisodes.

## 18/11/2026 — Six fiches fragiles remplacées

Demande du fondateur : remplacer les personnages à matière mince par des personnages « évidents ». Remplacements (même code DISC) : Béréens → Nicodème (C), Lydie → Jonathan (IS),
Tabitha → Élisabeth (SI), Jean le disciple bien-aimé → Marie de Béthanie (SI), Luc → Aquilas et Priscille (CI), Betsaleel → Boaz (CS). `DiscCombos` mis à jour (nom, référence,
phrase courte), fichiers JSON nettoyés, nouveau `08-…json`. Pagination PDF contrôlée (profils C, CI, CS, IS, SI). Citations vérifiées par le test.

## 18/11/2026 — Accueil « Ma prochaine étape » et cercle des plans sur téléphone

**Accueil :** `Dashboard/Service/NextStep` (règles ordonnées : fondations, bilan du jour à partir de 18 h, revue de la semaine samedi soir et dimanche, parcours de
90 jours, plan accessible le moins avancé, sinon relire le Plan de Vie) renvoie UNE action + deux « ensuite » ; carte en tête de l'accueil (au-dessus du bandeau sur
téléphone), les anciens encarts du parcours disparaissent (l'action est dans la carte). Tests : `tests/Dashboard/NextStepTest.php` (heure injectable), `JourneyTest` adapté.
**Cercle des plans :** vérifié en vue téléphone (375 px) : libellés trop petits ; disques 62 → 72, police 14 → 16 px (15 px sous 420 px), cadrage du `viewBox` resserré,
« LE SYSTÈME » masqué sous 560 px. **Reste :** libellés sous les icônes de la barre du bas sur téléphone ; page « Mes rythmes ».

## 18/11/2026 — Fiches de personnages modifiables dans l'administration

Demande du fondateur. Les fiches deviennent une source de `Content` (`figures.library`) : édition par la page « Textes des tests » existante, sans nouveau code d'écran.
Champ `relecture` (« en cours » / « faite ») remplace `reviewed`. Citations LSG et noms de rattachement verrouillés. Libellés lisibles pour les indices du profil.
Piège : `Content::defaults()` est appelé souvent ; `BiblicalFigures::raw()` met les fichiers JSON en cache.

## 18/11/2026 — Bibliothèque de personnages bibliques (32 fiches)

« Lance le tout » : 29 fiches ajoutées (JSON dans `content/personnages/`) sur le modèle validé (profil et indices d'abord, puis récit, fragilité,
grâce, Christ au centre ; cadre doctrinal du fondateur ; LSG). Citations vérifiées par le test (texte biblique local, non versionné). Cas
honnêtes signalés dans les fiches : indices indirects (Timothée, Béréens, Luc), fiches minces (Lydie, Tabitha, Betsaleel), contre-indices (Moïse « fort
patient » ; Jean « fils du tonnerre »), livre d'Esther qui ne nomme pas Dieu. **Non relues par le fondateur** (`reviewed: false`). Reste : relecture doctrinale,
extension aux autres tests (ennéagramme, dons, type) avec la même bibliothèque.

## 18/11/2026 — Fiches bibliques : le profil d'abord

Retour du fondateur : pour qu'un rapprochement soit crédible, il faut montrer d'abord les traits du profil chez le personnage, ensuite
commenter. Refonte de `_figure_sheet.html.twig` : en tête « Son profil : SC » avec, pour chaque dimension DISC, la définition de l'application
(essence), la solidité des indices (nombreux / réels / indirects) et un tableau trait · ce que dit le texte · référence ; puis « Ce que le texte ne
montre pas » (contre-indices) et les limites ; ensuite seulement le lien avec le lecteur, le récit (épisodes étiquetés par dimension), la
fragilité, la grâce et le Christ au centre. Le bloc « Notre lecture » disparaît (remplacé par le profil). Données : `BiblicalFigures[...]['profile']`.
Test : chaque dimension a au moins deux indices référencés (verset cité).

## 18/11/2026 — Fiches de personnages bibliques (pilote)

À la demande du fondateur (les deux phrases par figure « n'apportaient rien ») : 3 fiches complètes (Élie, Joseph, Timothée), structure
« Dans le texte / Notre lecture / Fragilité / Grâce / Le Christ au centre », citations LSG vérifiées par `bin/outils/bible.py` et
`BiblicalFiguresTest`. Texte biblique téléchargé avec l'accord du fondateur dans `var/bible/` (non versionné). Intégrées au rapport DISC (profils D
et SC) ; les mentions courtes « même famille » sont masquées quand des fiches existent. Pagination PDF contrôlée (aucune page orpheline).
**En attente :** relecture doctrinale par le fondateur (`reviewed => false`), puis extension.

## 18/11/2026 — Le regard des proches entre dans le moteur

`InsightEngine` reçoit le regard des proches (DISC, dons) via `ProfileSynthesis::closeOnes()` ; 6 lectures nouvelles + renfort de la lecture
« adaptation excessive » ; invitation à demander ce regard dans la synthèse (`closeInvites`). Anonymat : seules les synthèses ≥ 3 réponses.
Tests : `InsightEngineTest` (15, dont un parcours réel avec trois réponses de proches). **Reste :** recalibrage avec de vrais résultats ;
ajouter d'autres tests au regard des proches (Big Five, ennéagramme) si le fondateur le décide.

## 18/11/2026 — Lectures internes et vocation nuancée

Suite de l'InsightEngine : 7 rapprochements de facettes du Big Five et 7 paires de dons (lectures « internes », drapeau `internal`),
`insightsFor()` renvoie jusqu'à 2 lectures croisées + 1 interne par rapport ; panneau titré « Une lecture au sein de ce test » quand il n'y a
que de l'interne. Zone de vocation : bloc « Comment l'exercer, selon votre tempérament » (`vocationNuances`, Big Five). Tests :
`InsightEngineTest` (10). Pagination PDF contrôlée. **Non vu à l'écran :** aucun profil de test local ne déclenche encore ces règles internes ;
elles sont couvertes par des tests unitaires.

## 18/11/2026 — « Mon profil KIINI SAWA » : lectures croisées (InsightEngine)

Demande du fondateur : une vraie intelligence derrière la synthèse, et pas seulement un agrégat. Ajouté : `Service/InsightEngine.php`
(13 règles explicites, preuves citées, confiance selon le nombre de tests, question + premier pas relié à un plan), page « Lectures croisées »
dans la synthèse, encart « Ce que vos autres tests ajoutent » dans chaque rapport (écran et PDF, PRO ; teaser pour le gratuit),
partial `report/_insight_card.html.twig`. Pagination PDF mesurée (synthèse 10 p., ennéagramme 12 p., DISC 14 p., aucune page orpheline).
**Honnêteté :** règles et seuils écrits à la main, non validés statistiquement ; `ENNEAGRAM_EXPECTS` est une lecture usuelle, pas une
preuve. **Reste :** recalibrer avec de vrais résultats ; lire aussi le regard des proches (ConfirmationService) dans le moteur ; lectures
internes à chaque test (facettes Big Five qui se contredisent, combinaisons de dons) ; moteur de vocation qui pèse aussi ennéagramme et Big Five.

## 18/11/2026 — DISC : « Regard croisé » avec le Big Five

Le test « Style comportemental » n'est plus proposé depuis l'arrivée du DISC (absent de `Instruments::GROUPS`, voir `DiscTest`) ; il ne
sert qu'à relire d'anciens rapports. Son calcul (`Scorer::style`) existe déjà dans tout résultat Big Five / Profil complet.
Ajout : `ReportBuilder::crossView()` confronte le DISC au style déduit du Big Five le plus récent (confirmé / voisin / nuancé) sans
mélanger les scores ; bloc dans `disc/_report.html.twig` (page « Votre profil en situation », écran et PDF) ; sans Big Five, invitation
à le passer (écran seulement). Branché dans `AssessmentController::report` et `reportPdf`. Tests : `tests/Personnalite/CrossViewTest.php`.
Pagination PDF mesurée : toujours 13 pages, aucune page orpheline.

## 18/11/2026 — Rapports de tests : PDF plus propre (DISC mesuré)

Constat sur le PDF DISC du fondateur (16 pages) : 3 pages presque vides (5, 7, 12 : quelques lignes débordées), page de garde bleu
nuit hors palette du livre, pastille « Modérée » mal alignée, question réflexe coupée (« ? » seul en bout de ligne).
Corrigé (rendu local mesuré : 13 pages, aucune page orpheline) : compaction du PDF (interligne, marges, tuiles) dans
`report/_styles.html.twig`, boussole plus petite, hauteur minimale des tuiles « vu par les autres », espaces insécables avant « ? »,
intensité en étiquette lisible, page de garde en vert du livre (`report/_cover.html.twig`, commune à tous les rapports).
Outil de mesure : générer le PDF via un test temporaire (`tests/Personnalite/DiscTest.php` montre comment créer un `Assessment`), puis
le rendre avec PDFKit (swift) ; poppler/ImageMagick absents sur le Mac. **Pas encore revu :** écran (page web du rapport), Big Five,
ennéagramme, dons, synthèse : mêmes styles communs, à contrôler de la même façon.

## 18/11/2026 — Icônes affichées en texte et disparues (suite du lot A)

Deux défauts hérités du remplacement des émojis : (1) des balises `<i data-lucide=…>` rangées dans des tableaux Twig étaient
imprimées échappées (« <i data-lucide… » visible sur Organisation, Couple, humeurs du journal et de l'accueil, états de la
carte du Plan de Vie) → `|raw` ajouté ; (2) Turbo remplaçait le `<body>` sans redessiner les icônes (menu sans pictogrammes
après un clic) → `turbo:load/render/frame-load` appellent `lucide.createIcons()`. Étiquettes des diagrammes Mermaid : plus de balise
d'icône (elle y était invisible). Gardes : `NavigationTest::testNoIconMarkupIsDisplayedAsText`, `DesignRulesTest::testIconsAreRecreatedAfterTurboNavigation`.
Leçon : après tout remplacement en masse, rendre les pages et chercher `&lt;i data-lucide` dans le HTML.

## 18/11/2026 — Vitrine : figure des six plans en SVG

L'image du livre (`fig_6plans.png`) affichait « Plan de Développement » sans « personnel » ; `figs.py` ne se régénère pas sur ce Mac
(`rsvg-convert` et polices Carlito/Caladea absents, sortie vers `/home/claude/...`). La vitrine utilise donc un SVG intégré :
`templates/dashboard/_six_plans_figure.html.twig` (noms complets, couleurs du livre). Le cercle de « Mes plans » dit aussi
« Développement personnel ». **Reste (auteur) :** corriger la ligne 102 de `figs.py` (`"Plan de\nDéveloppement\npersonnel"`) pour le livre.

## 18/11/2026 — Bascule vers kiinisawa.com (code prêt, pas encore déployé)

Décision du fondateur : l'application passe de `app.kiinisawa.com` à `kiinisawa.com` (le site Hostinger Website Builder
est abandonné ; personne n'a encore reçu de lien `app.`, donc pas de redirection). Fait : références remplacées
(README, AGENTS, `env.prod.local.exemple`, page d'installation, rapport de test, brief média, `app:launch:check`,
`docs/mise-en-ligne.md`), test `tests/Shared/CanonicalDomainTest.php` (aucune mention de `app.` dans templates/src/config).
**Reste, côté serveur (à faire par le fondateur puis par moi) :** désactiver le Builder, ajouter `kiinisawa.com` à
l'hébergement vers `~/kiini/public`, SSL ; puis changer `DEFAULT_URI` dans `~/kiini/.env.prod.local`, mettre à jour
`HOSTINGER_DIR` dans `bin/ops/hostinger.local` si le dossier change, vider le cache, vérifier connexion, email, installation.
Connexions, notifications poussées et installation sur téléphone sont liées à l'adresse : tout le monde se reconnecte une fois.

## 2026-11-17 · Lot C : le cercle des plans vivant sur « Mes plans »

- **« Mes plans »** : la figure fixe des 6 plans est remplacée par un cercle SVG interactif
  (`plan_de_vie/_plans_circle.html.twig`) : chaque plan est un lien, entouré d'un **anneau de progression** ; survol, focus
  clavier ou premier toucher (mobile) affichent ses étapes et un bouton « Prochaine étape ». Plans PRO verrouillés : lien
  vers l'abonnement. Couleurs `--plan-*`.
- **`PlanDeVie\Service\PlanProgress`** : 3 ou 4 étapes simples par plan, tirées du livre (écrire, relier à un objectif,
  évaluer, rendre compte). Ce n'est pas un score de qualité : « 100 % » = étapes de départ franchies. Pour changer les étapes
  d'un plan, modifier ce seul fichier ; `tests/PlanDeVie/PlanProgressTest.php` vérifie les routes.
- Données ajoutées par `AbstractPlanController::renderStrategies` (`planProgress`).
- **Photo** : le fondateur ne veut pas de photo de lui sur la vitrine (mémoire `kiini-no-personal-photo`).
- **Reste (lot C)** : accueil « Ma prochaine étape » (réutiliser `PlanProgress` + parcours + bilan du jour), page « Mes
  rythmes », une action principale par écran ; lot B (composants uniques).

## 2026-11-17 · Lot A de l'audit design : zéro émoji, couleurs, casse

- **Zéro émoji dans l'interface** : 208 pictogrammes remplacés par des icônes Lucide (`<i data-lucide="…" class="ks-ico">`,
  style `.ks-ico` dans `app.css`) dans les textes, retirés dans les attributs, scripts, pages imprimables et libellés de
  carte. Messages PHP nettoyés. Restent permis : ✓ ✗ → ← (signes typographiques) et les sorties console.
- **Garde-fou** : `tests/Shared/DesignRulesTest.php` refuse tout émoji dans `templates/` et `src/` (hors `Command/` et emails)
  et l'ancien turquoise. Pour une icône : `<i data-lucide="nom" class="ks-ico" aria-hidden="true"></i>`.
- **Couleurs** : bandeau d'accueil en vert forêt avec halos sauge et or (plus de bleu ni d'indigo) ; teintes vives restantes
  (bleu, indigo, violet, rose, ambre) ramenées à la palette du livre dans les écrans de l'application. Les **rapports de
  tests** (DISC, ennéagramme…) et les pages « visualiser » gardent leurs couleurs propres, voulues.
- **Casse française** : « Balance interne », « Objectifs spécifiques », « Réseau de croissance »… (le livre garde les titres
  « X & Y » en majuscules : conservés). « Défis & Challenges Collectifs » → « Défis collectifs ».
- **Bug corrigé** : la page d'administration « Entonnoir » (déployée le 17/11) utilisait un filtre Twig `|min` inexistant et
  aurait donné une erreur 500 ; corrigé (`min()`), test de rendu ajouté. **À déployer.**
- **Reste de l'audit** : lot B (un seul modèle de bouton, d'en-tête, de carte ; styles en ligne), lot C (accueil « Ma
  prochaine étape », « Mes rythmes », une action principale par écran), visuels interactifs.

## 2026-11-17 · Audit design n° 2 : noms, vitrine, entonnoir

Audit : `docs/audit-design-2-strategique-2026-11-17.md` (vitrine, premier usage, interactivité, noms).
- **Noms** : « Mes plans » partout. `/mes-plans` (route `app_plans`, `PlansOverviewController`, gabarit `plans.html.twig`) ;
  `/mes-strategies` redirige en 301 (anciens courriels). Le menu choisit la rubrique par le **préfixe le plus long**
  (`/mes-plans/rituels` reste dans « Mon quotidien »). « Stratégie » est réservé au sens du livre.
- **Vitrine** (`dashboard/landing.html.twig`) : titre « Bâtissez la vie pour laquelle Dieu vous a créé », couverture du livre
  en tête et dans une section « Le livre », Équation du But (figure du livre), cercle des 6 plans, icônes Lucide à la place
  des émojis, lien vers l'introduction du livre. Promesse corrigée : le gratuit a « l'essentiel de chaque chapitre », PRO « le
  livre en entier » (aussi dans `abonnement/index`).
- **Couverture** : `assets/images/livre/couverture.png` = la couverture HD **recadrée au-dessus du nom de l'auteur**
  (règle : son nom n'est pas publié tant que les mentions légales ne sont pas prêtes). Quand elles le seront, on pourra
  mettre la couverture complète (`livre-et-ressources/livre/KIINI_couverture_HD.png`).
  Piège : `sips -c` recadre toujours au centre (ignore `--cropOffset`) ; le recadrage a été fait en Python (PNG).
- **Entonnoir** : `Media\Service\FunnelReport`, compteur `daily_visit` (visites de la vitrine par jour, robots écartés, aucune
  donnée personnelle), page `/admin/entonnoir` (visites, comptes, email vérifié, onboarding, parcours, PRO). Les visites ne
  sont comptées que depuis le déploiement. Test : `tests/Media/FunnelTest.php`.
- **Reste (audit n° 2)** : visuels interactifs (cercle des plans cliquable avec progression, carte cliquable, rythmes),
  accueil « Ma prochaine étape », puis lots A, B, C de l'audit n° 1 (zéro émoji dans l'application, composants uniques).
- Pas de capture d'écran de l'application sur la vitrine (pas de photo ni de témoignage non plus : ne rien inventer).

## 2026-11-17 · Identité visuelle du livre dans l'application

- Demande du fondateur : cohérence visuelle avec le livre (couleurs, schémas) ; **les polices restent** (Playfair, Inter).
- Palette du livre dans `base.html.twig` (`:root`) : vert `#2E6B53` / forêt `#1F4D3A`, or `#C8962E`, sauge, neutres
  légèrement verts ; anciennes valeurs turquoise remplacées partout (60 fichiers) ; `teal-*` de Tailwind redéfini.
- Couleurs des plans (`--plan-*`) selon la figure Être/Faire/Avoir/Donner ; appliquées à la vue d'ensemble (5 cartes
  sur une ligne), à la carte stratégique et sa légende, aux pages « visualiser » des plans, à la revue de la semaine.
  Badges PRO en or ; avertissements de l'analyse de cohérence en or.
- Encart « Comprendre cette étape » et citations du livre : vert pâle et filet d'or, icônes au lieu d'émojis.
- Schémas du livre placés (`methode/_figure.html.twig`) : 6 plans (vue d'ensemble), cascade (carte), saisons (Ma saison),
  cycle de l'intendant (Intendance), revues (Bilan), Équation du But, convictions, réseaux, héritage, hygiène, horizons
  (assistant, repliés), racines (rituels), trois témoins (feedback) ; ouvertures de parties en tête des chapitres.
- **Signalé à l'auteur** : la figure Être/Faire/Avoir/Donner dit « AVOIR : De quoi ai-je besoin ? » et BE/DO/HAVE/GIVE
  (non utilisée dans l'application) ; les ouvertures de parties posent les formules en questions (« Qui suis-je… »).
- Rappel : le prénom de l'auteur reste dans l'histoire du chapitre 10 (accord du 17/11) ; la signature reste retirée.

## 2026-11-17 · Phase 2 : le parcours de 90 jours (livre, annexe A)

- `/parcours` (`Methode\Controller\JourneyController`, contenu `Methode\Service\Journey::PLAN`) : les 13 semaines de
  l'annexe A, mot pour mot (chapitres, « ce que tu produis »), avec pour chaque semaine les chapitres à lire et des
  boutons vers les écrans où écrire (étapes de l'assistant, Ma saison, bilan du jour, Plan d'Intendance, rituels…).
- Avancement : `Methode\Entity\JourneyProgress` (date de départ, semaines terminées) ; semaine en cours calculée depuis
  la date de départ (`Journey::currentWeek`). Migration `journey_progress`.
- Entrées : carte en tête de la Méthode (comptes connectés), rappel de la semaine en cours sur l'accueil (`/dashboard`),
  invitation tant que le parcours n'est pas commencé.
- `tests/Methode/JourneyTest.php` vérifie que chaque semaine pointe vers des chapitres et des écrans qui existent :
  à garder vert si l'on renomme un chapitre ou une route. 214 tests verts.
- Les titres des semaines gardent le « tu » du livre (extraits du livre), le reste de la page vouvoie.

## 2026-11-17 · Phase 1 bis : les pratiques du livre dans l'application

- **Vérification visuelle** en local avec un compte de test PRO (`var/compte-de-test.local`, non versionné) : page
  d'Intendance, vue d'ensemble, Équation du But. Corrigé : retour sur l'élément après enregistrement (formulaires sans
  Turbo + défilement vers l'ancre), « Hygiène de vie » dans les titres.
- **Intendance dans la revue du mois** (compte rendu, chapitre 18) : état et action faite des priorités, date du dernier
  point (`MonthlyReviewController::saveStewardship`).
- **Intendance sur la carte stratégique** : lien facultatif d'un élément vers un objectif (`StewardshipItem::lifeGoal`) ;
  nœuds « ⚖️ » sur la carte ; alerte du chapitre 21 « Absent de la carte : … » pour tout plan qui ne soutient aucun objectif.
- **Ma saison** (`/bilan/saison`, `SeasonController`, `Bilan\Service\Seasons`, chapitre 11) : saison de 3 mois, thème,
  nature (semis, croissance, récolte, hiver : travail et piège du livre), revue de saison, historique. Entité `Season`
  existante, jusque-là inutilisée.
- **Bilan du jour** (`/bilan/du-jour`, `DailyReviewController`, chapitre 17) : intention tenue, énergie, humeur, stress,
  gratitude, tendances sur 14 jours. Stocké dans `DailyLog` (déjà utilisé par l'Agenda pour l'intention du jour).
- **Phrase d'éternité** et « déplacement de trésor » (chapitre 19) : étape 11 de l'assistant, affichés en tête du Plan de
  Transmission (`PlanDeTransmission::eternityPhrase`, `treasureShift`).
- **Le Sanctuaire** : chaque encart « Comprendre cette étape » propose l'exercice du chapitre (`BookLibrary::sanctuary`).
- **Ne manque jamais deux fois** (chapitre 22) : rappel sur la page des rituels pour les rituels quotidiens manqués la veille.
- **Axe « Ressources » du test Hygiène** : test inchangé (comparaison des passations) ; à l'étape 12, cet axe n'est plus
  proposé comme domaine d'hygiène mais renvoyé au domaine « Temps » du Plan d'Intendance.
- 3 migrations (lien objectif, saisons et bilan du jour, phrase d'éternité). 212 tests verts.
- **Pièges** : dans un gabarit enfant, ne pas nommer une variable `active` (écrasée par le menu de `base.html.twig`) ;
  `Habit` réécrit `createdAt` au `PrePersist` (dans un test, antidater en SQL puis `clear()`).
- **Choix retenus** : l'application garde le « vous », le livre le « tu » ; le mot reste « Agenda ».

## 2026-11-17 · Le livre : trois niveaux d'accès

Décision du fondateur : le livre est un produit en soi (vente, KDP), l'application fait pratiquer.
- Visiteur : 3 premiers chapitres en entier (`BookLibrary::PUBLIC_CHAPTERS`), extrait des autres.
- Compte gratuit : **l'essentiel** de chaque chapitre (`BookLibrary::essentials` : partie, sous-titre, verset, premier
  paragraphe, Le Sanctuaire, Ce que j'écris, À retenir ; ni les histoires ni « Pour les groupes »), extrait du texte de
  l'auteur sans réécriture.
- PRO (essai compris) : texte intégral. Interrupteur « Le livre en entier (La Méthode) » dans Configuration
  (`AppConfig::enableMethodFree`, désormais faux par défaut ; migration `Version20261117151426` le met à faux).
- Déployé le 17/11/2026 à 15 h 15 (UTC). **Piège du déploiement** : pendant les ~10 s entre l'envoi des fichiers et la
  reconstruction du cache, une nouvelle route (ici `app_plan_d_intendance`, appelée par le menu) donne des erreurs 500
  (6 dans le journal, toutes avant 15:15:46). Sans gravité ; à corriger un jour en vidant le cache avant d'ouvrir le menu.
- Pas de code imprimé dans le livre pour l'instant : KDP imprime le même code dans chaque exemplaire. Idée retenue
  pour la sortie du livre : un code de bienvenue (prolongation de l'essai, une fois par compte), sans risque s'il circule.

## 2026-11-17 · Phase 1 : l'application alignée sur le nouveau livre (6 plans, Plan d'Intendance)

Contexte : l'auteur a ajouté au livre le **Plan d'Intendance : ce que je gère** (manuscrit
`../livre-et-ressources/sources/livre/manuscrit.md`, partie VI, chapitre 18 ; fiche `../livre-et-ressources/CONTEXTE_POUR_IA.md`).
Décision et suite : `docs/DECISION-plan-d-intendance.md`. Le livre et l'application doivent sortir ensemble sans se contredire.

- **6 plans, noms et formules du livre** : `PlanCatalog::PLANS` (clé `INTENDANCE` entre `HYGIENE` et `TRANSMISSION` ;
  la clé `question` porte la formule du livre : « Qui je suis, où je vais », « Ce que je développe », « Ce que je gère »…).
  « Plan de Développement personnel », « Plan d'Hygiène de vie » partout ; plus de « quatre plans » / « 5 plans »
  visibles (navigation, accueil, abonnement, courriels, revue de la semaine). Lexique : `docs/lexique.md`.
- **Module `PlanDIntendance`** (doc `docs/modules/plan_d_intendance.md`) : page guidée (pas d'étape dans l'assistant,
  trop monolithique : `wizard.html.twig` fait 2 000 lignes), inventaire prérempli depuis le Plan de Vie et les tests,
  état, décision, action, 5 priorités au plus, compte rendu ; vue sur une page. PRO, interrupteur gratuit dans
  l'administration (`enableStewardshipFree`). Migration `Version20261117145446`. Carte dans la vue d'ensemble.
- **La Méthode lit le nouveau livre** : `app:livre:sync` découpe le manuscrit en 28 fichiers `content/livre/`
  (`BookLibrary::CHAPTERS`) ; retire dédicace, page auteur, signature et marqueurs « [HISTOIRE PERSONNELLE] »,
  « [À COMPLÉTER] », « [… À CONFIRMER] ». Anciennes adresses redirigées (`BookLibrary::LEGACY_SLUGS`, 301). Encarts
  « Comprendre cette étape » remappés (`STEPS`, dont `intendance` → chapitre 18).
- **Équation du But** dans l'étape 1 de l'assistant (« Je suis créé pour … afin d'apporter … chez … ») : trois champs
  qui écrivent le But ; le champ enregistré reste `purpose_statement`.
- Tests : `tests/PlanDIntendance/`, `MethodeControllerTest::testNewBookWithStewardshipChapter`,
  `WizardTestResultsTest::testPurposeEquationInStepOne` ; 204 tests verts.
- **Pièges** : `docs/livre/` est l'ancienne version (gardée, `README-OBSOLETE.md`) ; sur macOS, `grep -Z` ne sépare
  pas par NUL (utiliser `grep -l | xargs`).
- **Reste (phase 1 bis)** : bilan du jour, saisons (thème de 3 mois, revue de saison), phrase d'éternité dans la
  Transmission, nom « Le Sanctuaire » pour les exercices, « ne manque jamais deux fois », Organisateur ou Agenda (un
  seul mot à choisir) ; Intendance dans la revue du mois et la carte stratégique ; axe « Ressources » du test Hygiène.

## 2026-11-17 · Cohérence des plans, lot 1 : l'assistant reprend les résultats des tests

- `PlanDeVie\Service\TestResultsForPlans` : derniers résultats sous forme utile aux plans (DISC 16 profils, type,
  ennéagramme, Big Five dominant, dons + dons confirmés ou vus par les proches, 3 axes prioritaires du test Hygiène).
  Lit les **scores** (`Assessment::getScores`), pas les rapports illustrés (plus lents) ; noms via `Content::get`.
- Assistant (`WizardController` passe `testResults` et `discOptions`) :
  - étape 5 : préremplie depuis les tests quand rien n'est saisi, mention « Issu de votre test du … » ou « Votre dernier
    test indique … » (partiel `templates/plan_de_vie/wizard/_test_hint.html.twig`), liens « Voir mon profil KIINI SAWA »
    et « Passer les tests » ; « Style comportemental » → **Profil DISC** avec les 16 profils ;
  - étape 4 : puces « + don » (`_gift_suggestions`, JS `addSuggestedTalent`) ; étape 12 : puces des axes prioritaires
    avec la note (`_hygiene_suggestions`, JS `addSuggestedHygieneDomain`) ; étape 11 : rappel « Vos dons » (`_gifts_reminder`) ;
  - étape 2 avec `plan=CROISSANCE` : phrase sur la Balance interne.
- Plan de Transmission ouvert (`strategies.html.twig`) : rappel « Vos dons » (`giftResults`, ajouté par
  `AbstractPlanController::renderStrategies`). Les 4 phrases d'ouverture des plans réécrites selon la grille (doublon « (Être) » retiré).
- **Piège corrigé** : les tests écrivent aussi leurs scores bruts (JSON) dans `ProfileTest` (DISC, BIG5) ; l'assistant
  les prenait pour une saisie et rien n'était sélectionné. `PlanDataProvider::getWizardData` ne garde plus que des
  valeurs de la liste, la plus récente par date.
- Tests : `tests/PlanDeVie/WizardTestResultsTest.php`. Non vérifié à l'œil dans un navigateur (couvert par les tests fonctionnels).
- Reste : lots 2 et 3 de `docs/A-FAIRE-coherence-des-plans.md` (décisions du fondateur).

## 2026-11-17 · Audit de cohérence des 5 plans

- Audit détaillé : **`docs/A-FAIRE-coherence-des-plans.md`** (lot 1 fait ; lots 2 et 3 à décider).

## 2026-11-17 · Les 5 plans : questions, ordre et icônes de la méthode

- Référence fournie par le fondateur (diapositive « Les 5 plans de la méthode KIINI ») : une question par plan, ordre
  Vie → Croissance → Développement → **Hygiène → Transmission**, puis « le Système » (carte, habitudes, revues).
- `PlanCatalog::PLANS` porte désormais `question` et `desc` dans cet ordre (source unique) ; menu, vue d'ensemble
  (cartes réordonnées, question en italique, icônes sprout / wrench / heart-pulse / hand-heart), en-tête de chaque plan
  ouvert et du Plan de Vie. Hygiène : sous-titre « Le soutien » (et non plus « Le Système »).
- « Mon quotidien » présenté comme le Système (description dans le panneau et le menu du téléphone).
- Grille ajoutée à `docs/lexique.md` ; `NavigationTest` vérifie questions, contenus et ordre.

## 2026-11-17 · Correction : contenu du Plan de Croissance

- **Erreur commise** : le menu décrivait la Croissance avec « compétences » (ancienne version à 3 axes du livre).
  Décision du 15/11/2026 (`docs/plan-v1.md` §6.1) : **Compétence & Connaissance → Plan de Développement** ; la Croissance
  garde Caractère & Conviction, Relation & Réseau, valeurs d'alignement.
- **Source unique** des descriptions des plans : `PlanDeVie\Service\PlanCatalog::PLANS[...]['desc']`, utilisée par le menu
  (`NavigationExtension`) et la vue d'ensemble (`strategies.html.twig`). `NavigationTest` le vérifie.
- Corrigé aussi : zone de vocation « L'enseignant » → Plan de Développement ; Équilibre « Développement personnel » →
  Croissance (caractère) et Développement (apprentissages) ; fiche `docs/modules/plan_de_croissance.md`.
- **Attention** : le livre (`docs/livre/CHAP_09_LES_3_AXES.md`, `SUMMARY_PLANS.md`) décrit encore l'ancienne version ;
  ne pas s'en servir pour décrire les plans dans l'interface, et ne pas le réécrire sans l'auteur.

## 2026-11-17 · « Mes stratégies » retiré (décision du fondateur)

- Selon le livre (chapitre 10), une **stratégie** est un moyen d'atteindre un objectif, *dans* un plan : les 4 plans
  ne sont donc pas des « stratégies ». La rubrique « Mes plans » liste désormais directement le Plan de Vie et les
  4 plans (badge PRO pour les comptes gratuits), puis la **« Vue d'ensemble de mes plans »** (ancien hub `/mes-strategies`,
  adresse et route `app_strategies` inchangées).
- Libellés mis à jour (pages, emails, abonnement, bilan) ; `docs/lexique.md` et la charte modifiés ;
  `LexiconTest` interdit « Mes stratégies » et « plans stratégiques » dans le texte visible.
- Le mot « stratégie » reste employé dans son sens du livre (stratégies d'un plan, carte stratégique).

## 2026-11-17 · Navigation par rubriques

- Constat (audit de navigation) : fonctionnalités majeures cachées dans le menu du compte (Mes tests, Agenda,
  Partenaires, Couple…), menu « Plus » du téléphone en liste plate de 15 entrées, ordinateur et téléphone différents.
- Refonte : `Shared/Twig/NavigationExtension` = **une seule définition** ; six rubriques (Aujourd'hui, Me connaître,
  Mes plans, Mon quotidien, Ensemble, La Méthode) avec panneaux sur ordinateur et menu groupé sur téléphone ; menu du
  compte réduit au compte. Adresses des pages inchangées. Détail : `docs/design.md` (Navigation).
- **Piège Alpine** : un `@click.outside` sur chaque panneau (même masqué) referme le menu juste après l'avoir ouvert ;
  un seul `@click.outside` sur la barre.
- Vérifié : le message « Plan de Croissance envoyé… » vu dans le texte de « Mes stratégies » est une notification
  masquée hors écran (pas visible).
- Tests : `tests/Shared/NavigationTest.php`.

## 2026-11-17 · Historique et évolution des tests

- Les passations étaient déjà toutes conservées (`assessment`, `profile_test`), sans interface. Ajouté :
  - `Service/TestHistory` : séries `disc`, `bigfive` (profil complet + Big Five essentiel), `type`, `enneagramme`,
    `dons-spirituels`, `equilibre`, `hygiene` ; libellé du résultat principal par passation ; évolution (courbes
    `ReportCharts::lines`, lecture « ce qui a changé », tableau avant/aujourd'hui, frise des changements).
  - « Vos passations » sous chaque rapport (`templates/personnalite/_history.html.twig`), lien vers chaque ancien rapport.
  - `/mes-tests/evolution` (tests repassés) et `/mes-tests/evolution/{série}` (`SynthesisController::evolution`).
  - Auto-évaluations : consultation d'une passation précise `/mes-tests/{type}/resultats/{id}` et PDF `?id=`.
  - Liens depuis le catalogue (« Mon évolution et l'historique de mes tests ») et la synthèse (« Votre évolution »).
- **À savoir** : les scores d'une passation sont figés, mais les textes suivent le contenu actuel (`Content::get`) ;
  les réponses IPIP se cumulent entre questionnaires (voulu).
- Ce journal (`docs/SUIVI-IA.md`) a été créé à ce moment, à la demande du fondateur : **le tenir à jour à chaque chantier**.

## 2026-11-17 · Confirmation par les proches (DISC, dons)

- `ConfirmationService`, `ConfirmationController`, tables `confirmation_request` / `confirmation_answer`.
- Lien public `/confirmer/{jeton}` (déclaré public dans `security.yaml` et `SecurityGuardsTest`), sans compte, anonyme,
  champ piège, une réponse par session, 50 au plus. Résultat visible **à partir de 3 réponses** (anonymat).
- DISC : 4 descriptions sans lettre (`DiscProfiles::OBSERVED`), ordre propre à chaque lien. Dons : jusqu'à 5 cochés.
- Section `_confirmation.html.twig` dans les rapports DISC et dons (et le PDF si ≥ 3 réponses).

## 2026-11-17 · Nuances, contextes communautaires

- Intensité : `ReportBuilder::discIntensity`, `giftStrength`, netteté de l'ennéagramme, traits Big Five extrêmes.
- DISC naturel ≠ adapté : `DiscProfiles::ADAPTATION` (12 textes).
- « Dans la famille élargie et la communauté » : constantes `COMMUNITY` (DISC, ennéagramme, types).
- **Piège rencontré** : un script Python a vidé un fichier (`open(p,'w').write(open(p).read())`) : ne jamais lire et
  écrire le même fichier dans la même expression. Restauré par `git checkout`.

## 2026-11-17 · Synthèse « Mon profil KIINI SAWA », actions reliées aux plans

- `/mes-tests/synthese` : `ProfileSynthesis` croise les derniers résultats dès 2 tests (convergence sur 3 axes, zone de
  vocation DISC × famille de dons, ressources, fragilités, 3 priorités). Gratuit : carte d'identité + convergences ;
  PRO : le reste et le PDF.
- Boutons « Ajouter à mes rituels / à mon Plan de Croissance » (`TestActionService`, table `test_action`) ; rappel
  unique à 30 jours (`ReminderService::testFollowUps`, type `GOAL_DEADLINE`).

## 2026-11-17 · Refonte visuelle des rapports

- PDF : polices Inter et Playfair Display statiques (`resources/fonts/pdf`, OFL, téléchargées depuis Google Fonts avec
  l'accord du fondateur) enregistrées par `PdfFactory` (chroot sur le dossier des polices).
- Icônes SVG maison et logo : `Twig/ReportVisualsExtension` (`report_icon()`, `brand_logo()`).
- « L'essentiel en une page » + sommaire (`_essentials.html.twig`) après chaque page de garde.
- **Pièges Dompdf** : SVG simple uniquement (pas de `text-anchor`, pas de marqueurs ; largeur/hauteur du SVG égales au
  `viewBox`, sinon le dessin est réduit) ; pas de flex/grid (tableaux) ; police sans glyphe = caractère vide (d'où les
  icônes SVG au lieu de ✓ ▲ ☐).

## 2026-11-17 · Textes des tests modifiables (admin)

- Admin › Textes des tests (`/admin/tests/textes`) ; table `content_override` ; **toujours lire les textes via
  `Psychometrie\Content::get('source')`**, jamais directement les constantes (sinon les modifications ne s'affichent
  pas). Structure, calcul, couleurs et questions IPIP verrouillés.

## 2026-11-16 → 17 · Rapports illustrés (7 tests) et DISC complet

- DISC à choix forcé (`DiscBank`, 24 blocs), profil naturel (« le moins » inversé) et adapté (« le plus »), 16 profils
  (`DiscCombos`), contenus tirés de la formation DISC du fondateur (avec son accord, son nom n'apparaît pas dans l'UI).
- Rapports illustrés : cadre commun `templates/personnalite/assessment/report/` (`_cover`, `_styles`, `_warnings`,
  `pdf`), un gabarit par test (`ReportBuilder::TEMPLATES`), schémas `DiscCharts` / `ReportCharts`.
- Page « Bon usage de ce rapport » (protection juridique) et accord obligatoire avant les résultats du DISC.
- Rendu PDF de contrôle en local : scripts dans le dossier temporaire de session (`render_*.php` + `pdf2png.swift`) ;
  à recréer si besoin (amorcer le noyau en `APP_ENV=test`, rendre `report/pdf.html.twig`, convertir avec PDFKit).

## Avant le 16/11/2026

Voir `git log` : mise en ligne Hostinger, emails Brevo, audit en 6 lots, PWA, provenance, parrainage, défis,
feedback nourricier, notifications push, tableau de rétention.

## Ouvert / à faire (liste du 17/11/2026, voir aussi le « Reste à faire » des entrées récentes)

- **Prochain chantier : décisions du fondateur sur les lots 2 et 3 de `docs/A-FAIRE-coherence-des-plans.md`.**

- Carte DISC d'équipe ou de couple pour les organisations (offre Églises).
- Suivi de la fidélité de tous les tests (Admin › Fidélité des tests couvre l'IPIP) ; normes dès 200 profils.
- Relectures humaines : théologien (figures bibliques), psychologue (textes), juriste (mentions) — côté fondateur.
- Structure juridique (15 « [À COMPLÉTER] »), coordonnées de paiement, site vitrine — côté fondateur.
