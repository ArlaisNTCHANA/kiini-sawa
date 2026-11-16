# CHARTE DU PROJET KIINI SAWA
*Ce document sert de référence absolue pour toute intervention de l'IA sur le projet.*

## 1. NOMENCLATURE SACRÉE (NE PAS MODIFIER)
Les termes suivants ont été validés par l'utilisateur et ne doivent JAMAIS être reformulés, raccourcis ou "harmonisés" sans demande explicite.

### Réseaux de Soutien (Wizard Plan de Vie)
- **Réseau d'orientation** (et non "Orientation")
- **Réseau de croissance** (et non "Croissance")
- **Réseau de soutien** (et non "Soutien Émotionnel")
- **Réseau de responsabilité** (et non "Responsabilité")
- **Réseau d'environnement** (et non "Environnement")

### Actions UI
- Bouton : **"Ajouter"** (et non "Ajouter contact" ou autre)
- Titres Axes Croissance :
    - **"Compétence & Connaissance"** (et non "Compétence")
    - **"Caractère & Conviction"** (et non "Caractère")
    - **"Relation & Réseau"** (et non "Relation")

## 1bis. LEXIQUE DES 5 PLANS (décision du 15/11/2026)

Positionnement : **méthode chrétienne assumée**.

| Plan | Nom affiché | Nom court | URL cible | Module (code) | Axes / contenu |
|---|---|---|---|---|---|
| Vie | **Plan de Vie** | Vie | `/plan-de-vie` | `PlanDeVie` | Raison d'être (But de Dieu), Balance interne, Passions, Dons & Talents, Vision, Objectifs (BE/DO/HAVE/GIVE) |
| Croissance | **Plan de Croissance** | Croissance | `/plan-de-croissance` | `PlanDeCroissance` | **« Caractère & Conviction »**, **« Relation & Réseau »** |
| Développement | **Plan de Développement** | Développement | `/plan-de-developpement` | `PlanDeDeveloppement` | **« Compétence & Connaissance »** (déplacé depuis la Croissance), ressources d'apprentissage, carrière |
| Hygiène | **Plan d'Hygiène de Vie** | Hygiène | `/plan-d-hygiene` | `PlanDHygiene` | Domaines d'hygiène, bilans journaliers |
| Transmission | **Plan de Transmission** | Transmission | `/plan-de-transmission` | `PlanDeTransmission` | Legs, actions de transmission, dons de talents |

Termes **bannis** pour désigner ces plans : « Discipline », « Contribution ».
Les titres d'axes de la section 1 restent inchangés mot pour mot ; seul l'axe « Compétence & Connaissance » change de plan.

## 1ter. LEXIQUE DE L'INTERFACE (décision du 16/11/2026)

Validé par l'utilisateur lors de l'audit du 16/11/2026 : un seul mot par chose dans toute l'interface (Rituels, Agenda,
Partenaires, Mes tests, Mes stratégies, score de cohérence ; objectifs affichés **Être / Faire / Avoir / Donner**, codes
BE/DO/HAVE/GIVE réservés au stockage). Référence : `docs/lexique.md`. Ne pas revenir aux anciens termes.

## 2. RÈGLES D'OR DE DÉVELOPPEMENT (AI BEHAVIOR)

### Règle n°1 : Non-Régression Fonctionnelle Strict
**Ne jamais supprimer une fonctionnalité existante** lors d'une refonte visuelle.
- Si un champ (ex: *Formulation*, *Échéance*, *Suggestions*) existe dans la version N-1, il DOIT exister dans la version N.
- Tout nettoyage de code doit vérifier qu'il ne supprime pas une feature "silencieuse" (ex: import automatique des objectifs).

### Règle n°2 : Fidélité Textuelle
L'IA a interdiction de "lisser" ou "corriger" le style linguistique de l'utilisateur.
- Si l'utilisateur écrit * ÉCHÉANCE *, l'IA ne doit pas le changer en * Échéance (Optionnel) *.
- Se référer strictement aux textes existants avant de proposer une modification.

### Règle n°3 : Transparence des Données
Ne jamais exposer de données brutes (Valeurs Enum type `COGNITIVE`, `FINANCIAL`) à l'utilisateur.
- Si une donnée technique n'a pas de traduction élégante définie, elle doit être **masquée** plutôt qu'affichée en brut.

## 3. PROTOCOLE DE MODIFICATION
Avant tout changement majeur de UI (Wizard, Dashboard) :
1.  Lister ce qui est affiché actuellement.
2.  Garantir que 100% de ces éléments seront présents après modification.
3.  Si un élément semble "en trop", demander l'autorisation de le retirer AVANT de le faire.
