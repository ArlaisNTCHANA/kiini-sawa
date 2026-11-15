# Architecture Technique & Modulaire

Le projet **KIINI SAWA** repose sur une architecture modulaire en PHP/Symfony 8.1. Elle s'articule autour d'une séparation nette en **5 Plans Métiers** reliés à chaque utilisateur (`User`), soutenus par des **Modules Transversaux Autonomes** (Facturation, Système d'Habitudes, Profil, Commentaires, Saisons, Tests de Personnalité & Profils, Réseau, Redevabilité & Mentorat).

---

## Vue d'Ensemble de l'Architecture

L'utilisateur (`User`) est le pivot central de l'application. Ses données d'accès et d'identité sont isolées de ses informations personnelles, métiers et transversales :

```
                        ┌──────────────────┐
                        │   User (Auth)    │
                        └────────┬─────────┘
        ┌────────────────────────┼────────────────────────┐
        ▼                        ▼                        ▼
┌───────────────┐        ┌───────────────┐        ┌───────────────┐
│ UserProfile   │        │ 5 Plans       │        │ Transversal   │
│ (Démographie) │        │ (Métier)      │        │ (Soutien)     │
└───────────────┘        └───────┬───────┘        └───────┬───────┘
                                 │                        │
         ┌────────┬────────┬─────┼──────┬────────┐        ├─► Habit & Log
         ▼        ▼        ▼            ▼        ▼        ├─► Comment
       ┌───┐    ┌───┐    ┌───┐        ┌───┐    ┌───┐      ├─► Season
       │Vie│    │Cro│    │PDP│        │Hyg│    │Tra│      ├─► Subscription
       └───┘    └───┘    └───┘        └───┘    └───┘      ├─► Tests & Profils
                                                          └─► Réseau & Mentors
```

---

## 1. Les 5 Plans Métiers

Chaque utilisateur possède une relation `1-to-1` avec 5 plans fondamentaux :

1. **Plan de Vie (`PlanDeVie`)** : Définition de la raison d'être, de la vision long-terme et des objectifs de vie majeurs.
2. **Plan de Croissance (`PlanDeCroissance`)** : Développement du caractère (forces/faiblesses), relations interpersonnelles et redevabilité avec un mentor.
3. **Plan de Développement Personnel (`PlanDeDeveloppementPersonnel`)** : Acquisition de compétences professionnelles, lectures et plans de carrière.
4. **Plan d'Hygiène (`PlanDHygiene`)** : Suivi de l'équilibre physique, mental et spirituel, ainsi que la gestion de l'énergie quotidienne.
5. **Plan de Transmission (`PlanDeTransmission`)** : Mentorat, dons de talents et legs pour les générations futures.

---

## 2. Détail du Plan de Vie (Vague 1)

Le **Plan de Vie** est le point de départ de l'alignement personnel. Il regroupe :
* **Raison d'être (`Purpose`)** : Énoncé de mission personnel, hypothèses d'alignement et preuves associées.
* **Vision (`Vision`)** : Aspirations futures à divers horizons temporels (ex: 5 ans, 10 ans), avec indicateurs de succès et bénéficiaires.
* **Valeurs (`Value`)** : Valeurs cardinales traduites en comportements concrets.
* **Passions (`Passion`)** : Activités et thèmes d'intérêt qui animent l'utilisateur.
* **Objectifs de Vie (`LifeGoal`)** : Objectifs stratégiques classés par catégories (`BE`, `DO`, `HAVE`, `GIVE`) avec gestion de priorité et progression (%).
* **Jalons d'Objectif (`LifeMilestone`)** : Sous-étapes de complétion temporelles liées aux objectifs.

---

## 2.1 Philosophie d'Alignement : Mobilisation des Ressources & Moyens

Dans le **Plan de Vie**, les modules transversaux comme les Compétences, les Connaissances, le Caractère, les Convictions, les Relations et les Réseaux ne sont pas de simples listes isolées. Ils sont conçus comme des **Ressources et Moyens à mobiliser** pour servir des objectifs de vie spécifiques (`LifeGoal`).

### Pourquoi cette liaison ?
L'utilisateur est amené à s'interroger de manière pragmatique sur chaque objectif : **"Pour atteindre cet objectif spécifique, de quoi ai-je besoin ?"**
Plutôt que de lister ses compétences ou relations de manière théorique, il les rattache directement à des objectifs de vie concrets pour créer un véritable alignement stratégique.

### Les Postures de Mobilisation (Les 4 Statuts)
Pour chaque ressource déclarée, l'utilisateur qualifie sa situation actuelle par rapport à l'objectif via 4 statuts :
* **À mettre à profit** : La ressource est déjà acquise ou maîtrisée. L'utilisateur sait comment l'exploiter activement pour soutenir son objectif de vie.
* **À consolider** : L'utilisateur dispose déjà de cette ressource, mais elle a besoin d'être consolidée ou renforcée pour être pleinement efficace dans le cadre de cet objectif.
* **À construire** : L'utilisateur ne dispose pas encore de cette ressource. Il identifie qu'il doit la développer (ex: apprendre une nouvelle compétence) pour rendre l'objectif possible.
* **Absence assumée** : L'utilisateur reconnaît le besoin théorique de cette ressource pour son objectif, mais choisit délibérément de ne pas y consacrer d'efforts à ce stade (priorisation temporelle assumée).

### Catégorisation Stratégique du Réseau (Les 5 Réseaux)
Pour maximiser l'atteinte des objectifs de vie, les relations de l'utilisateur ne sont pas de simples contacts. Elles sont structurées et catégorisées selon leur rôle de soutien et d'influence dans son parcours de croissance :
* **Réseau d'Orientation** (`orientation`) : Les mentors et guides chevronnés capables de donner des conseils stratégiques et d'éclairer les choix de vie importants.
* **Réseau de croissance** (`croissance`) : Les pairs et collaborateurs actifs avec qui l'utilisateur partage des apprentissages et progresse mutuellement au quotidien.
* **Réseau de soutien** (`soutien`) : Le cercle intime de confiance (famille, amis proches) apportant le soutien émotionnel et le secours nécessaire en cas de difficulté.
* **Réseau de responsabilité** (`responsabilite`) : Les partenaires de redevabilité (ou coachs) devant qui l'utilisateur s'engage pour maintenir sa discipline et tenir ses résolutions.
* **Réseau d'environnement** (`environnement`) : Les personnes constituant le cadre de vie ou professionnel régulier de l'utilisateur, représentant le milieu qu'il souhaite influencer positivement.

---

## 2.2 Visualisation & Pilotage Stratégique (Échéancier, Carte & Analyse)

Pour piloter efficacement son Plan de Vie, l'utilisateur dispose de trois onglets de synthèse stratégiques et interactifs :

### A. L'Échéancier (Timeline Dynamique)
* **Description** : Affiche de manière chronologique tous les objectifs spécifiques (`LifeGoal`) ayant une date d'échéance, regroupés par année.
* **Fonctionnalités & Ergonomie** :
  * Un filtre dynamique par catégorie d'objectif (`BE`, `DO`, `HAVE`, `GIVE`) et par année, propulsé par Alpine.js côté client pour des transitions rapides et fluides.
  * Masquage intelligent des en-têtes d'années : si aucun objectif d'une année ne correspond aux filtres actifs, l'en-tête de cette année disparaît automatiquement pour un affichage propre.
  * Structure des cartes en deux colonnes : à gauche le titre, la catégorie, la priorité et la barre de progression ; à droite la liste des jalons temporels associés (`LifeMilestone`).

### B. La Carte Stratégique (Rendu Mermaid.js)
* **Description** : Visualisation graphique vectorielle (SVG) générée dynamiquement à partir d'un graphe Mermaid.js.
* **Cascade d'Alignement** : Représente visuellement la structure en cascade allant de la **Vision** globale de l'utilisateur vers ses **Objectifs de Vie**, puis vers les ressources et routines de soutien (Compétences, Habitudes de Caractère, Réseaux, Projets de Transmission et Domaines d'Hygiène de vie).
* **Rendu asynchrone** : Le code Mermaid est stocké dans un conteneur masqué et le rendu n'est déclenché qu'au clic sur l'onglet "Carte", évitant les anomalies de calcul de largeur et hauteur des éléments initialement masqués.

### C. L'Analyse de Cohérence (Audit Automatique)
* **Description** : Un moteur d'audit qui évalue la structure du plan de vie et calcule un **Score d'Alignement (sur 100)**.
* **Règles d'évaluation** :
  * Absence de date d'échéance sur les objectifs spécifiques.
  * Formulation d'objectifs trop brève ou imprécise.
  * Vision non rédigée ou trop courte.
  * Objectifs spécifiques orphelins (non soutenus par des plans de croissance, d'hygiène ou d'héritage).
  * Moyens d'action orphelins (ne soutenant aucun objectif de vie).
  * Croyances limitantes déclarées sans objectif de caractère pour les contrecarrer.
* **Recommandations interactives** : Chaque anomalie détectée est accompagnée d'un bouton "Corriger" redirigeant directement l'utilisateur vers l'étape adéquate de l'assistant de configuration.

---

## 3. Détail du Plan de Croissance (Vague 2)

Le **Plan de Croissance** structure le développement personnel intérieur et relationnel :
* **Objectifs de Croissance (`GrowthGoal`)** : Ciblent le caractère (axe `CHARACTER`) ou les relations (axe `RELATION`).
  * Pour l'axe **Caractère (`CHARACTER`)**, ils sont alimentés dynamiquement par le système à partir des Croyances Limitantes, Contre-valeurs observées, Valeurs déclarées & admirées, et Convictions de l'utilisateur saisies dans son Plan de Vie, et configurables via des stratégies, actions, habitudes et objectifs de vie soutenus.
  * Pour l'axe **Relation (`RELATION`)**, ils permettent de catégoriser les connexions de l'utilisateur sous forme de 5 réseaux distincts :
    * **Réseau d'Orientation** (`orientation`) : Mentors, conseillers et personnes de référence guidant la trajectoire de vie.
    * **Réseau de croissance** (`croissance`) : Pairs et collaborateurs favorisant le développement et l'apprentissage mutuels.
    * **Réseau de soutien** (`soutien`) : Proches et amis intimes constituant le cercle émotionnel et de secours.
    * **Réseau de responsabilité** (`responsabilite`) : Partenaires de redevabilité devant qui l'utilisateur s'engage.
    * **Réseau d'environnement** (`environnement`) : Contacts réguliers du milieu direct à influencer positivement.
  * *Liaison transverse* : Les objectifs de croissance peuvent être liés à un ou plusieurs objectifs de vie (`LifeGoal`) via une relation ManyToMany (table de jointure `growth_goal_life_goal`).
* **Actions de Croissance (`GrowthAction`)** : Actions concrètes à mener pour atteindre un objectif de croissance (suivies via des états : `NOT_STARTED`, `IN_PROGRESS`, `COMPLETED`, pouvant être définies avec ou sans date d'échéance).
* **Suivi des Scores (`GrowthGoalScore`)** : Permet à l'utilisateur de s'auto-évaluer périodiquement (sur une échelle de 0 à 100) pour mesurer l'évolution de ses progrès.
* **Redevabilité & Validation (`ConfirmPoint` & `Evidence`)** : 
  * Les **Points de Redevabilité (`ConfirmPoint`)** sont des énoncés comportementaux que l'utilisateur soumet à la validation de son mentor/partenaire de redevabilité.
  * Les **Preuves (`Evidence`)** permettent d'attacher des éléments tangibles (texte, liens, images) pour appuyer la validation de chaque point.

---

## 4. Détail du Plan de Développement Personnel (Vague 3)

Le **Plan de Développement Personnel (PDP)** gère l'acquisition de savoir-faire et la trajectoire de carrière de l'utilisateur :
* **Objectifs de Compétences (`SkillGoal`)** : Ciblent l'apprentissage d'aptitudes professionnelles ou personnelles classées par catégories (`TECHNICAL`, `INTERPERSONAL`, `COGNITIVE`, `EMOTIONAL`, `FINANCIAL`, `SOCIAL`, `PHYSICAL`) avec des niveaux cibles (ex : Intermédiaire, Expert).
  * *Liaison transverse* : Les objectifs de compétences peuvent être associés à des objectifs de vie (`LifeGoal`) via une relation ManyToMany (table de jointure `skill_goal_life_goal`).
* **Ressources d'Apprentissage (`LearningResource`)** : Enregistre les supports d'apprentissage (livres, formations, articles, vidéos) qui alimentent la maîtrise d'une compétence, avec statut de complétion, pourcentage d'avancement, évaluation (1 à 5 étoiles) et prise de notes personnelle.
* **Étapes de Carrière (`CareerStep`)** : Jalons de carrière et objectifs professionnels (ex : certifications, promotions, projets de reconversion) avec descriptif, entreprise cible et suivi des échéances.

---

## 5. Détail du Plan d'Hygiène (Vague 4)

Le **Plan d'Hygiène (`PlanDHygiene`)** assure le suivi quotidien de la vitalité physique, émotionnelle et spirituelle de l'utilisateur :
* **Domaines d'Hygiène (`HygieneDomain`)** : Représentent les différentes catégories d'équilibre (ex : Physique, Mental & Spirituel, Nutrition, Sommeil) avec des objectifs de score pour mesurer la complétion des routines associées.
  * *Liaison transverse* : Une habitude (`Habit`) peut être rattachée à un domaine d'hygiène (`HygieneDomain`) pour alimenter automatiquement les scores du domaine concerné.
* **Bilans Journaliers (`DailyLog`)** : Permettent de faire un point quotidien sur l'état général de l'utilisateur. Ils enregistrent le nombre d'heures et la qualité du sommeil, le niveau d'énergie physique, le niveau de stress, l'humeur dominante, l'intention de la journée et des remarques libres. Une contrainte unique garantit un seul bilan par jour et par utilisateur.

---

## 6. Détail du Plan de Transmission (Vague 5)

Le **Plan de Transmission (`PlanDeTransmission`)** gère la dimension externe et sociale de la redevabilité de l'utilisateur, à savoir comment il partage son expertise et ses acquis avec les autres (legs et mentoring) :
* **Objectifs de Legs (`LegacyGoal`)** : Ciblent la transmission formelle (ex : Accompagner des entrepreneurs, financer un projet communautaire, écrire des mémoires) avec des indicateurs de progression, des bénéficiaires cibles et des jalons de réussite.
  * *Liaison transverse* : Une habitude (`Habit`) peut être rattachée à un objectif de legs (`LegacyGoal`) pour automatiser et régulariser l'effort de transmission (ex : routine hebdomadaire de mentorat).
* **Actions de Transmission (`TransmissionAction`)** : Actions concrètes pour matérialiser un objectif de legs (états `NOT_STARTED`, `IN_PROGRESS`, `COMPLETED`).
* **Dons de Talents (`TalentDon`)** : Enregistrements plus informels de partage de compétences et de dons, permettant de conserver l'historique et la trace de l'impact social de l'utilisateur.

---

## 7. Profils de Personnalité & Tests (Vague 6)

Le module **Profils de Personnalité & Tests** gère les évaluations psychologiques et comportementales nécessaires à l'alignement personnel et au choix de carrière :
* **Questionnaire de Personnalité (`PersonalityQuestion` & `PersonalityAnswer`)** : Contient le référentiel des questionnaires pour les différents tests (DISC, MBTI, Ennéagramme, Big 5, Intelligences Multiples, Hygiène de vie). Chaque question est ordonnée via `sortOrder` et associée à plusieurs choix de réponses ayant une valeur de score spécifique (ex: type ou couleur).
* **Historique des tests (`ProfileTest`)** : Enregistre chaque session de test passée par un utilisateur (`User`). Les scores et structures complexes sont conservés de manière flexible dans un champ `result` sérialisé au format JSON.
* **Fiches profils de référence (`PersonalityProfile`)** : Référentiel des profils psychologiques ou des traits types définis par les modèles théoriques (ex: profils DISC rouge/bleu, types MBTI INFP, ennéatypes, ou intelligences de Gardner). Le couple `[testType, code]` y est unique.

---

## 8. Réseau, Redevabilité & Mentorat (Vague 7)

Le module **Réseau, Redevabilité & Mentorat** régit le tissu social et de soutien de l'utilisateur :
* **Connexions de Redevabilité (`Connection`)** : Gère les invitations et relations validées d'amitié, de mentorat ou de coaching. Les utilisateurs contrôlent la portée de partage (`ShareScope`) de leurs différents plans métiers.
* **Demandes de Conseils (`AdviceRequest` & `AdviceResponse`)** : Permet à l'utilisateur de soumettre un point de redevabilité (`ConfirmPoint`) à un mentor pour solliciter son avis sur les observations, les risques et obtenir des conseils.
* **Messagerie de Coaching (`CoachingMessage`)** : Canal de communication directe et asynchrone entre un coach/mentor et son élève.
* **Suggestions d'Objectifs (`GoalSuggestion`)** : Permet aux mentors ou partenaires de redevabilité de proposer des objectifs pertinents à l'utilisateur, facilitant l'élaboration de ses plans de croissance et de vie.

---

## 9. Système de Journaling & Réflexion (Vague 8)

Le module **Journaling & Réflexion** fournit un espace intime d'écriture et d'auto-analyse quotidien pour l'utilisateur :
* **Dossiers & Collections thématiques (`JournalCollection`)** : Permettent de classer les écrits par répertoires thématiques dotés d'icônes et de couleurs personnalisées pour une navigation fluide.
* **Notes de Réflexion (`ReflectionNote`)** : Consignent le contenu rédigé par l'utilisateur à une date donnée, accompagné d'informations contextuelles comme l'humeur (`mood`), le niveau d'énergie (`energy`), et la localisation (`location`).
  * *Typologie des notes* : Les notes sont caractérisées par un type spécifique via l'énumération `ReflectionType` (`REFLECTION`, `IDEA`, `MILESTONE`, `INSIGHT`, `RESOLUTION`, `TEMOIGNAGE`).
  * *Liaisons transverses & Intégrité* : Une note peut être associée à une collection (`JournalCollection`), à un objectif de vie (`LifeGoal`) ou à un objectif de croissance (`GrowthGoal`). Si l'un de ces éléments est supprimé, la relation est simplement mise à `NULL` (`ON DELETE SET NULL`) afin de conserver l'historique écrit et les pensées de l'utilisateur.

---

## 10. Planification & Agenda (Vague 9)

Le module **Planification & Agenda** fournit les outils de planification temporelle à court terme et d'organisation par listes de tâches :
* **Tâches d'Agenda (`PlannerTask`)** : Représentent les événements ou tâches planifiés dans le calendrier de l'utilisateur avec dates, heures précises (`startTime`, `endTime`) ou indicateur de journée entière (`isAllDay`), état de complétion et champ pour identifiant de synchronisation Google Calendar (`googleEventId`).
  * *Liaison transverse* : Une tâche d'agenda peut être rattachée à un objectif de croissance (`GrowthGoal`) pour planifier les efforts correspondants. En cas de suppression de l'objectif de croissance, la liaison est simplement mise à `NULL` (`ON DELETE SET NULL`) pour conserver la trace de l'activité.
* **Listes de Tâches (`UserList` & `ListItem`)** : Permettent de créer des listes thématiques personnalisées (avec icônes et couleurs personnalisées) contenant des éléments individuels (`ListItem`).
  * *Liaison transverse* : Les éléments de liste de tâches peuvent être rattachés à un objectif de vie (`LifeGoal`). La suppression de l'objectif de vie remet la liaison à `NULL` pour préserver l'historique de la liste.

---

## 11. Rétrospectives & Bilans (Vague 10)

Le module **Rétrospectives & Bilans** offre à l'utilisateur des outils de revue périodique et de sauvegarde de ses progrès :
* **Bilans Réguliers (`CheckIn`)** : Check-ins hebdomadaires pour faire le point sur les réalisations (`done`), les retards (`notDone`), les obstacles (`blocker`), les ajustements (`adjustment`) et les actions prioritaires sous 48h (`action48h`).
* **Revues Mensuelles (`MonthlyReview`)** : Revues approfondies de fin de mois pour analyser les progrès (`progress`), les stagnations (`stagnation`), les blocages (`blocker`), formuler des ajustements (`adjustment`) et acter des décisions stratégiques (`decision`).
* **Historique des Plans (`PlanHistory`)** : Clichés (snapshots) contenant les structures JSON de ses différents plans à un instant T pour suivre ses évolutions historiques.

---

## 12. Défis Collectifs & Challenges (Vague 11)

Le module **Défis Collectifs & Challenges** permet aux membres de s'engager ensemble dans des routines collectives :
* **Défis collaboratifs (`Challenge`)** : Définis par un titre, une description, un type (`HABIT`), une habitude ciblée (`habitTitle`), une fréquence (`DAILY`, `WEEKLY`), une visibilité (`PUBLIC`, `PARTNERS`) et des règles temporelles de récurrence. Un défi est initié par un créateur (`creator`).
* **Participations aux défis (`ChallengeParticipant`)** : Lie un utilisateur à un défi (`Challenge`). L'utilisateur peut y suivre sa progression (de 0 à 100%) et lier cette participation à son plan de vie (`LifeGoal`) ou à son plan de croissance (`GrowthGoal`). Une contrainte unique garantit qu'un utilisateur ne participe qu'une seule fois à un défi donné.
  * *Intégrité des données* : En cas de suppression de l'objectif de vie ou de croissance associé, la relation est mise à `NULL` (`ON DELETE SET NULL`) pour ne pas expulser le participant du défi collectif et préserver l'historique du challenge.
* **Logs d'avancement quotidien (`ChallengeLog`)** : Permet à chaque participant de valider quotidiennement sa complétion de l'habitude ciblée par le défi.
* **Fil d'actualité du défi (`ChallengeActivity`)** : Liste chronologique des activités générées par les participants (ex: adhésion à un défi, complétion d'un défi, messages ou posts).
* **Invitations aux défis (`ChallengeInvitation`)** : Gère l'envoi d'invitations entre utilisateurs (expéditeur `sender`, destinataire `receiver`) pour rejoindre un défi, avec des statuts d'acceptation (`PENDING`, `ACCEPTED`, `REJECTED`).

---

## 13. Notifications & Préférences (Vague 12)

Le module **Notifications & Préférences** fournit le système d'alerte et de personnalisation de la communication :
* **Notifications in-app (`Notification`)** : Consigne les alertes individuelles générées par les événements de l'application (ex : demandes de connexion, invitations aux défis, retours de feedbacks). Elles contiennent un titre, un message, un état de lecture (`read`), une URL de redirection optionnelle (`actionUrl`) et des métadonnées sous format JSON (`metadata`).
  * *Intégrité des données* : La suppression d'un utilisateur supprime l'intégralité de son historique de notifications in-app en cascade.
* **Préférences de notifications (`NotificationPreference`)** : Permet de moduler les canaux (e-mail, notifications instantanées ou digests périodiques) et les types d'événements pour lesquels l'utilisateur souhaite être notifié. Il configure également l'heure d'envoi préférée et la récurrence des bilans hebdomadaires ou mensuels.
  * *Intégrité des données* : Une préférence unique est associée à chaque utilisateur en relation `1-to-1` (cascade remove à la suppression de l'utilisateur).

---

## 14. Feedbacks & Demandes (Vague 13)

Le module **Feedbacks & Demandes** structure l'évaluation à 360 degrés et les retours d'expérience au sein de l'application :
* **Feedbacks directs (`Feedback`)** : Retours d'expérience formels ou informels partagés directement d'un utilisateur (`fromUser`) vers un autre (`toUser`), avec caractérisation de l'impact (Énumérateur `Impact` : `POSITIVE` ou `CONSTRUCTIVE`).
* **Demandes de feedback (`FeedbackRequest`)** : Initiées par un utilisateur qui génère un lien de partage doté d'un token d'identification unique (`token`). L'utilisateur peut y forcer l'identité réelle (`forceRealName`) ou laisser les retours anonymes.
* **Réponses de l'entourage (`CommunityFeedback`)** : Réponses individuelles reçues d'un proche/collaborateur sur une demande de feedback. Elles contiennent le lien de parenté/relation (`relationship`), le statut anonyme ou non, le nom déclaré du répondant, et les réponses structurées (forces, axes d'amélioration, qualités perçues, et réponses détaillées stockées au format JSON dans `answers`).
* **Formulaires & Questions (`FeedbackQuestion`)** : Référentiel des questions des formulaires d'évaluation configurables en back-end (libellés multilingues, type de champ, options de sélection, et ordonnancement `sortOrder`).
* **Rapports d'application (`AppFeedback`)** : Retours d'expérience transversaux envoyés par les utilisateurs à l'équipe KIINI SAWA (bug, suggestions d'amélioration) avec états de suivi (`PENDING`, `REVIEWED`, `RESOLVED`).

---

## 15. Sessions & Télémétrie (Vague 14)

Le module **Sessions & Télémétrie** assure la traçabilité des connexions et l'analyse de l'utilisation de l'application :
* **Sessions Utilisateurs (`UserSession`)** : Enregistre les métadonnées de chaque session de connexion d'un utilisateur (dates de début et de dernière activité, adresse IP, localisation géographique via le pays/ville, ainsi que le type d'appareil, de système d'exploitation et de navigateur). La durée de la session est calculée et stockée en minutes.
* **Événements de Télémétrie (`TelemetryEvent`)** : Consigne les actions analytiques des utilisateurs au sein de l'application (ex : clics sur un objectif, complétion d'habitude, changement d'écran), facilitant l'évaluation de l'engagement et l'optimisation de l'ergonomie. Les métadonnées contextuelles de l'événement sont stockées de manière flexible au format JSON textuel dans le champ `eventData`.
* *Intégrité des données* : La suppression d'un utilisateur supprime l'intégralité de ses sessions et de ses événements de télémétrie associés via une suppression en cascade (`ON DELETE CASCADE`).

---

## 16. Modules Transversaux de Soutien

Ces modules coupent à travers tous les plans métiers pour assurer la cohérence globale du système :

### A. Système d'Habitudes (`Habit` & `HabitLog`)
Le **Système d'Habitudes** est entièrement indépendant et découplé des plans métiers pour permettre une flexibilité maximale :
* **Liaison multiple** : Une habitude (`Habit`) peut être rattachée optionnellement à un **Objectif de Vie** (`LifeGoal`), un **Objectif de Croissance** (`GrowthGoal`), un **Objectif de Compétences** (`SkillGoal`), un **Domaine d'Hygiène** (`HygieneDomain`), ou un **Objectif de Legs** (`LegacyGoal`).
* **Configuration d'ancrage** : Gère la fréquence (`DAILY`, `WEEKLY`, `MONTHLY`), la planification (jours/semaines de récurrence), les intentions d'implémentation (Quand/Alors) et les leviers de soutien/ancrage cognitifs.
* **Suivi d'exécution (`HabitLog`)** : Historique journalier ou périodique de complétion (Vrai/Faux) pour calculer les statistiques de régularité.

### B. Profil Utilisateur (`UserProfile`)
Isole les données civiles, démographiques, sociales (LinkedIn, WhatsApp, etc.) et de localisation (fuseau horaire, langue) pour éviter de surcharger l'entité `User` dédiée uniquement à la sécurité.

### C. Commentaires Partagés (`Comment`)
Permet aux mentors et partenaires de redevabilité d'écrire des commentaires sur n'importe quel élément de l'application (Purpose, Vision, LifeGoal, Habit, GrowthGoal, SkillGoal, HygieneDomain, LegacyGoal). Le ciblage est rendu flexible par des clés étrangères optionnelles.

### D. Saisons de Vie (`Season`)
Découpe l'année de l'utilisateur en périodes temporelles thématiques (ex : "Saison de la mise au point") pour donner une impulsion temporelle aux objectifs en cours.

### E. Système d'Abonnement (`Subscription` & `Transaction`)
Régule l'accès aux fonctionnalités (plans `FREE`, `PRO`, `TEAM`) et conserve l'historique des transactions Stripe.

---

## 17. Config & Contenus (Vague 15)

Ce module regroupe les entités de configuration et de contenus globaux, indépendants des utilisateurs individuels :
* **Configuration Globale (`AppConfig`)** : Permet de stocker les paramètres fonctionnels et commerciaux de l'application (tarifs des abonnements, durée de la période d'essai gratuit, limites d'utilisation gratuites par module métier, ainsi que les clés API Resend pour l'envoi de courriels). Il existe une seule ligne par défaut (`id = 'default'`).
* **Modèles de courriels (`EmailTemplate`)** : Contient les modèles d'emails dynamiques (sujets et corps HTML ou texte brut) que l'application envoie automatiquement aux utilisateurs (ex: bienvenue, réinitialisation de mot de passe).
* **Citations Inspirantes (`Quote`)** : Catalogue de citations thématiques (ex: motivation) avec dates prévues d'affichage, pour nourrir la réflexion de l'utilisateur sur son tableau de bord.

---

## 18. Éléments Métiers Additionnels (Vague 16)

Ce module finalise les mappings fonctionnels individuels de l'utilisateur :
* **Principes directeurs (`Principle`)** : Lois et règles incontournables définies par l'utilisateur pour guider ses choix et actions. Ils sont rattachables de façon granulaire à un pilier spécifique (`pillar` contenant `CROISSANCE`, `DEVELOPPEMENT`, `CONTRIBUTION` ou `DISCIPLINE`), permettant à un utilisateur d'avoir des principes dédiés par plan.
* **Équilibre intérieur (`InternalBalance`)** : Consigne les éléments clés de l'alignement personnel au cœur des fondations de son Plan de Vie (convictions profondes, valeurs cardinales, talents naturels et croyances limitantes à surmonter).
* **Liens de partage (`SharedLink`)** : Liens d'accès uniques sécurisés par jeton (`token`), générés par l'utilisateur pour partager temporairement ses plans de vie ou de croissance avec son entourage ou son mentor.

---

## 19. Modularité & Séparation du Code

* **Dossier `src/Entity/`** : Contient le modèle de données Doctrine. Les frontières des plans sont respectées à l'aide de clés étrangères optionnelles (`SET NULL` à la suppression) pour préserver le découplage.
* **Dossier `src/Repository/`** : Dépôts de données encapsulant les requêtes SQL/DQL.
* **Dossier `src/Controller/Api/`** : Contrôleurs REST retournant des réponses JSON dédiées à l'application mobile.
* **Dossier `src/Controller/Admin/`** : Contrôleurs de l'espace administration web (dont `DocController` pour le rendu de cette documentation).


