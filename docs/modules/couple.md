# Module Couple

**Interface utilisateur** : Oui (`/couple`, invitation `/couple/rejoindre/{code}`, rapport `/couple/rapport`)

## Rôle

Un espace commun pour deux conjoints : vision et verset du couple, valeurs communes, projets communs
(Être / Faire / Avoir / Donner, échéance, avancement), thèmes de croissance (communication, vie spirituelle,
finances, intimité, parentalité, familles, temps, projets, santé, service), rituels de couple,
revue de couple mensuelle et rapport croisé des deux profils de personnalité.

## Règles

- **Accès** : les deux conjoints doivent être PRO. L'abonnement **Duo** (par défaut 119,99 € ou 39 000 FCFA par an) rend les deux PRO en un seul paiement, validé dans `/admin/paiements` (`SubscriptionManager`, période `DUO`).
- **Confidentialité** : les espaces personnels (plans, journal) restent privés. Le rapport croisé n'utilise les résultats de tests que si chacun l'accepte (modifiable dans les réglages).
- **Fermeture** : supprime l'espace commun, jamais les espaces personnels. La suppression du compte d'un conjoint ferme aussi l'espace.
- Un conjoint sans compte ouvre le lien, s'inscrit, et revient automatiquement sur l'invitation (`PendingInvitation::COUPLE_KEY`).

## Dans le code

- `CoupleService` : création, `join()`, `canUse()`, éléments communs, revue, `crossReport()` (Big Five, type, style, ennéagramme, avec des pistes quand les conjoints diffèrent).
- Entités : `Couple`, `CoupleItem` (VALUE, PROJECT, THEME, RITUAL), `CoupleReview` (une par mois).
