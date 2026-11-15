# Schéma de la Base de Données

Le projet utilise **MySQL/MariaDB** en production et en développement local. Les migrations sont gérées par **Doctrine Migrations**.

---

## 1. Tables de base & Pivots

Chaque utilisateur dispose d'une relation `OneToOne` avec les tables pivots des 5 plans :

* **`user`** : Gère l'authentification et l'identité (email, roles, password).
* **`plan_de_vie`** : Point d'ancrage de la vision et de la mission de vie (lié à `user_id`).
* **`plan_de_croissance`** : Suivi du caractère et des relations (lié à `user_id`).
* **`plan_de_developpement_personnel`** : Suivi des compétences et de la carrière (lié à `user_id`).
* **`plan_d_hygiene`** : Suivi de la santé physique/mentale et des routines (lié à `user_id`).
* **`plan_de_transmission`** : Suivi du mentorat, des legs et dons (lié à `user_id`).

---

## 2. Structure détaillée du "Plan de Vie"

Le plan de vie est composé des entités suivantes :

### A. Raison d'être (`purpose`)
*Relation : `OneToOne` avec `plan_de_vie`.*
* `id` (INT, PK AUTO_INCREMENT)
* `plan_de_vie_id` (INT, FK vers `plan_de_vie`)
* `statement` (TEXT) : Énoncé court de mission.
* `long_statement` (TEXT, Nullable) : Énoncé détaillé.
* `hypotheses` (TEXT, Nullable) : Hypothèses personnelles.
* `detail` (TEXT, Nullable) : Précisions complémentaires.
* `proofs` (TEXT, Nullable) : Éléments de preuve de l'alignement.

### B. Vision (`vision`)
*Relation : `OneToOne` avec `plan_de_vie`.*
* `id` (INT, PK AUTO_INCREMENT)
* `plan_de_vie_id` (INT, FK vers `plan_de_vie`)
* `content` (TEXT) : Contenu de la vision.
* `horizon` (VARCHAR(255)) : Horizon temporel (ex: 5 ans, 10 ans).
* `beneficiaries` (TEXT) : Bénéficiaires de la vision.
* `indicators` (TEXT) : Indicateurs clés de succès.
* `images` (TEXT, Nullable) : Liens ou métadonnées d'images d'inspiration.

### C. Valeurs (`value`)
*Relation : `ManyToOne` avec `plan_de_vie`.*
* `id` (INT, PK AUTO_INCREMENT)
* `plan_de_vie_id` (INT, FK vers `plan_de_vie`)
* `name` (VARCHAR(255)) : Nom de la valeur (ex: Intégrité).
* `behavior` (TEXT) : Action ou comportement concret traduisant cette valeur.

### D. Passions (`passion`)
*Relation : `ManyToOne` avec `plan_de_vie`.*
* `id` (INT, PK AUTO_INCREMENT)
* `plan_de_vie_id` (INT, FK vers `plan_de_vie`)
* `name` (VARCHAR(255)) : Titre de la passion.
* `context` (TEXT, Nullable) : Contexte ou description de la passion.

### E. Objectifs de vie (`life_goal`)
*Relation : `ManyToOne` avec `life_goal`.*
* `id` (INT, PK AUTO_INCREMENT)
* `plan_de_vie_id` (INT, FK vers `plan_de_vie`)
* `title` (VARCHAR(255)) : Titre de l'objectif.
* `category` (VARCHAR(50)) : Catégorie (BE, DO, HAVE, GIVE).
* `priority` (VARCHAR(50)) : Priorité (LOW, MEDIUM, HIGH).
* `deadline` (DATETIME, Nullable) : Date d'échéance.
* `metric` (VARCHAR(255), Nullable) : Indicateur de mesure.
* `progress` (INT) : Avancement en % (défaut 0).
* `is_shared` (TINYINT/BOOLEAN) : Statut de partage.

### F. Jalons d'objectifs (`life_milestone`)
*Relation : `ManyToOne` avec `life_goal`.*
* `id` (INT, PK AUTO_INCREMENT)
* `life_goal_id` (INT, FK vers `life_goal`)
* `title` (VARCHAR(255)) : Titre de l'étape.
* `due_date` (DATETIME) : Échéance du jalon.
* `completed` (TINYINT/BOOLEAN) : Statut de complétion.

---

## 3. Structure détaillée du "Plan de Croissance"

Le plan de croissance est composé des entités suivantes :

### A. Objectifs de croissance (`growth_goal`)
*Relation : `ManyToOne` avec `plan_de_croissance` et `ManyToMany` avec `life_goal`.*
* `id` (INT, PK AUTO_INCREMENT)
* `plan_de_croissance_id` (INT, FK vers `plan_de_croissance`)
* `title` (VARCHAR(255)) : Titre de l'objectif.
* `axis` (VARCHAR(50)) : Axe de croissance (Énumérateur : `CHARACTER`, `RELATION`).
* `character_action` (VARCHAR(50), Nullable) : Type d'action sur le trait de caractère (Énumérateur : `DEVELOP`, `STRENGTHEN`, `ABANDON`).
* `strategy` (TEXT, Nullable) : Stratégie cognitive et comportementale (conservée en base de données pour rétrocompatibilité, mais omise de l'interface graphique du wizard).
* `type` (VARCHAR(50), Nullable) : Catégorie d'objectif. Pour l'axe `RELATION`, contient la catégorie du réseau relationnel (`orientation`, `croissance`, `soutien`, `responsabilite`, `environnement`).
* `subtype` (VARCHAR(50), Nullable) : Sous-catégorie d'objectif.
* `metric` (VARCHAR(255), Nullable) : Indicateur d'avancement.
* `progress` (INT) : Avancement en % (défaut 0).
* `why` (TEXT, Nullable) : Motivation personnelle.
* `deadline` (DATETIME, Nullable) : Date d'échéance.
* `source_type` (VARCHAR(100), Nullable) : Type d'alimentation de l'axe (ex: `LIMITING_BELIEF`, `COUNTER_VALUE`, `DECLARED_VALUE`, `CONVICTION`).
* `source_id` (INT, Nullable) : ID de la ressource d'alimentation source.
* `created_at` (DATETIME) : Date d'initialisation.
* `updated_at` (DATETIME) : Date de mise à jour.

### B. Actions de croissance (`growth_action`)
*Relation : `ManyToOne` avec `growth_goal`.*
* `id` (INT, PK AUTO_INCREMENT)
* `growth_goal_id` (INT, FK vers `growth_goal`, ON DELETE CASCADE)
* `title` (VARCHAR(255)) : Description de l'action.
* `status` (VARCHAR(50)) : État de complétion (ex: `NOT_STARTED`, `IN_PROGRESS`, `COMPLETED`).
* `deadline` (DATETIME, Nullable) : Échéance (nullable).
* `created_at` (DATETIME) : Date d'initialisation.
* `updated_at` (DATETIME) : Date de mise à jour.

### C. Scores d'objectifs de croissance (`growth_goal_score`)
*Relation : `ManyToOne` avec `growth_goal`.*
* `id` (INT, PK AUTO_INCREMENT)
* `growth_goal_id` (INT, FK vers `growth_goal`, ON DELETE CASCADE)
* `score` (INT) : Score d'auto-évaluation (0 à 100).
* `progress_value` (INT) : Valeur numérique.
* `note` (TEXT, Nullable) : Remarque ou observation.
* `date` (DATETIME) : Date de saisie du score.

### D. Points de redevabilité (`confirm_point`)
*Relation : `ManyToOne` avec `plan_de_croissance`.*
* `id` (INT, PK AUTO_INCREMENT)
* `plan_de_croissance_id` (INT, FK vers `plan_de_croissance`)
* `statement` (TEXT) : Énoncé à faire valider par le mentor.
* `status` (VARCHAR(50)) : État de validation (ex: `PENDING`, `CONFIRMED`, `REJECTED`).
* `created_at` (DATETIME) : Date d'initialisation.

### E. Preuves de redevabilité (`evidence`)
*Relation : `ManyToOne` avec `confirm_point`.*
* `id` (INT, PK AUTO_INCREMENT)
* `confirm_point_id` (INT, FK vers `confirm_point`, ON DELETE CASCADE)
* `type` (VARCHAR(50)) : Format de la preuve (ex: `TEXT`, `IMAGE`, `LINK`).
* `content` (TEXT) : Preuve factuelle ou note.
* `source_id` (VARCHAR(255), Nullable) : Référence externe.
* `created_at` (DATETIME) : Date de soumission.

### F. Association Croissance-Vie (`growth_goal_life_goal`)
*Table de jointure pour relation ManyToMany.*
* `growth_goal_id` (INT, FK vers `growth_goal`, ON DELETE CASCADE)
* `life_goal_id` (INT, FK vers `life_goal`, ON DELETE CASCADE)

---

## 4. Structure détaillée du "Plan de Développement Personnel" (PDP)

Le plan de développement personnel est composé des entités suivantes :

### A. Objectifs de compétences (`skill_goal`)
*Relation : `ManyToOne` avec `plan_de_developpement_personnel` et `ManyToMany` avec `life_goal`.*
* `id` (INT, PK AUTO_INCREMENT)
* `plan_de_developpement_personnel_id` (INT, FK vers `plan_de_developpement_personnel`)
* `title` (VARCHAR(255)) : Titre de la compétence visée.
* `category` (VARCHAR(50)) : Catégorie de la compétence (Énumérateur : `TECHNICAL`, `INTERPERSONAL`, `COGNITIVE`, `EMOTIONAL`, `FINANCIAL`, `SOCIAL`, `PHYSICAL`).
* `target_level` (VARCHAR(255), Nullable) : Niveau cible visé (ex: Intermédiaire, Expert).
* `deadline` (DATETIME, Nullable) : Date d'échéance.
* `progress` (INT) : Avancement en % (défaut 0).
* `created_at` (DATETIME) : Date de création.
* `updated_at` (DATETIME) : Date de modification.

### B. Ressources d'apprentissage (`learning_resource`)
*Relation : `ManyToOne` avec `skill_goal`.*
* `id` (INT, PK AUTO_INCREMENT)
* `skill_goal_id` (INT, FK vers `skill_goal`, ON DELETE CASCADE)
* `title` (VARCHAR(255)) : Titre du support d'apprentissage (livre, formation, article, etc.).
* `type` (VARCHAR(50)) : Type de ressource (ex: `BOOK`, `COURSE`, `ARTICLE`, `VIDEO`).
* `url` (VARCHAR(255), Nullable) : URL d'accès direct.
* `author` (VARCHAR(255), Nullable) : Nom de l'auteur ou du formateur.
* `status` (VARCHAR(50)) : État de progression (ex: `NOT_STARTED`, `IN_PROGRESS`, `COMPLETED`).
* `progress` (INT) : Pourcentage d'avancement de l'apprentissage.
* `rating` (INT, Nullable) : Évaluation sur 5.
* `notes` (TEXT, Nullable) : Remarques ou résumé d'apprentissage.
* `created_at` (DATETIME) : Date de création.
* `updated_at` (DATETIME) : Date de modification.

### C. Étapes de carrière (`career_step`)
*Relation : `ManyToOne` avec `plan_de_developpement_personnel`.*
* `id` (INT, PK AUTO_INCREMENT)
* `plan_de_developpement_personnel_id` (INT, FK vers `plan_de_developpement_personnel`)
* `title` (VARCHAR(255)) : Objectif ou étape professionnelle.
* `company` (VARCHAR(255), Nullable) : Entreprise cible.
* `status` (VARCHAR(50)) : État de l'étape (ex: `NOT_STARTED`, `IN_PROGRESS`, `COMPLETED`).
* `deadline` (DATETIME, Nullable) : Date d'échéance professionnelle.
* `description` (TEXT, Nullable) : Détail des démarches à mener.
* `created_at` (DATETIME) : Date de création.
* `updated_at` (DATETIME) : Date de modification.

### D. Association Compétences-Vie (`skill_goal_life_goal`)
*Table de jointure pour relation ManyToMany.*
* `skill_goal_id` (INT, FK vers `skill_goal`, ON DELETE CASCADE)
* `life_goal_id` (INT, FK vers `life_goal`, ON DELETE CASCADE)

---

## 5. Structure détaillée du "Plan d'Hygiène"

Le plan d'hygiène est composé des entités suivantes :

### A. Domaines d'hygiène (`hygiene_domain`)
*Relation : `ManyToOne` avec `plan_d_hygiene`.*
* `id` (INT, PK AUTO_INCREMENT)
* `plan_d_hygiene_id` (INT, FK vers `plan_d_hygiene`)
* `name` (VARCHAR(255)) : Nom du domaine d'équilibre (ex: Physique, Mental & Spirituel, Nutrition).
* `description` (TEXT, Nullable) : Objectifs généraux ou intentions d'équilibre.
* `target_score` (INT) : Objectif de points d'hygiène (défaut 100).
* `created_at` (DATETIME) : Date de création.
* `updated_at` (DATETIME) : Date de modification.

### B. Bilans journaliers (`daily_log`)
*Relation : `ManyToOne` avec `plan_d_hygiene`.*
* `id` (INT, PK AUTO_INCREMENT)
* `plan_d_hygiene_id` (INT, FK vers `plan_d_hygiene`)
* `date` (DATE) : Date de l'enregistrement quotidien.
* `sleep_hours` (FLOAT, Nullable) : Nombre d'heures de sommeil.
* `sleep_quality` (INT, Nullable) : Note sur 5 de la qualité du sommeil.
* `energy_level` (INT, Nullable) : Niveau d'énergie quotidien (1 à 5).
* `stress_level` (INT, Nullable) : Niveau de stress de la journée (1 à 5).
* `intention` (VARCHAR(255), Nullable) : Focus ou intention de la journée.
* `mood` (VARCHAR(100), Nullable) : Humeur (ex: Joyeux, Épuisé, Inspiré).
* `notes` (TEXT, Nullable) : Remarques ou réflexions journalières.
* `created_at` (DATETIME) : Date de création.
* `updated_at` (DATETIME) : Date de modification.
* **Contrainte d'unicité** : Index unique `uniq_daily_log_plan_date` sur `[plan_d_hygiene_id, date]`.

---

## 6. Structure détaillée du "Plan de Transmission"

Le plan de transmission est composé des entités suivantes :

### A. Objectifs de legs (`legacy_goal`)
*Relation : `ManyToOne` avec `plan_de_transmission`.*
* `id` (INT, PK AUTO_INCREMENT)
* `plan_de_transmission_id` (INT, FK vers `plan_de_transmission`)
* `title` (VARCHAR(255)) : Titre de l'objectif de transmission.
* `target_beneficiary` (VARCHAR(255), Nullable) : Bénéficiaires ciblés (ex: Juniors, Famille, Communauté).
* `description` (TEXT, Nullable) : Description textuelle de l'objectif.
* `progress` (INT) : Avancement en % (défaut 0).
* `deadline` (DATETIME, Nullable) : Date d'échéance.
* `created_at` (DATETIME) : Date de création.
* `updated_at` (DATETIME) : Date de modification.

### B. Actions de transmission (`transmission_action`)
*Relation : `ManyToOne` avec `legacy_goal`.*
* `id` (INT, PK AUTO_INCREMENT)
* `legacy_goal_id` (INT, FK vers `legacy_goal`, ON DELETE CASCADE)
* `title` (VARCHAR(255)) : Intitulé de l'action de transmission.
* `status` (VARCHAR(50)) : État de complétion (ex: `NOT_STARTED`, `IN_PROGRESS`, `COMPLETED`).
* `deadline` (DATETIME, Nullable) : Échéance.
* `created_at` (DATETIME) : Date de création.
* `updated_at` (DATETIME) : Date de modification.

### C. Don de talents (`talent_don`)
*Relation : `ManyToOne` avec `plan_de_transmission`.*
* `id` (INT, PK AUTO_INCREMENT)
* `plan_de_transmission_id` (INT, FK vers `plan_de_transmission`)
* `text` (TEXT) : Énoncé du don de talents ou du partage.
* `created_at` (DATETIME) : Date de création.
* `updated_at` (DATETIME) : Date de modification.

---

## 7. Système d'Abonnement (Billing & Subscriptions)

> [!NOTE]
> Pour plus d'informations sur le fonctionnement des abonnements, les énumérations PHP associées et l'intégration des webhooks Stripe, consultez la page dédiée : **[Système d'Abonnement (Billing & Subscriptions)](subscription.md)**.

Ce module gère le modèle économique et l'accès aux fonctionnalités premium :

### A. Abonnements (`subscription`)
*Relation : `OneToOne` avec `user`.*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`)
* `tier` (VARCHAR(50)) : Niveau de l'abonnement (Énumérateur : `FREE`, `PRO`, `TEAM`).
* `status` (VARCHAR(50)) : État de l'abonnement (Énumérateur : `ACTIVE`, `TRIAL`, `PAST_DUE`, `CANCELED`, `EXPIRED`).
* `trial_start_at` (DATETIME, Nullable) : Début de la période d'essai.
* `trial_end_at` (DATETIME, Nullable) : Fin de la période d'essai.
* `is_trial_used` (TINYINT/BOOLEAN) : Indicateur d'usage de l'essai gratuit.
* `current_period_start` (DATETIME, Nullable) : Début de la période de facturation en cours.
* `current_period_end` (DATETIME, Nullable) : Fin de la période de facturation en cours.
* `cancel_at_period_end` (TINYINT/BOOLEAN) : Résiliation programmée à la fin de la période.
* `stripe_customer_id` (VARCHAR(255), Nullable) : Identifiant client Stripe.
* `stripe_subscription_id` (VARCHAR(255), Nullable) : Identifiant d'abonnement Stripe.
* `stripe_payment_method_id` (VARCHAR(255), Nullable) : Identifiant du moyen de paiement.
* `created_at` (DATETIME) : Date d'initialisation.
* `updated_at` (DATETIME) : Date de mise à jour.

### B. Transactions (`transaction`)
*Relation : `ManyToOne` avec `subscription`.*
* `id` (INT, PK AUTO_INCREMENT)
* `subscription_id` (INT, FK vers `subscription`)
* `amount` (DOUBLE/FLOAT) : Montant payé.
* `currency` (VARCHAR(10)) : Devise (défaut `EUR`).
* `status` (VARCHAR(50)) : État du paiement (ex: `succeeded`, `failed`).
* `description` (TEXT, Nullable) : Motif de la transaction.
* `stripe_invoice_id` (VARCHAR(255), Nullable) : Identifiant de facture Stripe.
* `created_at` (DATETIME) : Date de la transaction.

---

## 8. Fondations Transversales & Profil

Ces entités fournissent les bases structurelles indépendantes partagées par les 5 plans métiers :

### A. Profil Utilisateur (`user_profile`)
*Relation : `OneToOne` avec `user`.*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`)
* `first_name` (VARCHAR(100), Nullable) : Prénom.
* `last_name` (VARCHAR(100), Nullable) : Nom.
* `bio` (TEXT, Nullable) : Biographie personnelle.
* `date_of_birth` (DATE, Nullable) : Date de naissance.
* `gender` (VARCHAR(20), Nullable) : Genre de l'utilisateur.
* `marital_status` (VARCHAR(50), Nullable) : Situation familiale.
* `children` (INT, défaut 0) : Nombre d'enfants.
* `phone` (VARCHAR(50), Nullable) : Numéro de téléphone.
* `city` (VARCHAR(100), Nullable) : Ville de résidence.
* `country` (VARCHAR(100), Nullable) : Pays de résidence.
* `profession` (VARCHAR(150), Nullable) : Profession.
* `social_linked_in` (VARCHAR(255), Nullable) : Lien LinkedIn.
* `social_twitter` (VARCHAR(255), Nullable) : Lien Twitter/X.
* `social_website` (VARCHAR(255), Nullable) : Site internet personnel.
* `social_facebook` (VARCHAR(255), Nullable) : Lien Facebook.
* `whatsapp` (VARCHAR(50), Nullable) : Numéro Whatsapp.
* `onboarding_completed` (TINYINT/BOOLEAN) : Statut du parcours d'onboarding.
* `allow_team_advice` (TINYINT/BOOLEAN) : Autorisation de recevoir des conseils.
* `timezone` (VARCHAR(100), défaut 'UTC') : Fuseau horaire.
* `locale` (VARCHAR(10), défaut 'fr-FR') : Langue préférée.
* `created_at` (DATETIME) : Date de création.
* `updated_at` (DATETIME) : Date de modification.

### B. Habitudes (`habit`)
*Relation : `ManyToOne` avec `user`, optionnellement `life_goal`, `growth_goal`, `skill_goal`, `hygiene_domain` et `legacy_goal`.*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`)
* `life_goal_id` (INT, FK vers `life_goal`, Nullable, ON DELETE SET NULL)
* `growth_goal_id` (INT, FK vers `growth_goal`, Nullable, ON DELETE SET NULL)
* `skill_goal_id` (INT, FK vers `skill_goal`, Nullable, ON DELETE SET NULL)
* `hygiene_domain_id` (INT, FK vers `hygiene_domain`, Nullable, ON DELETE SET NULL)
* `legacy_goal_id` (INT, FK vers `legacy_goal`, Nullable, ON DELETE SET NULL)
* `title` (VARCHAR(255)) : Intitulé de la routine.
* `frequency` (VARCHAR(50)) : Fréquence (Enum : `DAILY`, `WEEKLY`, `MONTHLY`).
* `end_date` (DATE, Nullable) : Date de fin programmée de l'habitude.
* `recurrence_day` / `recurrence_week` (INT, Nullable) : Paramètres de récurrence.
* `implementation_when` / `implementation_then` (TEXT, Nullable) : Stratégie cognitive d'implémentation.
* `support_strategy` (TEXT, Nullable) : Stratégies de soutien de l'habitude.
* `rooting_intention` / `rooting_action` / `rooting_moment` / `rooting_anchor` / `rooting_benefit` / `rooting_facilitator` (TEXT, Nullable) : Paramètres d'ancrage de l'habitude.
* `created_at` (DATETIME) : Date de création.
* `updated_at` (DATETIME) : Date de modification.

### C. Historique des Habitudes (`habit_log`)
*Relation : `ManyToOne` avec `habit`.*
* `id` (INT, PK AUTO_INCREMENT)
* `habit_id` (INT, FK vers `habit`, ON DELETE CASCADE)
* `date` (DATE) : Jour du suivi.
* `status` (TINYINT/BOOLEAN) : Complété (`1`) ou Non complété (`0`).
* `created_at` (DATETIME) : Date de l'enregistrement.

### D. Commentaires partagés (`comment`)
*Relation : `ManyToOne` avec l'auteur (`user`) et cibles optionnelles.*
* `id` (INT, PK AUTO_INCREMENT)
* `author_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `content` (TEXT) : Contenu du commentaire.
* `section` (VARCHAR(100), Nullable) : Section ou contexte spécifique de la page.
* `purpose_id` (INT, FK vers `purpose`, Nullable, ON DELETE CASCADE) : Liaison raison d'être.
* `vision_id` (INT, FK vers `vision`, Nullable, ON DELETE CASCADE) : Liaison vision.
* `life_goal_id` (INT, FK vers `life_goal`, Nullable, ON DELETE CASCADE) : Liaison objectif de vie.
* `habit_id` (INT, FK vers `habit`, Nullable, ON DELETE CASCADE) : Liaison habitude.
* `growth_goal_id` (INT, FK vers `growth_goal`, Nullable, ON DELETE CASCADE) : Liaison objectif de croissance.
* `skill_goal_id` (INT, FK vers `skill_goal`, Nullable, ON DELETE CASCADE) : Liaison objectif de compétences.
* `hygiene_domain_id` (INT, FK vers `hygiene_domain`, Nullable, ON DELETE CASCADE) : Liaison domaine d'hygiène.
* `legacy_goal_id` (INT, FK vers `legacy_goal`, Nullable, ON DELETE CASCADE) : Liaison objectif de legs/transmission.
* `target_user_id` (INT, FK vers `user`, Nullable, ON DELETE CASCADE) : Liaison utilisateur (redevabilité).
* `created_at` (DATETIME) : Date du commentaire.

### E. Saisons de vie (`season`)
*Relation : `ManyToOne` avec `user`.*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `start_date` (DATE) : Début de la saison.
* `end_date` (DATE) : Fin de la saison.
* `theme` (VARCHAR(255), Nullable) : Thème inspirant de la saison (ex: "Saison de la mise au point").
* `created_at` (DATETIME) : Date de création.

---

## 9. Structure détaillée des "Profils de Personnalité & Tests" (Vague 6)

Ce module gère le référentiel des questionnaires et profils psychologiques (DISC, MBTI, Ennéagramme, Big 5, Intelligences Multiples, Hygiène de Vie), ainsi que l'historique des tests passés par les utilisateurs.

### A. Passage de tests (`profile_test`)
*Relation : `ManyToOne` avec `user`.*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `type` (VARCHAR(50)) : Type de test (ex: `DISC`, `MBTI`, `ENNEAGRAMME`, `BIG5`, `INTELLIGENCES`, `HYGIENE`).
* `result` (TEXT) : Contenu JSON sérilisé des résultats et scores détaillés.
* `insights` (TEXT, Nullable) : Synthèse d'analyse ou observations textuelles générées.
* `attachments` (TEXT, Nullable) : Liens ou métadonnées des pièces jointes associées.
* `date` (DATETIME) : Date effective de passage du test.
* `created_at` (DATETIME) : Date d'initialisation de l'enregistrement.
* `updated_at` (DATETIME) : Date de mise à jour.

### B. Questions du questionnaire (`personality_question`)
*Relation : `OneToMany` avec `personality_answer`.*
* `id` (INT, PK AUTO_INCREMENT)
* `test_type` (VARCHAR(50)) : Type de test concerné (ex: `DISC`).
* `category` (VARCHAR(100), Nullable) : Catégorie, axe ou trait ciblé par la question.
* `question` (TEXT) : Libellé complet de la question.
* `sort_order` (INT) : Ordre d'affichage séquentiel dans le test.
* `created_at` (DATETIME) : Date de création.
* `updated_at` (DATETIME) : Date de modification.

### C. Réponses de questionnaire (`personality_answer`)
*Relation : `ManyToOne` avec `personality_question`.*
* `id` (INT, PK AUTO_INCREMENT)
* `question_id` (INT, FK vers `personality_question`, ON DELETE CASCADE)
* `text` (TEXT) : Libellé de la réponse ou option de réponse.
* `value` (VARCHAR(255)) : Valeur de scoring associée à cette option (ex: `D`, `I`, `S`, `C`, ou nom de l'intelligence).

### D. Profils psychologiques de référence (`personality_profile`)
*Unique Constraint : couple `[test_type, code]` unique.*
* `id` (INT, PK AUTO_INCREMENT)
* `test_type` (VARCHAR(50)) : ex: `DISC`, `MBTI`, `ENNEAGRAMME`, `BIG5`, `INTELLIGENCES`, `HYGIENE`.
* `code` (VARCHAR(50)) : Code d'identification unique pour le test (ex: `D`, `INFP`, `1`, `O`).
* `title` (VARCHAR(255)) : Libellé ou titre du profil.
* `description` (TEXT) : Description globale du profil de personnalité.
* `strengths` (TEXT, Nullable) : Forces et atouts majeurs.
* `weaknesses` (TEXT, Nullable) : Faiblesses et limites.
* `relationships` (TEXT, Nullable) : Dynamiques relationnelles et de couple.
* `career` (TEXT, Nullable) : Orientations de carrière conseillées.
* `emotional_dynamics` (TEXT, Nullable) : Fonctionnement émotionnel et stress.
* `essence` (TEXT, Nullable) : Essence / cœur spirituel et philosophique.
* `growth_axes` (TEXT, Nullable) : Axes de développement personnel recommandés.
* `feedback_low` (TEXT, Nullable) : Retour d'évaluation gradué (Bas).
* `feedback_mid` (TEXT, Nullable) : Retour d'évaluation gradué (Moyen).
* `feedback_high` (TEXT, Nullable) : Retour d'évaluation gradué (Élevé).
* `created_at` (DATETIME) : Date de création.
* `updated_at` (DATETIME) : Date de modification.

---

## 10. Structure détaillée du "Réseau, Redevabilité & Mentorat" (Vague 7)

Ce module gère le réseau social de redevabilité de l'utilisateur, à savoir ses relations avec ses pairs/mentors, ses demandes de conseils, ses messages de coaching et ses suggestions d'objectifs de vie.

### A. Connexions de redevabilité (`connection`)
*Relation : Double relation `ManyToOne` avec `user` (requester, receiver).*
*Unique Constraint : couple `[requester_id, receiver_id]` unique.*
* `id` (INT, PK AUTO_INCREMENT)
* `requester_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `receiver_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `status` (VARCHAR(50)) : PENDING, ACCEPTED, REJECTED.
* `type` (VARCHAR(50)) : FRIEND, MENTOR, COACH.
* `share_scope` (VARCHAR(50)) : Enum `ShareScope` (TITLES_ONLY, METRICS, ACTION_PLAN, CHECKINS).
* **Champs de partage (Booleans / TINYINT)** :
  * `requester_share_life_plan`
  * `requester_share_growth_plan`
  * `requester_share_habits`
  * `requester_share_reviews`
  * `requester_share_discovery`
  * `requester_share_croissance`
  * `requester_share_developpement`
  * `requester_share_hygiene`
  * `requester_share_transmission`
  * `requester_share_bilan`
  * `receiver_share_life_plan`
  * `receiver_share_growth_plan`
  * `receiver_share_habits`
  * `receiver_share_reviews`
  * `receiver_share_discovery`
  * `receiver_share_croissance`
  * `receiver_share_developpement`
  * `receiver_share_hygiene`
  * `receiver_share_transmission`
  * `receiver_share_bilan`
* `created_at` (DATETIME) : Date d'établissement de la relation.

### B. Demandes d'avis ou conseils (`advice_request`)
*Relation : `ManyToOne` avec `user` et `ConfirmPoint`.*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `point_id` (INT, FK vers `confirm_point`, Nullable, ON DELETE SET NULL)
* `message` (TEXT, Nullable) : Message d'accompagnement ou question.
* `created_at` (DATETIME) : Date d'envoi.

### C. Réponses aux demandes de conseils (`advice_response`)
*Relation : `ManyToOne` avec `advice_request`.*
* `id` (INT, PK AUTO_INCREMENT)
* `request_id` (INT, FK vers `advice_request`, ON DELETE CASCADE)
* `provider_name` (VARCHAR(255), Nullable) : Identité du répondant.
* `provider_email` (VARCHAR(255), Nullable) : Email du répondant.
* `q1_observation` (TEXT, Nullable) : Observations constatées.
* `q2_risk` (TEXT, Nullable) : Risques anticipés.
* `q3_advice` (TEXT, Nullable) : Actions et conseils stratégiques formulés.
* `created_at` (DATETIME) : Date de réponse.

### D. Messages de coaching (`coaching_message`)
*Relation : Double relation `ManyToOne` avec `user` (sender, receiver).*
* `id` (INT, PK AUTO_INCREMENT)
* `sender_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `receiver_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `content` (TEXT) : Contenu du message de coaching.
* `context` (VARCHAR(255), Nullable) : Sujet ou plan métier lié.
* `is_read` (TINYINT/BOOLEAN) : Statut de lecture.
* `created_at` (DATETIME) : Date d'envoi.

### E. Suggestions d'objectifs (`goal_suggestion`)
*Relation : Double relation `ManyToOne` avec `user` (fromUser, toUser).*
* `id` (INT, PK AUTO_INCREMENT)
* `from_user_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `to_user_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `title` (VARCHAR(255)) : Titre de l'objectif suggéré.
* `description` (TEXT, Nullable) : Description ou pourquoi de la suggestion.
* `category` (VARCHAR(50)) : Catégorie conseillée de l'objectif.
* `status` (VARCHAR(50)) : PENDING, ACCEPTED, REJECTED.
* `created_at` (DATETIME) : Date de suggestion.

---

## 11. Structure détaillée du "Système de Journaling & Réflexion" (Vague 8)

Ce module gère le journal intime de bord de l'utilisateur, ses notes de réflexion (idées, jalons, résolutions, témoignages), et leur classement dans des dossiers/collections thématiques.

### A. Dossiers de regroupement de réflexions (`journal_collection`)
*Relation : `ManyToOne` avec `user` (cascade remove).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `name` (VARCHAR(255)) : Nom du dossier
* `icon` (VARCHAR(50)) : Nom ou référence de l'icône (défaut 'Folder')
* `color` (VARCHAR(50)) : Couleur au format hexadécimal (défaut '#6366f1')
* `created_at` (DATETIME)
* `updated_at` (DATETIME)

### B. Notes de réflexion (`reflection_note`)
*Relation : `ManyToOne` avec `user` (cascade remove), `JournalCollection`, `LifeGoal`, `GrowthGoal` (tous en ON DELETE SET NULL).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `collection_id` (INT, FK vers `journal_collection`, Nullable, ON DELETE SET NULL)
* `goal_id` (INT, FK vers `life_goal`, Nullable, ON DELETE SET NULL)
* `growth_goal_id` (INT, FK vers `growth_goal`, Nullable, ON DELETE SET NULL)
* `content` (TEXT) : Contenu textuel rédigé
* `date` (DATETIME) : Date effective de la réflexion (défaut `now()`)
* `mood` (VARCHAR(100), Nullable) : Humeur ressentie
* `energy` (INT, Nullable) : Niveau d'énergie chiffré
* `type` (VARCHAR(50)) : Enum `ReflectionType` (REFLECTION, IDEA, MILESTONE, INSIGHT, RESOLUTION, TEMOIGNAGE)
* `location` (VARCHAR(255), Nullable) : Contexte spatial ou géolocalisation
* `created_at` (DATETIME)
* `updated_at` (DATETIME)

---

## 12. Structure détaillée de "Planification & Agenda" (Vague 9)

Ce module permet de gérer les tâches planifiées de l'agenda et les listes de tâches personnelles rattachées aux objectifs de l'utilisateur.

### A. Tâches planifiées (`planner_task`)
*Relation : ManyToOne avec `user` (cascade remove) et `growth_goal` (ON DELETE SET NULL).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `growth_goal_id` (INT, FK vers `growth_goal`, Nullable, ON DELETE SET NULL)
* `title` (VARCHAR(255)) : Titre de la tâche
* `description` (TEXT, Nullable) : Description de la tâche
* `date` (DATETIME) : Date programmée
* `is_all_day` (TINYINT/BOOLEAN) : Indique si la tâche prend toute la journée
* `start_time` (DATETIME, Nullable) : Heure de début
* `end_time` (DATETIME, Nullable) : Heure de fin
* `is_completed` (TINYINT/BOOLEAN) : Indique si la tâche est terminée
* `google_event_id` (VARCHAR(255), Nullable) : Id de synchronisation Google Calendar
* `created_at` (DATETIME)
* `updated_at` (DATETIME)

### B. Listes de tâches de l'utilisateur (`user_list`)
*Relation : ManyToOne avec `user` (cascade remove).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `name` (VARCHAR(255)) : Nom de la liste
* `icon` (VARCHAR(50)) : Emoji/Icône (défaut '📋')
* `color` (VARCHAR(50)) : Couleur hexadécimale (défaut '#6366f1')
* `is_default` (TINYINT/BOOLEAN) : Liste par défaut
* `created_at` (DATETIME)
* `updated_at` (DATETIME)

### C. Éléments de liste de tâches (`list_item`)
*Relation : ManyToOne avec `user_list` (cascade remove) et `life_goal` (ON DELETE SET NULL).*
* `id` (INT, PK AUTO_INCREMENT)
* `list_id` (INT, FK vers `user_list`, ON DELETE CASCADE)
* `goal_id` (INT, FK vers `life_goal`, Nullable, ON DELETE SET NULL)
* `title` (VARCHAR(255)) : Titre de l'élément
* `subtitle` (VARCHAR(255), Nullable) : Complément/Sous-titre
* `note` (TEXT, Nullable) : Remarque ou note descriptive
* `url` (VARCHAR(255), Nullable) : Lien URL associé
* `is_completed` (TINYINT/BOOLEAN) : Complétion de la tâche
* `priority` (INT) : Priorité / ordre de tri
* `created_at` (DATETIME)
* `updated_at` (DATETIME)

---

## 13. Structure détaillée de "Rétrospectives & Bilans" (Vague 10)

Ce module permet à l'utilisateur de s'auto-évaluer régulièrement via des check-ins hebdomadaires, des revues mensuelles et d'enregistrer des instantanés (snapshots) de ses plans.

### A. Bilans d'alignement (`check_in`)
*Relation : ManyToOne avec `user` (cascade remove).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `date` (DATETIME) : Date du check-in
* `done` (TEXT) : Éléments accomplis
* `not_done` (TEXT) : Éléments non accomplis
* `blocker` (TEXT, Nullable) : Obstacles
* `adjustment` (TEXT, Nullable) : Ajustements
* `action48h` (TEXT, Nullable) : Actions prioritaires sous 48h

### B. Revues mensuelles (`monthly_review`)
*Relation : ManyToOne avec `user` (cascade remove).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `month` (DATETIME) : Mois évalué
* `progress` (TEXT, Nullable) : Progrès constatés
* `stagnation` (TEXT, Nullable) : Points de stagnation
* `blocker` (TEXT, Nullable) : Blocages
* `adjustment` (TEXT, Nullable) : Ajustements
* `decision` (TEXT, Nullable) : Décisions du mois
* `created_at` (DATETIME)

### C. Historique des plans (`plan_history`)
*Relation : ManyToOne avec `user` (cascade remove).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`, ON DELETE CASCADE)
* `title` (VARCHAR(255)) : Libellé/Titre du snapshot
* `data` (TEXT) : Données JSON sérialisées du plan
* `created_at` (DATETIME)

---

## 14. Structure détaillée de "Défis Collectifs & Challenges" (Vague 11)

Ce module permet aux utilisateurs de créer et de participer à des défis collaboratifs, d'y inviter d'autres membres, de poster des activités d'avancement et de logger quotidiennement leurs complétions d'habitudes liées.

### A. Challenges (`challenge`)
*Relation : ManyToOne avec le créateur (`user`, ON DELETE CASCADE).*
* `id` (INT, PK AUTO_INCREMENT)
* `creator_id` (INT, FK vers `user`)
* `title` (VARCHAR(255)) : Titre du défi
* `description` (TEXT, Nullable) : Description textuelle
* `type` (VARCHAR(50)) : Type de défi (ex: `HABIT`)
* `habit_title` (VARCHAR(255), Nullable) : Titre de l'habitude ciblée
* `target_value` (INT, Nullable) : Objectif chiffré éventuel
* `start_date` (DATETIME) : Date de début du défi
* `end_date` (DATETIME, Nullable) : Date de fin du défi
* `frequency` (VARCHAR(50)) : Fréquence (ex: `DAILY`, `WEEKLY`)
* `visibility` (VARCHAR(50)) : Visibilité (ex: `PUBLIC`, `PARTNERS`)
* `recurrence_day` / `recurrence_week` / `recurrence_ordinal` (INT, Nullable) : Paramètres de récurrence temporelle
* `recurrence_days` (VARCHAR(255), Nullable) : Jours de récurrence (ex: sous forme de chaîne)
* `created_at` (DATETIME) : Date de création

### B. Participants aux défis (`challenge_participant`)
*Relation : ManyToOne avec le défi (`challenge`, ON DELETE CASCADE), le participant (`user`, ON DELETE CASCADE) et optionnellement les objectifs personnels (`life_goal`, `growth_goal`, tous deux en ON DELETE SET NULL).*
* `id` (INT, PK AUTO_INCREMENT)
* `challenge_id` (INT, FK vers `challenge`)
* `user_id` (INT, FK vers `user`)
* `joined_at` (DATETIME) : Date d'adhésion au défi
* `status` (VARCHAR(50)) : Statut (`ACTIVE`, `COMPLETED`, `LEFT`)
* `progress` (INT) : Avancement personnel en %
* `linked_goal_id` (INT, FK vers `life_goal`, Nullable) : Objectif de vie lié
* `linked_growth_goal_id` (INT, FK vers `growth_goal`, Nullable) : Objectif de croissance lié
* **Contrainte d'unicité** : Index unique `UNIQ_CHALLENGE_USER` sur `[challenge_id, user_id]`.

### C. Logs de défis (`challenge_log`)
*Relation : ManyToOne avec le participant (`challenge_participant`, ON DELETE CASCADE).*
* `id` (INT, PK AUTO_INCREMENT)
* `participant_id` (INT, FK vers `challenge_participant`)
* `date` (DATETIME) : Date du log
* `status` (TINYINT/BOOLEAN) : Complétion effective (oui/non)

### D. Activités de défis (`challenge_activity`)
*Relation : ManyToOne avec le défi (`challenge`, ON DELETE CASCADE) et l'auteur de l'action (`user`, ON DELETE CASCADE).*
* `id` (INT, PK AUTO_INCREMENT)
* `challenge_id` (INT, FK vers `challenge`)
* `user_id` (INT, FK vers `user`)
* `type` (VARCHAR(50)) : Type d'action (ex: `JOIN`, `COMPLETE`, `POST`)
* `content` (TEXT) : Message ou description de l'action
* `created_at` (DATETIME) : Date de l'activité

### E. Invitations de défis (`challenge_invitation`)
*Relation : ManyToOne avec le défi (`challenge`, ON DELETE CASCADE), l'expéditeur (`user`, ON DELETE CASCADE) et le destinataire (`user`, ON DELETE CASCADE).*
* `id` (INT, PK AUTO_INCREMENT)
* `challenge_id` (INT, FK vers `challenge`)
* `sender_id` (INT, FK vers `user`)
* `receiver_id` (INT, FK vers `user`)
* `status` (VARCHAR(50)) : Statut de l'invitation (ex: `PENDING`, `ACCEPTED`, `REJECTED`)
* `created_at` (DATETIME) : Date d'envoi

---

## 15. Structure détaillée de "Notifications & Préférences" (Vague 12)

Ce module gère les alertes en temps réel, l'historique des notifications in-app et les préférences de réception des notifications pour chaque utilisateur.

### A. Notifications (`notification`)
*Relation : ManyToOne avec le destinataire (`user`, ON DELETE CASCADE).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`)
* `type` (VARCHAR(50)) : Type de notification (Enum `NotificationType`)
* `title` (VARCHAR(255)) : Titre court du message
* `message` (TEXT) : Contenu descriptif complet
* `read` (TINYINT/BOOLEAN) : Indicateur d'état de lecture
* `action_url` (VARCHAR(255), Nullable) : Lien URL de redirection
* `metadata` (TEXT, Nullable) : Chaîne JSON contenant des données structurées spécifiques
* `created_at` (DATETIME) : Date de création

### B. Préférences de notifications (`notification_preference`)
*Relation : OneToOne avec l'utilisateur (`user`, ON DELETE CASCADE).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`)
* `enable_in_app` (TINYINT/BOOLEAN) : Activation des notifications in-app
* `enable_email_instant` (TINYINT/BOOLEAN) : Activation des emails instantanés
* `enable_email_digest` (TINYINT/BOOLEAN) : Activation des digests par e-mail
* `notify_connection` (TINYINT/BOOLEAN) : Alerte de connexion
* `notify_challenge` (TINYINT/BOOLEAN) : Alerte de défi
* `notify_feedback` (TINYINT/BOOLEAN) : Alerte de feedback
* `notify_suggestion` (TINYINT/BOOLEAN) : Alerte de suggestion d'objectif
* `notify_comment` (TINYINT/BOOLEAN) : Alerte de commentaire
* `notify_monthly_review` (TINYINT/BOOLEAN) : Alerte de revue mensuelle
* `preferred_time` (VARCHAR(10)) : Heure préférée d'envoi du digest (ex: "09:00")
* `notify_habit_reminder` (TINYINT/BOOLEAN) : Rappel d'habitudes
* `notify_goal_deadline` (TINYINT/BOOLEAN) : Rappel d'échéances d'objectifs
* `notify_weekly_review` (TINYINT/BOOLEAN) : Rappel de revue hebdomadaire
* `weekly_review_day` (INT) : Jour de revue hebdomadaire (0 = Dimanche, 1 = Lundi, etc.)
* `digest_frequency` (VARCHAR(50)) : Fréquence du digest (ex: `DAILY`, `WEEKLY`)
* `digest_day` (INT) : Jour du digest dans la semaine ou le mois
* `created_at` (DATETIME) : Date de création
* `updated_at` (DATETIME) : Date de modification

---

## 16. Structure détaillée de "Feedbacks & Demandes" (Vague 13)

Ce module gère le recueil d'avis à 360° et les évaluations de l'entourage/communauté.

### A. Feedbacks directs (`feedback`)
*Relation : ManyToOne avec l'expéditeur (`from_user_id`, ON DELETE CASCADE) et le destinataire (`to_user_id`, ON DELETE CASCADE).*
* `id` (INT, PK AUTO_INCREMENT)
* `from_user_id` (INT, FK vers `user`)
* `to_user_id` (INT, FK vers `user`)
* `content` (TEXT) : Contenu du feedback
* `impact` (VARCHAR(50), Nullable) : Type d'impact (Enum `Impact` : `POSITIVE`, `CONSTRUCTIVE`)
* `created_at` (DATETIME) : Date de création

### B. Demandes de feedback (`feedback_request`)
*Relation : ManyToOne avec le demandeur (`user_id`, ON DELETE CASCADE).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`)
* `token` (VARCHAR(255), Unique) : Token public d'identification
* `is_active` (TINYINT/BOOLEAN) : Demande active ou clôturée
* `force_real_name` (TINYINT/BOOLEAN) : Si vrai, impose l'identité réelle au répondant
* `created_at` (DATETIME) : Date de création
* `updated_at` (DATETIME) : Date de modification

### C. Réponses de l'entourage (`community_feedback`)
*Relation : ManyToOne avec le destinataire de l'avis (`user_id`, ON DELETE CASCADE) et la demande d'origine (`feedback_request_id`, ON DELETE CASCADE).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`)
* `feedback_request_id` (INT, FK vers `feedback_request`)
* `answers` (TEXT, Nullable) : JSON contenant les réponses aux questions structurées
* `relationship` (VARCHAR(255), Nullable) : Relation déclarée
* `is_anonymous` (TINYINT/BOOLEAN) : Si vrai, l'avis est anonymisé
* `respondent_name` (VARCHAR(255), Nullable) : Nom déclaré du répondant
* `strengths` (TEXT, Nullable) : Points forts mentionnés
* `improvements` (TEXT, Nullable) : Axes d'améliorations
* `qualities` (TEXT, Nullable) : Qualités
* `created_at` (DATETIME) : Date de création

### D. Questions de feedback (`feedback_question`)
*Référentiel des questions des formulaires.*
* `id` (INT, PK AUTO_INCREMENT)
* `label_fr` (VARCHAR(255)) : Libellé en français
* `label_en` (VARCHAR(255)) : Libellé en anglais
* `placeholder_fr` (VARCHAR(255), Nullable)
* `placeholder_en` (VARCHAR(255), Nullable)
* `type` (VARCHAR(50)) : Type de champ (ex: `text`, `select`)
* `options` (TEXT, Nullable) : Options sous format JSON
* `sort_order` (INT) : Index d'ordonnancement (nommé `sort_order` pour éviter le mot réservé SQL `order`)
* `is_active` (TINYINT/BOOLEAN)
* `is_mandatory` (TINYINT/BOOLEAN)
* `created_at` (DATETIME) : Date de création
* `updated_at` (DATETIME) : Date de modification

### E. Remarques sur l'application (`app_feedback`)
*Relation : ManyToOne avec l'auteur (`user_id`, ON DELETE CASCADE).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`)
* `content` (TEXT) : Contenu de la remarque
* `category` (VARCHAR(50)) : Catégorie (ex: `BUG`, `IMPROVEMENT`)
* `status` (VARCHAR(50)) : Statut du rapport (ex: `PENDING`, `RESOLVED`)
* `created_at` (DATETIME) : Date du rapport

---

## 17. Structure détaillée de "Sessions & Télémétrie" (Vague 14)

Ce module permet de suivre l'historique des connexions de l'utilisateur, l'analyse d'activité de session, et la télémétrie des événements d'utilisation (logs analytiques de clics, écrans ou fonctionnalités).

### A. Sessions Utilisateurs (`user_session`)
*Relation : ManyToOne avec l'utilisateur (`user_id`, ON DELETE CASCADE).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`)
* `started_at` (DATETIME) : Début de la session
* `last_active_at` (DATETIME) : Dernière activité constatée
* `duration_minutes` (INT) : Durée de session en minutes
* `ip_address` (VARCHAR(50), Nullable) : Adresse IP
* `country` (VARCHAR(100), Nullable) : Pays
* `city` (VARCHAR(100), Nullable) : Ville
* `platform` (VARCHAR(50), Nullable) : Plateforme d'accès (Web, iOS, Android, etc.)
* `browser` (VARCHAR(100), Nullable) : Navigateur web utilisé
* `device` (VARCHAR(100), Nullable) : Appareil
* `os_version` (VARCHAR(50), Nullable) : Version du système d'exploitation

### B. Événements de télémétrie (`telemetry_event`)
*Relation : ManyToOne avec l'utilisateur (`user_id`, ON DELETE CASCADE).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`)
* `event_type` (VARCHAR(100)) : Type d'événement (ex: `CLICK_GROWTH_GOAL`, `COMPLETE_HABIT`)
* `event_data` (LONGTEXT, Nullable) : Métadonnées associées de l'événement (format JSON stringifié)
* `created_at` (DATETIME) : Date de l'événement
* `platform` (VARCHAR(50), Nullable) : Plateforme d'accès (Web, iOS, Android, etc.)

---

## 18. Structure détaillée de "Config & Contenus" (Vague 15)

Ce module gère les configurations globales, les modèles d'e-mails dynamiques, et le catalogue de citations inspirantes affichées sur le tableau de bord.

### A. Citations inspirantes (`quote`)
* `id` (INT, PK AUTO_INCREMENT)
* `content` (LONGTEXT) : Texte de la citation
* `author` (VARCHAR(255), Nullable) : Auteur de la citation
* `category` (VARCHAR(100)) : Thématique (ex: `MOTIVATION`)
* `display_date` (DATETIME, Nullable) : Date d'affichage prévue
* `is_active` (TINYINT/BOOLEAN) : Indicateur d'état actif
* `created_at` (DATETIME)
* `updated_at` (DATETIME)

### B. Modèles d'emails (`email_template`)
* `id` (INT, PK AUTO_INCREMENT)
* `type` (VARCHAR(100), Unique) : Identifiant unique du type d'e-mail (ex: `welcome`)
* `subject` (VARCHAR(255)) : Objet du courriel
* `html_body` (LONGTEXT) : Contenu HTML du courriel
* `text_body` (LONGTEXT, Nullable) : Contenu texte alternatif
* `enabled` (TINYINT/BOOLEAN) : État d'activation
* `created_at` (DATETIME)
* `updated_at` (DATETIME)

### C. Configuration de l'application (`app_config`)
* `id` (VARCHAR(50), PK) : Clé unique (valeur fixe `'default'`)
* `pro_monthly_price` (DOUBLE PRECISION) : Prix mensuel de l'abonnement PRO
* `pro_yearly_price` (DOUBLE PRECISION) : Prix annuel de l'abonnement PRO
* `trial_duration_days` (INT) : Nombre de jours d'essai gratuit
* `max_growth_goals_free` (INT) : Objectifs de croissance max autorisés en gratuit
* `max_habits_free` (INT) : Routines max autorisées en gratuit
* `max_connections_free` (INT) : Connexions max autorisées en gratuit
* `max_tasks_per_day_free` (INT) : Tâches par jour max autorisées en gratuit
* `max_values_free` (INT) : Valeurs max autorisées en gratuit
* `max_goals_free` (INT) : Objectifs de vie max autorisés en gratuit
* `max_challenges_free` (INT) : Défis max autorisés en gratuit
* `enable_print_free` (TINYINT/BOOLEAN) : Autoriser l'impression en gratuit
* `enable_map_free` (TINYINT/BOOLEAN) : Autoriser la cartographie en gratuit
* `enable_analytics_free` (TINYINT/BOOLEAN) : Autoriser l'analytique en gratuit
* `enable_monthly_reviews_free` (TINYINT/BOOLEAN) : Autoriser les revues mensuelles en gratuit
* `enable_plan_history_free` (TINYINT/BOOLEAN) : Autoriser l'historique de plan en gratuit
* `enable_month_year_view_free` (TINYINT/BOOLEAN) : Autoriser la vue mensuelle/annuelle en gratuit
* `enable_advanced_export_free` (TINYINT/BOOLEAN) : Autoriser l'export avancé en gratuit
* `enable_hygiene_detailed_free` (TINYINT/BOOLEAN) : Autoriser l'hygiène détaillée en gratuit
* `enable_coherence_analysis_free` (TINYINT/BOOLEAN) : Autoriser l'analyse de cohérence en gratuit
* `enable_advanced_comments_free` (TINYINT/BOOLEAN) : Autoriser les commentaires avancés en gratuit
* `enable_challenges_free` (TINYINT/BOOLEAN) : Autoriser les défis en gratuit
* `enable_discovery_free` (TINYINT/BOOLEAN) : Autoriser la découverte en gratuit
* `enable_journal_free` (TINYINT/BOOLEAN) : Autoriser le journal en gratuit
* `enable_growth_free` (TINYINT/BOOLEAN) : Autoriser la croissance en gratuit
* `enable_hygiene_free` (TINYINT/BOOLEAN) : Autoriser l'hygiène en gratuit
* `enable_contribution_free` (TINYINT/BOOLEAN) : Autoriser les contributions en gratuit
* `enable_planner_free` (TINYINT/BOOLEAN) : Autoriser le planificateur en gratuit
* `enable_habits_free` (TINYINT/BOOLEAN) : Autoriser les habitudes en gratuit
* `enable_social_free` (TINYINT/BOOLEAN) : Autoriser les fonctionnalités sociales en gratuit
* `enable_coaching_free` (TINYINT/BOOLEAN) : Autoriser le coaching en gratuit
* `enable_method_free` (TINYINT/BOOLEAN) : Autoriser la méthode en gratuit
* `demo_password` (VARCHAR(255)) : Mot de passe de démonstration
* `resend_from_email` (VARCHAR(255), Nullable) : Adresse email d'envoi Resend
* `resend_from_name` (VARCHAR(255), Nullable) : Nom d'envoi Resend
* `resend_api_key` (VARCHAR(255), Nullable) : Clé API Resend
* `updated_at` (DATETIME)

---

## 19. Structure détaillée des "Éléments Métiers Additionnels" (Vague 16)

Ce module regroupe les lois et principes directeurs, l'équilibre intérieur de l'utilisateur, et les liens de partage de plans.

### A. Principes directeurs (`principle`)
*Relation : ManyToOne avec l'utilisateur (`user_id`, ON DELETE CASCADE).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`)
* `title` (VARCHAR(255)) : Titre du principe ou de la loi
* `description` (LONGTEXT, Nullable) : Descriptif détaillé ou explication
* `pillar` (VARCHAR(50), Nullable) : Pilier associé (`CROISSANCE`, `DEVELOPPEMENT`, `CONTRIBUTION`, `DISCIPLINE`)
* `created_at` (DATETIME)

### B. Équilibre intérieur (`internal_balance`)
*Relation : ManyToOne avec l'utilisateur (`user_id`, ON DELETE CASCADE).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`)
* `type` (VARCHAR(100)) : Type d'élément d'équilibre (`CONVICTION`, `LIMITING_BELIEF`, `VALUE`, `TALENT`)
* `subtype` (VARCHAR(100), Nullable) : Sous-type éventuel
* `text` (LONGTEXT) : Énoncé ou description de la conviction/croyance/talent
* `created_at` (DATETIME)

### C. Liens de partage (`shared_link`)
*Relation : ManyToOne avec l'utilisateur (`user_id`, ON DELETE CASCADE).*
* `id` (INT, PK AUTO_INCREMENT)
* `user_id` (INT, FK vers `user`)
* `token` (VARCHAR(255), Unique) : Jeton d'accès public unique
* `created_at` (DATETIME)
* `expires_at` (DATETIME, Nullable) : Date d'expiration éventuelle du lien
* `active` (TINYINT/BOOLEAN) : Statut actif/inactif du lien de partage
* `scope` (VARCHAR(100)) : Portée d'accès (`FULL`, etc.)

---

## 20. Commandes de gestion de la BDD

Pour faire évoluer la base de données locale :

1. **Création d'une nouvelle entité** ou modification :
   ```bash
   php bin/console make:entity
   ```
2. **Génération de la migration** :
   ```bash
   php bin/console make:migration
   ```
3. **Application de la migration** :
   ```bash
   php bin/console doctrine:migrations:migrate
   ```
4. **Validation du schéma** :
   ```bash
   php bin/console doctrine:schema:validate
   ```


