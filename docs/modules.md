# Organisation par module

Le code de KIINI SAWA est rangé **par module métier**. Chaque module regroupe tout ce qui le concerne : contrôleurs, entités, dépôts, formulaires, services, templates, tests et documentation. Pour savoir ce que contient l'application, il suffit de lister `src/`.

## Carte des modules

| Module | Rôle | Entités | Routes | Interface |
|---|---|---|---|---|
| [Abonnement](modules/abonnement.md) | Abonnements et transactions (Stripe à venir) | 2 | 0 | Non |
| [Admin](modules/admin.md) | Back-office | 2 | 3 | Partielle |
| [Bilan](modules/bilan.md) | Bilans et revues (scores de croissance, revue mensuelle, check-ins) | 2 | 2 | Partielle |
| [Coaching](modules/coaching.md) | Mentorat | 4 | 0 | Non |
| [Compte](modules/compte.md) | Identité et accès | 3 | 3 | Partielle |
| [Dashboard](modules/dashboard.md) | Tableau de bord d'accueil et citations | 1 | 2 | Oui |
| [Defis](modules/defis.md) | Défis collectifs | 5 | 0 | Partielle |
| [Feedback](modules/feedback.md) | Feedback 360°, questionnaires et retours sur l'application | 5 | 0 | Non |
| [Journal](modules/journal.md) | Journal de réflexion et collections | 2 | 6 | Oui |
| [Listes](modules/listes.md) | Listes personnelles (UserList / ListItem) | 2 | 0 | Non |
| [Notification](modules/notification.md) | Notifications, préférences, modèles d'email | 3 | 0 | Non |
| [Personnalite](modules/personnalite.md) | Tests de personnalité (DISC, MBTI, Ennéagramme, Big 5…), calcul des scores et administration du référentiel | 4 | 11 | Oui |
| [PlanDHygiene](modules/plan_d_hygiene.md) | Plan d'Hygiène (Discipline) | 3 | 0 | Partielle |
| [PlanDeCroissance](modules/plan_de_croissance.md) | Plan de Croissance | 7 | 0 | Partielle |
| [PlanDeDeveloppement](modules/plan_de_developpement.md) | Plan de Développement Personnel | 4 | 0 | Partielle |
| [PlanDeTransmission](modules/plan_de_transmission.md) | Plan de Transmission (Contribution) | 4 | 0 | Partielle |
| [PlanDeVie](modules/plan_de_vie.md) | Plan de Vie | 10 | 34 | Oui |
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
