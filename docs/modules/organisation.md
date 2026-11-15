# Module Organisation

**Interface utilisateur** : Oui (`/organisation`, lien d'invitation `/rejoindre/{code}`, administration `/admin/organisations`)

## Rôle

Offre B2B pour les églises, associations, entreprises et coachs : un pack de places pour un an,
chaque membre devient PRO, les responsables suivent l'engagement, les accompagnateurs suivent leurs membres.

## Décisions (15/11/2026)

- **Confidentialité** : responsables et accompagnateurs voient des indicateurs d'engagement ; le contenu des plans seulement si le membre l'accepte (modifiable à tout moment) ; le journal jamais.
- **Prix** : pack de places pour un an (10, 25, 50, 100, 250), remise de volume de 0 à 35 %. Prix d'une place réglable dans la configuration (par défaut 30 € ou 10 000 FCFA par an).
- **Rôles** : responsable principal (OWNER : tout, y compris l'achat de places), responsable (LEADER : membres et rôles), accompagnateur (MENTOR : seulement les membres qui lui sont confiés), membre.

## Parcours

1. Un responsable crée l'espace (gratuit) ou l'administrateur le crée pour un pilote, avec des places offertes.
2. Il partage le lien d'invitation (copie, WhatsApp, emails groupés). Un visiteur sans compte est accueilli, s'inscrit, et revient automatiquement sur l'invitation.
3. Le membre rejoint en choisissant de partager ou non ses plans. Avec une licence valide, il est PRO.
4. Le responsable principal commande un pack et déclare son paiement ; l'administrateur valide dans `/admin/paiements` ; un reçu est émis (numérotation commune aux abonnements).

## Dans le code

- `OrganisationService` : création, `join()` (contrôle des places), `leave()`, `canSee()`, `canReadPlans()`, `stats()` (aucun contenu personnel), packs et commandes.
- `Entitlements::proUntil()` prend en compte la meilleure date entre l'abonnement personnel et les organisations.
- L'export RGPD ne suit pas les relations vers les autres membres (`UserDataExporter::SKIPPED_ASSOCIATIONS`).

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/Organisation/` |
| Templates | `templates/organisation/` |
| Tests | `tests/Organisation/` |
