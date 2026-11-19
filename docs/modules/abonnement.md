# Module Abonnement

**Interface utilisateur** : Oui (`/abonnement`, administration `/admin/paiements` et `/admin/utilisateurs`)

## Rôle

Offres Gratuit / PRO, essai, paiements manuels (virement, Mobile Money, espèces) validés par l'administration, reçus, revenus.

## Offres (réglables dans Administration › Configuration)

| | Gratuit | PRO |
|---|---|---|
| Plan de Vie complet et analyse de cohérence, journal, planner, revue de la semaine, méthode | ✓ | ✓ |
| Rituels / partenaires | 3 / 1 (réglable) | Illimités |
| Tests de personnalité | **2 tests complets** : DISC et Dons spirituels (réglable : `AppConfig::freeTests`, Admin › Configuration) | Les 6 tests |
| Rapports PDF des tests, lectures croisées, « Mon profil KIINI SAWA » | — | ✓ |
| Plans de Croissance, Développement, Transmission, Hygiène ; carte ; PDF ; revue du mois ; historique | — (chaque fonction peut être rendue gratuite) | ✓ |

Prix par défaut : 9,99 € ou 3 000 FCFA par mois, 79,99 € ou 25 000 FCFA par an. Essai PRO de 14 jours à l'inscription.

## Dans le code

- `Entitlements` : `isPro()`, `can($user, 'map')`, `require()` (redirige vers `/abonnement?fonction=…`), `requireBelowLimit()`, `startTrial()`.
- Tests : `canTakeTest($user, $slug)`, `requireTest()`, `freeTests()`, `firstFreeTest()` ; gabarits `can_take_test('disc')`, `free_tests()`. **Les résultats déjà obtenus restent consultables** (seul le passage ou le repassage d'un test PRO est bloqué), conformément à la promesse « vos données sont conservées ». Les auto-évaluations (Équilibre de Vie, Hygiène de Vie) restent gratuites.
- Gabarits : `is_pro()`, `can('map')`, `is_trialing()`, `pro_until()`, filtre `|money('XAF')`.
- `SubscriptionManager` : `declare()`, `validate()`, `reject()`, `gift()`. La validation attribue un numéro de reçu `KS-AAAA-00001` et prolonge PRO à partir de la fin actuelle (essai compris : aucun jour perdu).
- Rappels : fin d'essai à J-3 et fin d'abonnement à J-7 (`app:reminders:send`), car les paiements manuels ne se renouvellent pas.

## Parcours d'un paiement manuel

1. Le client choisit sa formule et sa devise dans `/abonnement`, paie selon les consignes (configurées par l'administrateur) puis déclare sa référence.
2. Les administrateurs reçoivent une notification (et un email à l'adresse d'alerte si elle est renseignée).
3. Dans `/admin/paiements`, l'administrateur vérifie son compte et valide (ou refuse avec un motif).
4. Le client est prévenu, PRO est actif, le reçu PDF est téléchargeable.

## Paiement en ligne : à brancher quand la société existera

Le parcours de validation est déjà le bon : un fournisseur en ligne appellera `SubscriptionManager::validate()` depuis son webhook au lieu de l'administrateur.

| Besoin | Fournisseur possible | Condition |
|---|---|---|
| Carte bancaire en euros | Stripe Checkout | Société immatriculée dans un pays couvert (France / UE…) |
| Mobile Money et carte en FCFA | CinetPay, Flutterwave, PayDunya, Notch Pay… | Compte marchand, souvent société locale |

À prévoir alors : enregistrement du paiement en `PENDING` avec l'identifiant du fournisseur, webhook signé qui valide, page de retour, et, pour Stripe, renouvellement automatique (`cancelAtPeriodEnd = false`).

## Avant la mise en ligne

- Renseigner les coordonnées de virement et/ou de Mobile Money dans la configuration (sinon le paiement est masqué).
- Compléter l'en-tête et la mention de TVA du reçu (`templates/abonnement/receipt.html.twig`).
