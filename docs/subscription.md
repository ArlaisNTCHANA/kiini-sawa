# Système d'Abonnement (Billing & Subscriptions)

> ⚠️ **État au 16/11/2026** : pas de Stripe pour l'instant. Les paiements sont **manuels** (virement, Mobile Money) et validés dans l'administration ; voir `docs/modules/abonnement.md`. Les colonnes Stripe de `Subscription` sont gardées pour plus tard.


Ce document détaille le fonctionnement technique, le schéma de base de données et les processus métiers du système d'abonnement et de facturation de **KIINI SAWA**.

---

## 1. Vue d'Ensemble

Le modèle économique de KIINI SAWA repose sur un système d'abonnement multi-niveaux (SaaS) contrôlant l'accès aux fonctionnalités des 5 plans de vie et de développement. Par défaut, tout utilisateur enregistré bénéficie du niveau gratuit (`FREE`). L'accès aux fonctionnalités premium nécessite une mise à niveau vers les offres payantes (`PRO` ou `TEAM`).

Le système est conçu pour être indépendant de la plateforme de paiement (ex: Stripe, PayPal, etc.) au niveau de sa logique métier interne, tout en stockant les références nécessaires pour l'intégration avec Stripe.

---

## 2. Niveaux et États (Enums PHP)

Le système s'appuie sur deux énumérations PHP distinctes définies dans `src/Enum/` :

### A. Niveaux d'Abonnement (`SubscriptionTier`)
Défini dans `App\Enum\SubscriptionTier` :
* **`FREE`** : Accès de base, limitations sur la création d'objectifs, de jalons ou de routines.
* **`PRO`** : Accès individuel illimité à l'ensemble des 5 plans métiers.
* **`TEAM`** : Fonctionnalités Pro avec fonctionnalités collaboratives (mentorat, partage de plans, redevabilité).

### B. États de l'Abonnement (`SubscriptionStatus`)
Défini dans `App\Enum\SubscriptionStatus` :
* **`ACTIVE`** : Abonnement en cours et payé.
* **`TRIAL`** : Période d'essai actif.
* **`PAST_DUE`** : Échec de paiement, relance en cours (accès temporairement maintenu ou restreint selon les règles de grâce).
* **`CANCELED`** : Abonnement résilié par l'utilisateur (prend effet à la fin de la période de facturation en cours).
* **`EXPIRED`** : Abonnement expiré et non renouvelé (l'utilisateur repasse automatiquement en `FREE`).

---

## 3. Schéma de la Base de Données

Le système d'abonnement utilise deux entités principales : `Subscription` et `Transaction`.

### A. L'entité `Subscription`
Cette entité est liée en relation `OneToOne` avec l'utilisateur (`User`).

| Champ | Type | Description |
| :--- | :--- | :--- |
| `id` | INT (PK Auto) | Identifiant unique de l'abonnement. |
| `user_id` | INT (FK) | Relation OneToOne vers la table `user`. |
| `tier` | VARCHAR(50) | Niveau d'abonnement (`FREE`, `PRO`, `TEAM`), mappé sur l'enum `SubscriptionTier`. |
| `status` | VARCHAR(50) | État de l'abonnement, mappé sur l'enum `SubscriptionStatus`. |
| `trial_start_at` | DATETIME (Null) | Date de début de la période d'essai gratuit. |
| `trial_end_at` | DATETIME (Null) | Date de fin de la période d'essai gratuit. |
| `is_trial_used` | BOOLEAN | Indique si l'utilisateur a déjà consommé sa période d'essai (évite les abus). |
| `current_period_start` | DATETIME (Null) | Date de début de la période de facturation en cours. |
| `current_period_end` | DATETIME (Null) | Date de fin de la période de facturation en cours (échéance). |
| `cancel_at_period_end` | BOOLEAN | Si `true`, l'abonnement ne sera pas renouvelé à l'échéance. |
| `stripe_customer_id` | VARCHAR(255) (Null) | ID client unique généré par Stripe. |
| `stripe_subscription_id` | VARCHAR(255) (Null)| ID de l'abonnement actif dans Stripe. |
| `stripe_payment_method_id` | VARCHAR(255) (Null)| ID de la carte ou du moyen de paiement par défaut dans Stripe. |
| `created_at` | DATETIME | Date d'initialisation de l'abonnement. |
| `updated_at` | DATETIME | Date de dernière mise à jour de l'abonnement. |

### B. L'entité `Transaction`
Cette entité conserve l'historique complet des paiements effectués par l'utilisateur pour son abonnement.

| Champ | Type | Description |
| :--- | :--- | :--- |
| `id` | INT (PK Auto) | Identifiant unique de la transaction. |
| `subscription_id` | INT (FK) | Relation ManyToOne vers la table `subscription`. |
| `amount` | FLOAT / DOUBLE | Montant facturé (ex: `9.99`). |
| `currency` | VARCHAR(10) | Devise du paiement (par défaut `EUR`). |
| `status` | VARCHAR(50) | État de la transaction (ex: `succeeded`, `failed`, `pending`). |
| `description` | TEXT (Null) | Motif ou détails de la transaction (ex: "Abonnement PRO Mensuel"). |
| `stripe_invoice_id` | VARCHAR(255) (Null) | ID de facture généré par Stripe. |
| `created_at` | DATETIME | Date d'enregistrement du paiement. |

---

## 4. Cycle de Vie & Flux Métiers

### A. Création à l'inscription
Lorsqu'un utilisateur crée son compte :
1. Une entité `User` est créée.
2. Une entité `Subscription` est automatiquement instanciée avec le tier `SubscriptionTier::FREE` et l'état `SubscriptionStatus::ACTIVE`.
3. Cette liaison automatique est déclenchée soit par les fixtures, par les commandes de création (ex: `CreateUserCommand`), ou via un Event Listener Symfony sur l'événement d'inscription.

### B. Gestion des restrictions d'accès (Paywall)
Dans l'application Symfony, les vérifications d'accès premium se font au niveau des contrôleurs ou des services via le système d'autorisation de Symfony (Voters ou vérification directe) :
```php
$subscription = $user->getSubscription();

if ($subscription->getTier() === SubscriptionTier::FREE) {
    // Appliquer les limites du compte gratuit (ex : maximum 3 objectifs de vie)
    if ($lifePlan->getLifeGoals()->count() >= 3) {
        throw new AccessDeniedException("Veuillez passer à l'abonnement PRO pour ajouter d'autres objectifs.");
    }
}
```

### C. Flux de paiement & Synchronisation Stripe
Lorsqu'un utilisateur souscrit à une offre payante :
1. L'application crée une session Stripe Checkout.
2. Une fois le paiement validé, Stripe envoie un webhook à l'application (`customer.subscription.updated` ou `invoice.payment_succeeded`).
3. Le contrôleur de Webhook de l'application intercepte l'événement, identifie la `Subscription` correspondante via `stripe_customer_id` ou `stripe_subscription_id`, et met à jour les champs :
   - `tier` (ex: `PRO`)
   - `status` (ex: `ACTIVE`)
   - `current_period_start` et `current_period_end`
4. Une nouvelle entité `Transaction` est insérée en base de données pour enregistrer le reçu du paiement.

---

## 5. Commandes Utiles (Développement)

Pour tester ou manipuler manuellement les abonnements en environnement de développement :

* **Créer un utilisateur de test avec un abonnement de base** :
  ```bash
  php bin/console app:create-user admin@kiinisawa.com password123 --role=ROLE_ADMIN
  ```
  *Cette commande crée automatiquement l'utilisateur et lui associe un abonnement `FREE` actif par défaut.*
