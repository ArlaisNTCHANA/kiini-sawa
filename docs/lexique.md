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
| Les 4 plans (Croissance, Développement, Transmission, Hygiène) | **Vos quatre plans** ; la page qui les réunit : **Vue d'ensemble de mes plans** | Mes stratégies, plans stratégiques (décision du fondateur, 17/11/2026 : un plan *contient* des stratégies, il n'en est pas une) |
| Moyens d'atteindre un objectif, dans un plan et sur la carte | **Stratégies** (sens du livre, chapitre 10) | — |
| Indicateur du Plan de Vie | **Score de cohérence** | Score d'alignement |
| Catégories d'objectifs | **Être, Faire, Avoir, Donner** | BE, DO, HAVE, GIVE, Devenir, Accomplir, Posséder, Léguer |
| Note du journal saisie vite | **Note rapide** | Quick capture |
| Type de note « prise de conscience » | **Déclic** | Insight |
| Groupes de notes du journal | **Carnets** | Collections |
| Vue chronologique de l'agenda | **Frise** | Gantt |
| Test des 8 domaines de vie | **Roue de la vie** | Wheel of Life |
| Accroche sous le logo | **Plan de Vie** | Espace de croissance |

## Les 5 plans de la méthode (référence, 17/11/2026)

Diapositive « Les 5 plans de la méthode KIINI » du fondateur. **Ordre, questions et icônes à respecter partout**
(source unique : `PlanCatalog::PLANS`). Le livre (chapitre 9) décrit encore l'ancienne version à 3 axes.

| Plan | Question | Contenu | Icône |
|---|---|---|---|
| Plan de Vie | Qui suis-je, où vais-je ? | Raison d'être, valeurs, vision, objectifs | boussole |
| Plan de Croissance | Qui est-ce que je deviens ? | Caractère & Conviction, Relation & Réseau, valeurs d'alignement | pousse |
| Plan de Développement | Que dois-je maîtriser ? | Compétences, formations, livres d'étude | clé et tournevis |
| Plan d'Hygiène de Vie | Qu'est-ce qui me soutient ? | Sommeil, nutrition, sport, méditation, environnement | cœur-pouls |
| Plan de Transmission | Que vais-je laisser ? | Legs, mentorat, service | main et cœur |

Puis **le Système** qui relie les 5 plans au quotidien : carte stratégique, rituels (habitudes), revues — dans
l'application, la rubrique « Mon quotidien ».

Codes techniques : les catégories restent stockées en BE/DO/HAVE/GIVE ; l'affichage passe par le filtre Twig
`categorie_objectif`.
