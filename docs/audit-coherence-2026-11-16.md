# Audit de cohérence — 16 novembre 2026 (après les lots 1 à 6)

## Résultat

| Contrôle | Résultat |
|---|---|
| Liens et pages | ✅ 244 pages parcourues (visiteur, gratuit, PRO, administrateur) : aucun lien cassé, aucune erreur |
| Promesses de la page d'accueil / règles gratuit-PRO | ✅ conformes (3 rituels, 1 partenaire, tests, méthode, cohérence gratuits ; 4 plans, carte, PDF, revue du mois, historique en PRO ; essai 14 jours) |
| Vocabulaire (lexique) | ✅ pages de l'application ; corrigé : « planner » dans le tableau des offres |
| Espace couple | ⚠️ corrigé : le tableau disait « Avec Duo » alors qu'il suffit que les deux conjoints soient PRO (le Duo est la formule la moins chère) |
| Tableau des offres | corrigé : ajout de la ligne « Tous les tests » (promise sur la page d'accueil) |
| Documentation | ⚠️ corrigé : `api.md`, `subscription.md`, `admin.md`, `index.md` décrivaient des choses inexistantes (JWT, Stripe, EasyAdmin) ; avertissements ajoutés |
| Modules dormants | ⚠️ `Coaching`, `Defis`, `Feedback`, `Listes` : tables sans interface ; signalés dans la doc et `AGENTS.md` |
| Réglages inutilisés | ⚠️ 12 colonnes de `AppConfig` jamais lues (limites d'objectifs, de valeurs, de tâches, défis, coaching, clé Resend…) ; sans effet visible, à nettoyer plus tard |

## Incohérences qui restent (décisions ou actions du fondateur)

1. **Paiement** : la page d'accueil annonce « virement, Mobile Money » mais la page d'abonnement affiche « paiement pas encore ouvert » tant que les coordonnées ne sont pas saisies (Admin › Configuration).
2. **Pages légales** : 15 passages « [À COMPLÉTER] » liés à la structure juridique ; bandeau « Version de travail » affiché.
3. **Tâches planifiées** : à créer dans hPanel (rappels, sauvegarde) ; sans elles, les rappels de revue et de fin d'essai ne partent pas.
4. **Site vitrine** www.kiinisawa.com : aucun lien vers l'application, message différent (pas de mention chrétienne, « conseils personnalisés »), « © 2025 », contact différent.
5. **Application mobile native** : branchée sur l'ancienne version ; l'application installable (PWA) la remplace en attendant.
6. **Emails** : envoyés depuis bonjour@kiinisawa.com alors que le contact affiché est contact@kiinisawa.com ; prévoir une adresse de réponse unique.
7. **Signature de marque** : « Bâtis. Grandis. Transmets. » (brief média) à valider ; la page d'accueil dit « Plan de vie · Croissance · Transmission ».

## Fonctionnalités à fort impact (proposées)

Classées par effet sur l'objectif de 1 M€ (acquisition, activation, rétention, revenus) et par effort.

| # | Fonctionnalité | Pourquoi | Effort |
|---|---|---|---|
| 1 | **Défis collectifs** (21 jours de discipline, etc.) : inscription par lien, suivi quotidien dans les rituels, classement, badge et partage | Le moteur du média : chaque défi convertit l'audience en inscrits, puis en habitués. Le module `Defis` existe déjà côté données | 3–4 j |
| 2 | **Séquence d'accueil par email** (J0, J1, J3, J7, J12 avant la fin d'essai) via Brevo | Transforme l'inscrit en utilisateur actif puis en payant ; aujourd'hui seul le code de vérification part | 1–2 j |
| 3 | **Notifications sur le téléphone** (web push de l'application installable) : rituels, revue du dimanche | La rétention quotidienne ; fonctionne sur Android et iPhone (application installée) | 2–3 j |
| 4 | **Parrainage** : un lien personnel ; 1 mois PRO offert à chaque ami qui s'inscrit et vérifie son email | Croissance gratuite, très efficace dans les communautés (Églises, familles, WhatsApp) | 2 j |
| 5 | **Résultat de test partageable** (image « Mon style : Rayonnant » aux couleurs KIINI SAWA) | Viralité naturelle sur WhatsApp et Instagram ; chaque partage ramène des inscrits | 1–2 j |
| 6 | **Paiement Mobile Money et carte automatiques** (CinetPay, Kkiapay, Flutterwave ou Stripe selon le pays) | Supprime la validation manuelle, augmente la conversion ; **après l'immatriculation** | 3–5 j |
| 7 | **Accompagnant IA** (PRO) : aide à rédiger la vision, à transformer un rêve en objectifs Être/Faire/Avoir/Donner, prépare la revue de la semaine | Valeur perçue forte, argument PRO différenciant ; coût d'usage à maîtriser | 4–6 j |
| 8 | **Offre Églises enrichie** : cellules, parcours de groupe (plan de lecture + défi), rapport d'engagement pour le pasteur | Le levier B2B : une Église = des dizaines d'abonnés d'un coup | 4–5 j |
| 9 | **Méditation du jour** : verset + question + action, reliés au Plan de Vie, aussi envoyés sur la chaîne WhatsApp | Raison de revenir chaque jour ; contenu déjà produit pour le média | 1–2 j |
| 10 | **Studio média** (admin) : calendrier éditorial, fiches de contenus, statistiques des réseaux (YouTube, Meta, puis TikTok) à côté des inscrits | Piloter le média par les revenus ; prévu une fois le rythme de publication installé | 3–5 j |
| 11 | **Tableau de rétention** (admin) : actifs à J7, J30, J90 par canal et par cohorte | Savoir si l'on garde les gens, pas seulement si on les attire | 1 j |
| 12 | **Application native** reconnectée (App Store, Google Play) | Présence dans les boutiques, hors connexion, widgets ; après la structure juridique (compte d'organisation) | 2–3 sem. |

**Ordre recommandé** : 2 (accueil email) → 1 (défis) → 4 (parrainage) → 5 (partage de test) → 3 (notifications) → 11 (rétention),
puis 6 (paiement automatique) dès l'immatriculation, 8 (Églises) et 7 (IA) ensuite.
