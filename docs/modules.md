# Organisation par module

Le code de KIINI SAWA est rangé **par module métier**. Chaque module regroupe tout ce qui le concerne : contrôleurs, entités, dépôts, formulaires, services, templates, tests et documentation. Pour savoir ce que contient l'application, il suffit de lister `src/`.

## Carte des modules

| Module | Rôle | Entités | Routes | Interface |
|---|---|---|---|---|
| [Abonnement](modules/abonnement.md) | Offres Gratuit / PRO, essai, paiements manuels validés par l'admin, reçus, revenus | 2 | 8 | Oui |
| [Admin](modules/admin.md) | Back-office | 2 | 3 | Partielle |
| [Bilan](modules/bilan.md) | Bilans et revues : revue hebdomadaire sur les 4 plans, revue mensuelle, scores de croissance | 3 | 4 | Oui |
| [Coaching](modules/coaching.md) | Mentorat | 4 | 0 | Non |
| [Compte](modules/compte.md) | Identité et accès : inscription, vérification email, profil, RGPD, onboarding, pages légales | 4 | 18 | Oui |
| [Couple](modules/couple.md) | Espace couple : vision, valeurs, projets, thèmes de croissance, rituels, revue, rapport croisé ; offre Duo | 3 | 10 | Oui |
| [Dashboard](modules/dashboard.md) | Accueil (citation du jour, raison d'être, échéances) et administration des citations | 1 | 6 | Oui |
| [Defis](modules/defis.md) | Défis collectifs | 5 | 0 | Partielle |
| [Feedback](modules/feedback.md) | Feedback 360°, questionnaires et retours sur l'application | 5 | 0 | Non |
| [Journal](modules/journal.md) | Journal de réflexion et collections | 2 | 6 | Oui |
| [Listes](modules/listes.md) | Listes personnelles (UserList / ListItem) | 2 | 0 | Non |
| [Methode](modules/methode.md) | La méthode : chapitres du livre et encarts « Comprendre cette étape » | 0 | 2 | Oui |
| [Notification](modules/notification.md) | Centre de notifications, préférences, rappels par email | 3 | 5 | Oui |
| [Organisation](modules/organisation.md) | Offre B2B : églises, associations, entreprises ; places, rôles, accompagnateurs, engagement | 3 | 11 | Oui |
| [Personnalite](modules/personnalite.md) | Questionnaires psychométriques (IPIP-NEO, type, style, ennéagramme, dons), rapports PDF, fidélité ; tests d'aptitudes et d'hygiène | 5 | 14 | Oui |
| [PlanDHygiene](modules/plan_d_hygiene.md) | Plan d'Hygiène de vie | 3 | 3 | Oui |
| [PlanDIntendance](modules/plan_d_intendance.md) | Plan d'Intendance : ce que je gère (7 domaines, état, décision, compte rendu) | 2 | 7 | Oui |
| [PlanDeCroissance](modules/plan_de_croissance.md) | Plan de Croissance : Caractère & Conviction, Relation & Réseau, principes, export PDF | 7 | 6 | Oui |
| [PlanDeDeveloppement](modules/plan_de_developpement.md) | Plan de Développement : Compétence & Connaissance | 4 | 3 | Oui |
| [PlanDeTransmission](modules/plan_de_transmission.md) | Plan de Transmission : projets de legs | 4 | 3 | Oui |
| [PlanDeVie](modules/plan_de_vie.md) | Plan de Vie, assistant des plans, hub des stratégies, export PDF | 10 | 23 | Oui |
| [Planner](modules/planner.md) | Agenda | 1 | 8 | Oui |
| [Rituels](modules/rituels.md) | Habitudes et suivi quotidien (logs, ancrage) | 2 | 4 | Oui |
| [Shared](modules/shared.md) | Code transverse | 0 | 0 | — |
| [Social](modules/social.md) | Réseau de redevabilité | 3 | 6 | Partielle |
## Structure d'un module

```
src/<Module>/                 ← PascalCase, ex. PlanDeVie
├── Controller/               ← contrôleurs utilisateur
│   └── Admin/                ← écrans d'administration propres au module
├── Entity/                   ← entités Doctrine (mapping automatique)
├── Repository/
├── Form/
├── Service/                  ← logique métier
├── Enum/
├── Command/                  ← commandes console du module
└── Security/                 ← (module Compte uniquement)

templates/<module>/           ← snake_case, ex. plan_de_vie/
└── admin/                    ← templates d'administration du module
tests/<Module>/               ← même arborescence que src/<Module>
docs/modules/<module>.md      ← fiche du module
```

Espaces de noms : `App\<Module>\<Type>`, par exemple `App\PlanDeVie\Entity\LifeGoal` et `App\Tests\PlanDeVie\Controller\UserPlanControllerTest`.

Seuls les sous-dossiers utiles sont créés : un module sans formulaire n'a pas de dossier `Form/`.

## Ajouter un module

```bash
bin/console app:module:create PlanDeCarriere            # URL par défaut : /plan-de-carriere
bin/console app:module:create Finances --route=/mes-finances
bin/console cache:clear
```

La commande crée :
- `src/<Module>/` avec un contrôleur protégé (`ROLE_USER`) ;
- un template `templates/<module>/index.html.twig` ;
- un test fonctionnel (accès anonyme refusé, page affichée une fois connecté) ;
- la fiche `docs/modules/<module>.md`.

## Ajouter une entité à un module

```bash
bin/console app:module:entity PlanDeCarriere Objectif
bin/console cache:clear
bin/console make:entity '\App\PlanDeCarriere\Entity\Objectif'   # ajout interactif des champs
bin/console make:migration
```

> ⚠️ Ne pas créer une nouvelle entité directement avec `make:entity` : MakerBundle range alors le dépôt hors du module. `app:module:entity` crée l'entité et son dépôt au bon endroit ; `make:entity` sert ensuite seulement à ajouter des champs.

Le mapping Doctrine est automatique : tout dossier `src/<Module>/Entity` est détecté par `config/packages/doctrine_modules.php`. Il n'y a rien à déclarer, il faut seulement vider le cache après la création du dossier.

## Checklist d'un nouveau module

- [ ] Module créé avec `app:module:create`
- [ ] Fiche `docs/modules/<module>.md` complétée (rôle, statut, entités)
- [ ] Ligne ajoutée dans la carte ci-dessus
- [ ] Lien ajouté dans le menu (`templates/base.html.twig`)
- [ ] Contrôle d'accès vérifié (`#[IsGranted]` sur le contrôleur)
- [ ] Tests au vert (`bin/phpunit tests/<Module>`)

## Règles de dépendance

- Un module peut utiliser les entités d'un autre module (relations Doctrine), par exemple `Comment` (Social) → `LifeGoal` (PlanDeVie).
- La logique métier d'un module reste dans **son** dossier `Service/`. Un autre module l'appelle via ce service, sans dupliquer le code.
- Le code commun à plusieurs modules va dans `Shared/`.

## Dette connue (lot 3 de la feuille de route)

- `PlanDeVie/Controller/UserPlanController` porte aussi le wizard global et les vues des 4 stratégies. Il sera découpé : chaque plan aura son contrôleur dans son module.
- Les modules Coaching, Feedback, Listes, Notification et Abonnement n'ont que leurs entités, sans aucun écran.
