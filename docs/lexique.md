# Lexique de l'interface

Un seul mot par chose, partout : menus, titres de page, emails, page d'accueil. Les termes du livre (chapitres de la
méthode) restent ceux du livre.

| Chose | Mot à utiliser | À ne plus employer |
|---|---|---|
| Petites actions régulières | **Rituels** | Habitudes (sauf citation du livre) |
| Calendrier, tâches, jalons | **Agenda** | Organisation, Calendrier, Planner |
| Espace Église / association / entreprise | **Mon organisation** | — |
| Personnes qui vous accompagnent | **Partenaires** (de redevabilité) | Réseau |
| Questionnaires | **Mes tests** | Mes découvertes, Découverte de soi, Architecture intérieure |
| Les plans autres que le Plan de Vie (Croissance, Développement personnel, Hygiène de vie, Intendance, Transmission) | **Vos plans** ; la page qui les réunit : **Mes plans** (adresse `/mes-plans`) | Mes stratégies, plans stratégiques (décision du fondateur, 17/11/2026 : un plan *contient* des stratégies, il n'en est pas une) |
| Moyens d'atteindre un objectif, dans un plan et sur la carte | **Stratégies** (sens du livre, chapitre 10) | — |
| Indicateur du Plan de Vie | **Score de cohérence** | Score d'alignement |
| Catégories d'objectifs | **Être, Faire, Avoir, Donner** | BE, DO, HAVE, GIVE, Devenir, Accomplir, Posséder, Léguer |
| Note du journal saisie vite | **Note rapide** | Quick capture |
| Type de note « prise de conscience » | **Déclic** | Insight |
| Groupes de notes du journal | **Carnets** | Collections |
| Vue chronologique de l'agenda | **Frise** | Gantt |
| Test des 8 domaines de vie | **Roue de la vie** | Wheel of Life |
| Accroche sous le logo | **Plan de Vie** | Espace de croissance |
| Le mot « Sanctuaire » | Deux sens acceptés (décision du fondateur, 23/11/2026) : **Le Sanctuaire**, exercice écrit de chaque chapitre du livre ; **le Sanctuaire du Couple** (ses pièces : L'Autel, Le Jardin des défis, L'Atelier, Le Salon), espace commun des conjoints. Toujours préciser « du Couple » pour le second | — |

## Les 6 plans de la méthode (référence : le livre, 17/11/2026)

Source : le livre de l'auteur (`livre-et-ressources/sources/livre/manuscrit.md`, « Comment lire ce livre »). Le Plan
d'Intendance a été ajouté le 17/11/2026. **Ordre, noms, formules et icônes à respecter partout** (source unique dans le
code : `PlanCatalog::PLANS`, dont la clé `question` porte la formule du livre).

| Plan | Formule | Contenu | Icône |
|---|---|---|---|
| Plan de Vie | Qui je suis, où je vais | But de Dieu (Équation du But), valeurs, passions, dons, vision, objectifs | boussole |
| Plan de Croissance | Qui je deviens | Caractère & Conviction, Relation & Réseau | pousse |
| Plan de Développement personnel | Ce que je développe | Compétence & Connaissance : formations, livres d'étude, route professionnelle | clé et tournevis |
| Plan d'Hygiène de vie | Ce qui me soutient | Corps, âme et esprit | cœur-pouls |
| Plan d'Intendance | Ce que je gère | 7 domaines : finances et biens, temps, foyer et enfants, ressources intellectuelles, influence et position, responsabilités confiées, opportunités et accès ; cycle inventaire, état (fructifie, dort, fuit), décision (affecter, protéger, libérer), compte rendu | mains et pièces |
| Plan de Transmission | Ce que je laisse | Valeurs, œuvres et personnes ; legs, mentorat, service | main et cœur |

Puis **le Système** qui relie les 6 plans au quotidien : carte stratégique, rituels (habitudes), revues — dans
l'application, la rubrique « Mon quotidien ».

Être → Croissance, Faire → Développement personnel, Avoir → Intendance (« ce qui m'est confié », pas seulement ce
qu'on possède), Donner → Transmission. Un don est identifié (Vie), aiguisé (Développement), géré (Intendance),
transmis (Transmission).

Codes techniques : les catégories restent stockées en BE/DO/HAVE/GIVE ; l'affichage passe par le filtre Twig
`categorie_objectif`.
