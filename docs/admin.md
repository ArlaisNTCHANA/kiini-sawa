# Architecture & Stratégie d'Administration

> ⚠️ **État au 16/11/2026** : EasyAdmin n'est pas utilisé ; l'administration est faite de contrôleurs Symfony et de gabarits `templates/admin/` (layout `admin/layout.html.twig`). Ce document garde la réflexion d'origine.


Pour le projet **KIINI SAWA**, le choix a été fait de ne pas se limiter à un générateur d'administration rigide comme EasyAdmin, mais d'adopter une **approche hybride moderne** qui concilie gain de temps et liberté totale sur l'expérience visuelle (UI/UX).

---

## 1. La Stratégie Hybride : Symfony UX & EasyAdmin

L'espace d'administration et de gestion est divisé en deux parties distinctes selon la cible et les besoins :

### A. Le Cœur Métier & Dashboard Utilisateur (Symfony UX)
Toutes les pages clés avec lesquelles les utilisateurs finaux interagissent, y compris les tableaux de bord, les plans de redevabilité, les graphes de progression et les outils d'alignement.

* **Technologie :** Twig Components, Live Components et Stimulus.
* **Style :** Design premium et dynamique sur mesure (Dark Mode, Glassmorphism, animations fluides) géré via Tailwind CSS et des composants UI autonomes.
* **Fonctionnement :** Rendu côté serveur ultra-rapide avec mise à jour réactive via requêtes Ajax asynchrones transparentes (gérées nativement par Live Components).

### B. L'Administration Technique & Data Froide (EasyAdmin 4)
Toutes les interfaces de gestion purement administratives destinées aux administrateurs ou développeurs (ex. gestion de la liste des pays, modification des modèles d'emails, gestion des abonnements, consultation des logs de télémétrie).

* **Technologie :** EasyAdmin 4.
* **Pourquoi ce choix ?** Développer des formulaires CRUD sur mesure pour des données de référence ou techniques prend du temps sans valeur ajoutée visuelle. EasyAdmin permet d'obtenir ces interfaces sécurisées et fonctionnelles en quelques minutes.
* **Sécurisation :** Cet espace est restreint aux comptes ayant le rôle `ROLE_ADMIN` ou supérieur.

---

## 2. Hébergement & Gestion des Assets sur Serveur PHP

Puisque l'application est hébergée sur un serveur PHP standard (sans daemon Node.js actif en production), la gestion des ressources CSS et JavaScript est optimisée :

* **Symfony AssetMapper :** Utilisation du composant natif de Symfony pour importer les bibliothèques JavaScript (comme Stimulus ou Chart.js) et le CSS sans nécessiter de compilation Node.js (`npm run build`) sur le serveur de production.
* **Tailwind CSS Standalone :** Utilisation de la version binaire standalone de Tailwind CSS intégrée via le bundle Symfony pour compiler le CSS en local ou pendant le workflow d'intégration continue (CI/CD), garantissant un déploiement fluide par simple transfert de fichiers (FTP/Rsync/Git).
