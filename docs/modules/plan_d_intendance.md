# Module PlanDIntendance

**Statut** : actif depuis le 17/11/2026 (PRO, sauf si l'administrateur l'ouvre en gratuit : `AppConfig::enableStewardshipFree`).

## Rôle

Le **Plan d'Intendance : ce que je gère** (livre, partie VI, chapitre 18 « Gérer ce qui m'est confié »). L'utilisateur
gère fidèlement ce qui est déjà entre ses mains, et en rend compte. Une page guidée suit le cycle de l'intendant :

1. **Inventaire** : ce que le Plan de Vie a mis en lumière (But, dons, passions, personnalité, vision, saison),
   proposé en puces depuis le Plan de Vie et les tests ; puis les **7 domaines** (finances et biens, temps, foyer et
   enfants, ressources intellectuelles, influence et position, responsabilités confiées, opportunités et accès).
2. **État** : il fructifie, il dort, il fuit.
3. **Décision** : affecter, protéger, libérer, avec une action des 30 prochains jours ; 3 à 5 **priorités** au plus
   (5 imposé côté serveur).
4. **Compte rendu** : rythme (semaine, mois, saison), avec qui, « J'ai fait le point ».

Garde-fous du livre repris dans la page : l'intendance n'est ni une méthode pour s'enrichir, ni un fardeau ; aucun
montant n'est demandé. Le vocabulaire est **mot pour mot celui du livre** (`Service/Stewardship.php`) : ne pas le
reformuler sans l'auteur.

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/PlanDIntendance/` |
| Vocabulaire (domaines, questions, états, décisions) | `src/PlanDIntendance/Service/Stewardship.php` |
| Inventaire et suggestions (Plan de Vie, tests) | `src/PlanDIntendance/Service/StewardshipInventory.php` |
| Templates | `templates/plan_d_intendance/` (`index` : page guidée ; `visualize` : le plan sur une page) |
| Tests | `tests/PlanDIntendance/` |
| URL | `/plan-d-intendance` (+ `/visualiser`, `/element`, `/element/{id}`, `/element/{id}/fait`, `/element/{id}/supprimer`, `/rythme`) |

## Entités

- `StewardshipItem` (`stewardship_item`) : utilisateur, origine (domaine ou sortie du Plan de Vie), libellé, note,
  état, décision, action, priorité, action faite le, position.
- `StewardshipRhythm` (`stewardship_rhythm`, un par utilisateur) : fréquence, avec qui, date du dernier point.

## Liens avec le reste

- `PlanCatalog::PLANS['INTENDANCE']` : navigation, vue d'ensemble des plans (carte qui mène à cette page).
- Encart du livre : `book_panel('intendance')` → chapitre 18.
- **Pas encore** : revue du mois (Bilan), carte stratégique (plans de soutien), lien d'une action vers l'Agenda, PDF,
  partage avec le conjoint (Duo). Voir `docs/DECISION-plan-d-intendance.md`.
