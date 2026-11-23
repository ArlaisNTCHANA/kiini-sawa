# RFC-001 : Sanctuaire de Croissance 360 (Espace Couple V2)

* **Statut** : **Livré** sur le mobile (21/11/2026) et sur le web (23/11/2026 : `/couple/autel`, `/couple/defis`, signal depuis `/couple`). Écarts connus avec cette spécification : voir `docs/audit-coherence-ia-2026-11-23.md` (§ 4, RFC-001) : pas d'entité par jour pour les méditations, jours de défi non liés au calendrier, routes mobiles différentes de la section « Contrat d'API ».
* **Date de conception** : Novembre 2026
* **Cible** : Abonnements Duo (Mobile & Web)
* **Type de document** : Architecture Blueprint & Spécification Technique

---

## 1. Vision Métier & Concept « La Maison du Couple »

Transformer l'actuel espace Couple en un **Sanctuaire de Croissance 360**, conçu comme une **« Maison partagée »** pour les deux conjoints :
* **Isolation étanche** : Ce qui est individuel (journal, plans intimes) reste strictement privé.
* **Symétrie totale** : Aucun des deux conjoints n'a de droits supérieurs sur l'autre dans le sanctuaire.
* **Le Tiers-Lieu (« Le Nous »)** : Un espace chaleureux où le couple se retrouve pour méditer, bâtir leurs projets communs, relever des défis de couple et faire le point chaque mois.

---

## 2. Les 4 Piliers du Sanctuaire

1. **L'Autel (Spiritualité & Méditation partagée)** :
   - Verset et pensée du jour pour le couple.
   - Parcours de méditation/prière guidés à deux (ex. : 7 à 21 jours).
   - Intentions de prière et sujets d'accord commun.
2. **Le Jardin des Défis (Connexion émotionnelle & Habitudes)** :
   - Challenges à deux synchronisés (ex. : *« 7 jours d'écoute active »*, *« Défi gratitude conjugale »*, *« Déconnexion écrans après 21h »*).
   - Validation mutuelle quotidienne avec suivi de la flamme (*streak*).
   - Rituels sacrés du couple (rendez-vous hebdomadaire, balade, prière).
3. **L'Atelier (Vision & Projets communs)** :
   - Vision commune et verset fondateur du foyer.
   - Projets de vie à deux (achat de maison, budget/épargne, vacances, enfants) avec pourcentage d'avancement.
4. **Le Salon d'intimité (La Revue mensuelle & Profils)** :
   - Revue mensuelle guidée sur 10 thèmes de croissance (communication, finances, intimité, vie spirituelle...).
   - Rapport croisé des 2 profils de personnalités pour désamorcer les conflits et valoriser les complémentarités.

---

## 3. Architecture Technique

### Modèle de Données (Doctrine ORM)

* **`Couple`** : ajout du compteur de jours consécutifs `streakDays`.
* **`CoupleItem`** : extension des types (`kind`) avec `INTENTION`.
* **`CoupleChallenge`** & **`CoupleChallengeDay`** : suivi des défis de couple avec validation croisée des deux partenaires.
* **`CoupleMeditation`** & **`CoupleMeditationDay`** : suivi des parcours de méditation/lecture biblique à deux.

### Contrat d'API Mobile (`/api/mobile/v1/couple/...`)

* `GET /api/mobile/v1/couple/home` : Vue globale 360 du Sanctuaire en un appel (Verset, Défi actif, Méditation, Résumé Projets).
* `GET / POST /api/mobile/v1/couple/projects` : Gestion des projets communs.
* `GET / POST /api/mobile/v1/couple/challenges` : Inscription et validation quotidienne du défi.
* `GET / POST /api/mobile/v1/couple/meditations` : Suivi des étapes de méditation.
* `POST /api/mobile/v1/couple/ping` : Notification push instantanée au conjoint (*« ❤️ [Prénom] t'invite à la méditation ce soir »*).

### Arborescence Mobile (React Native / Expo)

* `app/couple/_layout.tsx` : Ambiance feutrée, prénoms et streak.
* `app/couple/index.tsx` : Le Hall d'entrée (vue 360).
* `app/couple/meditation/` : L'Autel.
* `app/couple/challenges/` : Le Jardin des défis.
* `app/couple/projets/` : L'Atelier des projets.
* `app/couple/revue/` : La revue mensuelle.

---

## 4. Jalons de Réalisation (Milestones)

* **Jalon 1 (Backend)** : Entités, migrations SQL, catalogue des défis et routes API.
* **Jalon 2 (Mobile)** : Hall d'entrée du Sanctuaire et gestion des projets.
* **Jalon 3 (Mobile)** : Écrans de défis, validation quotidienne et parcours de méditation.
* **Jalon 4 (Temps Réel)** : Notifications push interactives (Expo) et streak.
