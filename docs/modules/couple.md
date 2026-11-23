# Module Couple

**Interface utilisateur** : Oui (`/couple`, invitation `/couple/rejoindre/{code}`, rapport `/couple/rapport`, Sanctuaire du Couple : `/couple/autel`, `/couple/defis`, signal `/couple/signal`)

## Rôle

Un espace commun pour deux conjoints : vision et verset du couple, valeurs communes, projets communs
(Être / Faire / Avoir / Donner, échéance, avancement), thèmes de croissance (communication, vie spirituelle,
finances, intimité, parentalité, familles, temps, projets, santé, service), rituels de couple,
revue de couple mensuelle et rapport croisé des deux profils de personnalité.

## Règles

- **Accès** : les deux conjoints doivent être PRO. L'abonnement **Duo** (par défaut 119,99 € ou 78 500 FCFA par an) rend les deux PRO en un seul paiement, validé dans `/admin/paiements` (`SubscriptionManager`, période `DUO`).
- **Sanctuaire du Couple** (RFC-001, sur le web et dans l'application mobile, même service `CoupleSanctuaryService`) : bloc « Notre Sanctuaire » en tête de `/couple` (parole du jour, flamme commune, les 4 pièces, signal au conjoint) ; **L'Autel** `/couple/autel` (méditations à deux, intentions de prière `CoupleItem` INTENTION) ; **Le Jardin des défis** `/couple/defis` (le jour suivant s'ouvre quand les deux ont validé) ; **L'Atelier** (vision, verset, projets) et **Le Salon** (thèmes, rituels, revue du mois) sont les sections existantes de `/couple`. Contrôleur web : `CoupleSanctuaryController` ; textes dans `CoupleSanctuaryCatalog`.
- **Rythme (décision du fondateur, 23/11/2026)** : un jour de défi et une étape de méditation par date du calendrier ; une étape de méditation n'avance que quand les deux conjoints l'ont validée. Message discret : « La suite s'ouvre demain. Ici, c'est la régularité qui fait grandir, pas la vitesse. » (`CoupleSanctuaryService::RHYTHM_MESSAGE`).
- **Temps de retraite** (`CoupleRetreat`) : proposé par un conjoint, effectif seulement après confirmation de l'autre ; dates choisies par le couple, date de fin obligatoire (se termine d'elle-même), pas de limite de durée ni de fréquence ; l'un ou l'autre peut l'arrêter. Pendant ces dates, plusieurs jours de défi et étapes de méditation le même jour. Historique visible (jours de défi vécus pendant chaque retraite). La flamme compte toujours un jour par date. Spécification : [RFC-001](../roadmap/RFC-001-sanctuaire-couple-360.md).
- **Confidentialité** : les espaces personnels (plans, journal) restent privés. Le rapport croisé n'utilise les résultats de tests que si chacun l'accepte (modifiable dans les réglages).
- **Fermeture** : supprime l'espace commun, jamais les espaces personnels. La suppression du compte d'un conjoint ferme aussi l'espace.
- Un conjoint sans compte ouvre le lien, s'inscrit, et revient automatiquement sur l'invitation (`PendingInvitation::COUPLE_KEY`).

## Dans le code

- `CoupleService` : création, `join()`, `canUse()`, éléments communs, revue, `crossReport()` (Big Five, type, style, ennéagramme, avec des pistes quand les conjoints diffèrent).
- Entités : `Couple`, `CoupleItem` (VALUE, PROJECT, THEME, RITUAL), `CoupleReview` (une par mois).
