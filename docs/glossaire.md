# Référentiel de Termes (Glossaire) - KIINI SAWA

Ce document regroupe les termes et expressions par page de l'application.
La colonne **"Terme Corrigé"** est réservée à votre validation : si un terme UI ne convient pas, inscrivez votre correction dans cette colonne.

## 1. Page : Assistant Plan de Vie (Saisie Générale)
*Composant : `LifePlanWizard.tsx`*

| Terme UI Actuel | Terme Technique | Détails | Terme Corrigé (Votre Validation) |
| :--- | :--- | :--- | :--- |
| **But de Dieu pour ma vie** | `Purpose` | Étape 1. Raison d'être fondamentale. | ✅ |
| **Balance Interne** | `InternalBalance` | Étape 2. Section regroupant Convictions et Valeurs. | |
| **Convictions** | `Conviction` | Sous-section. Certitudes profondes. | |
| **Profonde (Dogmatique)** | `DOGMATIC` | Niveau de conviction. | |
| **Médiane (Raisonnée)** | `REASONED` | Niveau de conviction. | |
| **Légère (Circonstantielle)** | `CIRCUMSTANTIAL` | Niveau de conviction. | |
| **Valeurs** | `Value` | Sous-section. Principes moraux. | |
| **Réelle & Vécue** | `REAL` | Type de valeur. | |
| **Déclarée & Admirée** | `DECLARED` | Type de valeur. | |
| **Croyances Limitantes** | `LimitingBelief` | Sous-section. Pensées négatives à traiter. | |
| **Passions & Intérêts** | `Passions` | Étape 3. Centres d'intérêt. | |
| **Vision** | `Vision` | Étape 4. Projection future (5 ans). | |
| **Objectifs Spécifiques** | `SpecificGoals` | Étape 5. Buts concrets à atteindre. | ✅ |
| **Compétence & Connaissance** | `Skills (Competence)` | Étape 6. Axe de croissance "Savoir-faire". | |
| **Caractère & Conviction** | `Character` | Étape 7. Axe de croissance "Savoir-être". | |
| **Relation & Réseau** | `Relations` | Étape 8. Axe de croissance "Social". | |
| **Échéancier** | `Timeline` | Étape 9. Vue chronologique. | |

## 2. Page : Vue Plan de Vie (Affichage)
*Route : `/print/life-plan`, Composant : `PrintLifePlanPage.tsx`*

| Terme UI Actuel | Terme Technique | Détails | Terme Corrigé (Votre Validation) |
| :--- | :--- | :--- | :--- |
| **Plan de Vie** | `LifePlan` | Titre principal de la page. | |
| **Ma Balance Interne** | `InternalBalance` | Titre de section. | |
| **Mes Valeurs** | `Values` | Titre de bloc. | |
| **Mes Convictions** | `Convictions` | Titre de bloc. | |
| **Croyances Limitantes** | `LimitingBeliefs` | Titre de bloc (affiché en rouge). | |
| **Mes Passions & Intérêts** | `Passions` | Titre de section. | |
| **Ma Vision Personnelle** | `Vision` | Titre de section. | |
| **Mes Objectifs Spécifiques** | `SpecificGoals` | Titre de section. Cartes "OBJECTIF" / "PLAN DE VIE". | |
| **Compétence & Connaissance** | `Competence` | Titre de section (Liste simple). | |
| **Caractère & Conviction** | `Character` | Titre de section (Liste simple). | |
| **Relation & Réseau** | `Relation` | Titre de section (Liste détaillée par type). | |
| **Réseau d'orientation** | `ORIENTATION` | Type de réseau (Mentors). | |
| **Réseau de croissance** | `GROWTH` | Type de réseau (Pairs). | |
| **Réseau de soutien émotionnel** | `EMOTIONAL` | Type de réseau (Amis/Famille). | |
| **Réseau de responsabilité** | `ACCOUNTABILITY` | Type de réseau (Partenaires). | |
| **Réseau d'environnement** | `ENVIRONMENT` | Type de réseau (Cadre). | |
| **Mon Échéancier** | `Timeline` | Titre de section (Chronologie). | |

## 3. Page : Vue Plan de Croissance (Affichage)
*Route : `/print/growth-plan`, Composant : `PrintGrowthPlanPage.tsx`*

| Terme UI Actuel | Terme Technique | Détails | Terme Corrigé (Votre Validation) |
| :--- | :--- | :--- | :--- |
| **Plan de Croissance** | `GrowthPlan` | Titre principal de la page. | |
| **Le Pourquoi de la Croissance**| `Purpose` | Titre de section. Rappel du But de Dieu. | |
| **Lois de la Croissance** | `GrowthPrinciples` | Titre de section. Principes directeurs. | |
| **Objectifs de Croissance** | `GrowthGoals` | Titre de section. Cartes détaillées. | |
| **Compétence & Connaissance** | `COMPETENCE` | Label de catégorie sur la carte. | |
| **Caractère & Conviction** | `CHARACTER` | Label de catégorie sur la carte. | |
| **Relation & Réseau** | `RELATION` | Label de catégorie sur la carte. | |
| **Horizon** | `Deadline` | Date butoir ou fin de saison. | |
| **Origine** | `Source` | Lien vers l'objectif spécifique source. | |
| **Stratégie** | `Strategy` | Rubrique de la carte. Comment atteindre le but. | |
| **Système & Habitudes** | `System/Habits` | Rubrique de la carte. Actions récurrentes. | |
| **Échéancier de Croissance** | `GrowthTimeline` | Titre de section. Chronologie des objectifs de croissance. | |
| **Objectif de maîtrise** | `MasteryGoal` | Métrique par défaut si non définie. | |

## 4. Page : Tableau de Bord Social
*Route : `/social`, Composant : `SocialDashboard`*

| Terme UI Actuel | Terme Technique | Détails | Terme Corrigé (Votre Validation) |
| :--- | :--- | :--- | :--- |
| **Tableau de Bord** | `Dashboard` | Titre principal. | |
| **Mes Partenaires** | `Partners` | Liste des connexions acceptées. | |
| **En attente** | `Pending` | Statut d'invitation envoyée/reçue. | |
| **Suggestions** | `Suggestions` | Onglet ou section "Suggestions Reçues". | |
| **Accepter** | `Accept` | Action sur une suggestion. | |
| **Refuser** | `Reject` | Action sur une suggestion. | |

## 5. Fenêtre de Suggestion (Modale)
*Composant : `SuggestionModal.tsx`*

| Terme UI Actuel | Terme Technique | Détails | Terme Corrigé (Votre Validation) |
| :--- | :--- | :--- | :--- |
| **Faire une suggestion** | `SuggestGoal` | Titre de la fenêtre. | ✅ |
| **Titre de la suggestion** | `Title` | Champ de saisie. | |
| **Type** | `Category` | Sélecteur de catégorie. | |
| **Objectif Spécifique** | `GOAL` (Specific) | Catégorie : Objectif de vie. | ✅ |
| **Compétence** | `COMPETENCE` | Catégorie : Objectif de croissance. | |
| **Caractère** | `CHARACTER` | Catégorie : Objectif de croissance. | |
| **Pourquoi cette suggestion ?** | `Description` | Champ de motivation facultatif. | |
