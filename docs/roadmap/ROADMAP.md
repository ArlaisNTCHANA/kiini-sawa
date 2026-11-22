# Feuille de Route Produit — KIINI SAWA (Cap 1M € ARR)

* **Dernière mise à jour** : Novembre 2026
* **Vision** : Faire de KIINI SAWA un **Sanctuaire de Croissance 360** — calme, structuré autour des 6 plans de la Méthode, au service de l'individu, du couple, de la famille et des communautés.

---

## 1. Principes Directeurs (Garde-fous Produit & UX)

1. **Un sanctuaire reste calme** : Pas d'usine à gaz. Les espaces dédiés (Couple, Organisation, Famille) restent masqués tant que l'utilisateur ne les active pas (**affichage progressif**).
2. **Fidélité absolue aux 6 plans** : Chaque nouvelle fonctionnalité doit servir au moins un des 6 plans (Vie, Croissance, Développement, Hygiène, Intendance, Transmission), jamais s'y superposer comme un gadget.
3. **L'humain avant la machine** : La technologie (audio, notifications, IA) sert l'introspection, la responsabilité personnelle et la relation humaine (conjoint, partenaire de redevabilité, accompagnateur) — elle ne les remplace jamais.
4. **Architecture modulaire & maîtrisée** : S'appuyer sur le socle existant (Symfony + MySQL + API `/api/mobile/v1` + Expo React Native) et externaliser les médias lourds (audio) sur stockage objet dédié (ex. Cloudflare R2).

---

## 2. Trajectoire Économique Cible (1 000 000 € / an)

| Segment | Modèle | Cible indicative | Potentiel annuel |
| :--- | :--- | :--- | :--- |
| **Communautés, Églises & Coachs (B2B2C)** | Licences groupées (`Organisation`) & Accompagnateurs | ~150 communautés / réseaux | **500 000 €** |
| **Foyers (Couple & Famille)** | Abonnements Duo (119,99 €) & Famille (~159 €) | ~2 200 foyers | **300 000 €** |
| **Individuel (PRO & PRO+ Mentor)** | Abonnements annuels (79,99 € à 150 €) | ~2 000 – 2 500 membres | **200 000 €** |

---

## 3. Catalogue des Releases à Venir

### Release V1.1 — Le Sanctuaire du Couple 360 *(Spécifié)*
* **Statut** : En Roadmap — Architecture validée
* **Spécification technique** : [RFC-001 : Sanctuaire de Croissance 360 (Espace Couple V2)](RFC-001-sanctuaire-couple-360.md)
* **Objectif** : Transformer l'abonnement Duo en une « Maison commune » (L'Autel, Le Jardin des Défis, L'Atelier des Projets, Le Salon d'intimité) avec suivi quotidien croisé et notifications bienveillantes.
* **Levier** : Viralité naturelle (1 abonné = 2 utilisateurs), forte justification du panier Duo.

---

### Release V1.2 — Académie Pratique, Parcours Communautés & Sanctuaire Audio
* **Statut** : Planifié

#### A. L'Académie Pratique reliée aux 6 Plans *(Spécifié)*
* **Spécification technique** : [RFC-002 : L'Académie Pratique KIINI SAWA](RFC-002-academie-pratique.md)
* **Concept** : Zéro parcours certifiant théorique ; chaque leçon pratique se termine par un exercice qui **alimente directement l'un des 6 plans** (ou les Rituels / Journal / Couple) via `AcademyPlanBridge`, résolvant ainsi la page blanche.

#### B. Studio d'Accompagnement (Églises, Leaders & Coachs)
* **Socle existant** : Modules `Organisation`, `Coaching`, `Defis`, `Social`.
* **Concept** : Permettre à un leader (pasteur, responsable, coach certifié) de lancer un parcours collectif de 21 ou 40 jours (ex. *« Préparation au mariage »*, *« 40 jours d'alignement »*, *« Intendance & Finances »*) suivi dans l'app mobile par toute sa communauté, avec suivi d'engagement agrégé (zéro accès aux journaux privés).
* **Levier** : Acquisition de masse (B2B2C), moteur principal vers le 1M €.

#### C. Sanctuaire Audio (Capsules rituelles de 3 à 7 min)
* **Socle existant** : Module `Media` + Expo (`expo-audio`).
* **Concept** : Capsules courtes dédiées aux rituels KIINI SAWA (alignement du matin, gratitude du soir, préparation de la revue hebdomadaire, méditation guidée en couple).
* **Garde-fou** : Rester strictement lié à la Méthode (ne pas devenir une app de relaxation générique). Héberger les fichiers audio sur stockage objet externe (Cloudflare R2).
* **Levier** : Rétention quotidienne sur mobile.

---

### Release V2.0 — Mentor IA & Livre de Famille *(À spécifier — RFC-004 / RFC-005)*
* **Statut** : Vision long terme

#### A. Le Mentor IA KIINI SAWA *(Palier PRO+)*
* **Socle existant** : 5 tests de personnalité, 6 plans, revues hebdomadaires et mensuelles.
* **Concept** : Un miroir discret formé exclusivement sur la Méthode KIINI SAWA, capable de générer des synthèses mensuelles narratives personnalisées et d'éclairer les dynamiques de couple à partir des profils croisés.
* **Garde-fous** : Consentement explicite, anonymisation des données avant appel API (RGPD), zéro entraînement sur les données utilisateurs ; l'IA pose des questions et éclaire, sans jamais se substituer à l'humain.

#### B. Livre de Famille & Capsules Temporelles *(Offre Famille)*
* **Socle existant** : `PlanDeTransmission`, `Couple`, `Journal`, tâches `cron`.
* **Concept** : Espace d'héritage familial (valeurs du foyer, mémoires, prières) incluant des capsules temporelles (lettres ou audios programmés pour s'ouvrir aux 18 ans ou au mariage d'un enfant).
* **Levier** : Rétention long terme quasi permanente.
