# Documentation Technique - KIINI SAWA

Bienvenue sur le portail de documentation technique interne du projet de migration **KIINI SAWA**. 

Ce portail est accessible directement depuis le back-end de l'application et compilé à la volée à partir des fichiers Markdown situés dans le dossier `docs/` à la racine du projet.

---

## Sommaire de la Documentation

1. **[Architecture Modulaire & 5 Plans](architecture.md)**
   * Analyse de la structure modulaire et séparation fonctionnelle des 5 plans.
2. **[Structure de la Base de Données (MySQL)](database.md)**
   * Détail des entités, relations, et les tables des 5 plans fondamentaux.
3. **[Système d'Abonnement & Facturation](subscription.md)**
   * Gestion du modèle de facturation, des niveaux d'abonnement (tiers), des transactions et de l'intégration Stripe.
4. **[Architecture des API (Mobile)](api.md)**
   * Spécifications techniques pour le dialogue avec l'application mobile.
5. **[Architecture & Stratégie d'Administration](admin.md)**
   * Comparatif, stratégie d'administration hybride (Symfony UX & EasyAdmin) et gestion des assets.
6. **[Organisation par module](modules.md)**
   * Carte des modules, structure des dossiers, ajout d'un module ou d'une entité.
7. **[Audit technique & fonctionnel — novembre 2026](audit-2026-11.md)**
   * État des lieux, écosystème, complétude de la migration, feuille de route V1.
8. **[Plan complet V1 — produit, cohérence, vente et technique](plan-v1.md)**
   * Parité avec la version précédente, cohérence de l'offre, vendabilité, phases et décisions.
9. **[Dépendances JavaScript](dependances-front.md)**
   * Bibliothèques servies par l'application, versions, mise à jour.
10. [Mise en ligne](mise-en-ligne.md) : choix à faire, variables, déploiement, tâches planifiées, sauvegardes, contrôle `app:launch:check`.
