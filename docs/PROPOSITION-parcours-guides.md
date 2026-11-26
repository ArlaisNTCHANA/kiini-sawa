# Proposition : les parcours guidés (remplir ses plans en étant accompagné)

**Statut** : proposition du 26/11/2026, **à valider par le fondateur avant tout code**.
Origine : le parcours « nouvel utilisateur » du 25/11 (`docs/parcours-nouvel-utilisateur-2026-11-25.md`) et le ressenti
du fondateur, qui a essayé de remplir ses plans et s'est vite découragé.

---

## 1. Le problème, dit simplement

> « L'application n'est pas facile, il faut de la discipline et l'enchaînement n'aide pas. Pas de visibilité globale
> sur le circuit de remplissage. » (le fondateur, 26/11/2026)

- **Remplir les plans demande de la discipline**, et l'application ne porte pas l'utilisateur : chaque plan est un gros
  formulaire, rempli seul, d'un coup.
- **On ne voit pas le chemin** : combien d'étapes, où j'en suis, ce qui reste, combien de temps il faut.
- **La compréhension arrive trop tard ou pas du tout** : le chapitre du livre est un encart replié à côté du formulaire.
  On écrit avant d'avoir compris et médité.
- **Rien ne fait revenir** : une fois la page fermée, rien ne rappelle doucement de continuer.

Le livre dit pourtant : « Ce livre ne se lit pas, il se vit. » L'application doit **faire vivre le livre pas à pas**,
au lieu de tendre un formulaire.

## 2. L'idée

L'utilisateur choisit **un parcours**, et l'application le guide séance après séance, comme une ligne de métro dont il
voit toutes les stations et celle où il se trouve.

- **Sept parcours** :
  - six parcours courts, **un par plan** : Plan de Vie, Plan de Croissance, Plan de Développement personnel, Plan
    d'Hygiène de vie, Plan d'Intendance, Plan de Transmission ;
  - le **parcours complet de 90 jours**, qui enchaîne les six dans l'ordre du livre (annexe A, 13 semaines).
- **Chaque station est une séance courte** (10 à 15 minutes), toujours bâtie de la même façon (§ 3).
- **Ce que la personne écrit pendant la séance va directement dans son plan**, à la bonne rubrique. À la fin d'un
  parcours, le plan est rempli sans qu'elle ait jamais vu le gros formulaire.
- **Un rendez-vous** : si elle le souhaite, un e-mail ou une notification lui annonce la séance du jour.

Le **mode libre** (les assistants et les pages actuelles des plans) reste disponible pour ceux qui préfèrent remplir
seuls ou corriger : on ne retire rien (charte : non-régression).

## 3. Une séance : cinq temps, toujours les mêmes

| Temps | Ce que vit la personne | Exemple : séance « Mes valeurs » (Plan de Vie) |
|---|---|---|
| **1. Comprendre** | Un enseignement court tiré du chapitre du livre (2 à 4 minutes de lecture, ou une vidéo), avec une image ou un schéma du livre. Lien vers le chapitre entier pour qui veut aller plus loin. | Ce qu'est une valeur réelle, une valeur déclarée et une contre-valeur ; l'exemple biblique du chapitre. |
| **2. Méditer** | Un verset, un temps de silence et de prière proposé (« Prenez deux minutes avec Dieu… »). Un bouton « J'ai pris ce temps » et non un minuteur imposé. | Un verset sur ce qui guide nos choix ; « Seigneur, montre-moi ce qui compte vraiment pour moi. » |
| **3. Réfléchir** | Deux ou trois questions de discernement (celles du Sanctuaire du livre), avec l'explication de la bonne réponse quand il y en a une, ou une question ouverte notée en brouillon. | « Quand vous avez dû choisir récemment, qu'avez-vous protégé en premier ? » |
| **4. Écrire (cristalliser)** | **Un seul** champ ou une petite liste, avec un exemple chrétien et concret, qui alimente **une rubrique précise du plan**. | « Vos 3 valeurs non négociables » → rubrique Valeurs du Plan de Vie. |
| **5. Célébrer et annoncer la suite** | Message chaleureux, la ligne de métro qui avance d'une station, ce qui vient d'être ajouté au plan, et la séance suivante (« Demain : vos passions, 10 minutes »). | « Vos valeurs sont posées : Foi, Intégrité, Service. Station 3 sur 8. » |

Règles :
- **Un seul geste d'écriture par séance.** Le reste de la rubrique (convictions détaillées, contre-valeurs…) est proposé
  plus tard, en « approfondir », pas imposé au premier passage.
- On peut **reprendre exactement où l'on s'est arrêté**, même au milieu d'une séance (brouillon gardé).
- **Le vocabulaire est celui d'un débutant.** Les mots techniques du livre sont expliqués la première fois.

## 4. La ligne de métro (voir où l'on en est)

Une bande toujours visible en haut de la séance, et une carte complète sur la page du parcours :

```
Parcours « Plan de Vie »  ·  station 3 sur 8  ·  environ 50 min restantes

 ●━━━━━━●━━━━━━◉━━━━━━○━━━━━━○━━━━━━○━━━━━━○━━━━━━○
But de  Balance Valeurs Passions Dons   Personna- Vision  Objectifs
Dieu    interne                         lité
 fait    fait   aujourd'hui
```

- Stations faites (pleines), station du jour (cerclée), stations à venir (vides). On peut toucher une station faite pour
  la relire ou la corriger.
- Pour le **parcours de 90 jours**, la carte montre les **6 lignes** (une par plan) et les **correspondances**
  (Feedback nourricier à la semaine 8, tests de personnalité, revue de la semaine…).
- Sur **Aujourd'hui**, une seule carte « Ma prochaine étape » remplace les trois blocs actuels : « Parcours Plan de Vie,
  station 3 : vos valeurs, 10 min », avec le bouton « Reprendre ».

## 5. Les rendez-vous (e-mails et notifications)

À l'inscription au parcours, la personne choisit son rythme (déjà proposé par l'Académie) : **à mon rythme, sans
e-mail** / **une séance par jour** / **trois par semaine** (lundi, mercredi, vendredi), et l'heure.

Exemple de message :

> **Aujourd'hui : ce qui guide vos choix**
> Bonjour Testeur, hier vous avez écrit votre But de Dieu. Aujourd'hui, nous regardons vos valeurs : ce sur quoi vous ne
> transigez pas. Un enseignement de 3 minutes, un temps de prière, une question, et vous poserez vos 3 valeurs.
> « Garde ton cœur plus que toute autre chose, car de lui viennent les sources de la vie. » (Proverbes 4:23)
> **[Commencer la séance, 10 min]**

- **Doux et jamais culpabilisant** : après 2 messages sans activité, on espace ; après 3, on propose une pause ou un
  autre rythme. C'est déjà le principe de l'Académie (`emailsWithoutProgress`).
- Un seul message par jour au plus, toutes sources confondues (rappels de rituels compris), via le `Notifier` existant.
- Notification dans l'application mobile quand elle existera (dépôt `kiini-mobile`).

## 6. Ce qui existe déjà et qu'on réutilise

| Besoin | Déjà dans l'application | Où |
|---|---|---|
| Parcours, modules, séances, ordre, durée, progression, inscription | L'Académie (parcours → modules → leçons, `CourseEnrollment`, `LessonCompletion`) | `src/Academie/`, `docs/modules/academie.md` |
| Enseignement illustré, verset, schéma du livre, vidéo | Corps de leçon (Markdown, images, encadrés, schémas, vidéo YouTube ou Vimeo) | `LessonRenderer`, `LessonBlocks`, `templates/academie/_lesson_body.html.twig` |
| Questions de discernement avec explication | `LessonQuestion` | `src/Academie/Entity/LessonQuestion.php` |
| Écrire dans un plan depuis la séance | « Mettre en pratique » → `PracticeCatalog` / `AcademyPlanBridge` | `src/Academie/Service/PracticeCatalog.php` |
| Rythme et e-mails, pause, espacement | `AcademyPaceMailer`, `paceMode` | `src/Academie/Service/AcademyPaceMailer.php` |
| Découpage du parcours de 90 jours (13 semaines, chapitres, écrans) | `Journey` | `src/Methode/Service/Journey.php`, `templates/methode/journey.html.twig` |
| Textes du livre, chapitre par chapitre | `BookLibrary` | `src/Methode/Service/BookLibrary.php`, `content/livre/` |
| Rubriques des plans et leur enregistrement | `LifePlanWizardSaver` (étapes 1 à 13) | `src/PlanDeVie/Service/LifePlanWizardSaver.php` |

**Conclusion** : les parcours guidés peuvent être des **parcours de l'Académie d'un genre particulier** (« parcours de
plan »), plutôt qu'un nouveau module. Le studio auteur existant permet au fondateur d'écrire et de corriger les séances
lui-même.

## 7. Ce qu'il faut ajouter

1. **Écrire dans toutes les rubriques des plans** : « Mettre en pratique » ne sait aujourd'hui qu'ajouter un élément
   (une valeur, un objectif, un principe…). Il faut des cibles pour **chaque rubrique** : But de Dieu (avec l'Équation
   du But), convictions et croyances limitantes, passions, dons et talents, vision et horizon, objectifs
   Être/Faire/Avoir/Donner avec échéance, traits de caractère, réseaux, compétences et connaissances, domaines
   d'hygiène, inventaire d'Intendance, phrase d'éternité, déplacement de trésor, projets de transmission.
   **Le plus simple** : réutiliser `LifePlanWizardSaver` (mêmes champs, même enregistrement) depuis la séance, plutôt
   que de dupliquer la logique.
2. **Le temps « Méditer »** : un bloc de leçon « verset + temps de prière », avec le bouton « J'ai pris ce temps ».
3. **La ligne de métro** : composant d'affichage (bande en haut de séance, carte du parcours, version 90 jours sur
   6 lignes) et carte « Ma prochaine étape » sur Aujourd'hui (`src/Dashboard/Service/NextStep.php`).
4. **La célébration de fin de séance et de fin de parcours** : plan mis à jour, score de cohérence affiché à la fin du
   parcours Plan de Vie.
5. **Les parcours de plan doivent être accessibles comme les plans** : le parcours Plan de Vie gratuit, les cinq
   autres PRO (comme les plans eux-mêmes). Le parcours de 90 jours suit la même règle, plan par plan.
6. **Le contenu** : environ 8 séances pour le Plan de Vie, 3 à 4 pour chacun des autres plans, soit une trentaine de
   séances. Elles sont tirées du livre (texte de l'auteur, **ne pas le réécrire sans accord** : proposer des extraits
   et des questions, le fondateur valide dans le studio).

## 8. Découpage proposé (lots)

| Lot | Contenu | Résultat visible |
|---|---|---|
| **1** | Parcours **Plan de Vie** (8 séances), cibles d'écriture du Plan de Vie, temps « Méditer », ligne de métro simple, carte « Ma prochaine étape », célébration et score à la fin | Un nouvel inscrit peut poser tout son Plan de Vie en une semaine, guidé, sans voir le gros formulaire |
| **2** | Rendez-vous (e-mails), reprise au milieu d'une séance, proposition du parcours dès la fin de l'accueil et du résultat de la Boussole | L'utilisateur est rappelé et revient |
| **3** | Les cinq autres parcours de plan et leurs cibles d'écriture | Les 6 plans se remplissent par parcours |
| **4** | Parcours de **90 jours** = enchaînement des 6, carte à 6 lignes et correspondances (feedback, tests, revues) | Le chemin complet du livre est visible et suivi |

Chaque lot : tests automatiques, vérification à l'écran **en format téléphone**, puis un vrai essai par le fondateur
avant de passer au suivant.

## 9. Questions au fondateur (à trancher avant le lot 1)

1. **Où proposer les parcours ?** Proposition : à la fin de l'accueil (« Voulez-vous être guidé pour écrire votre Plan
   de Vie ? Une séance de 10 minutes par jour ») et sur le résultat de la Boussole (le parcours du plan prioritaire).
2. **Les séances : extraits du livre mot pour mot, ou résumés validés par vous ?**
3. **Le temps de méditation** : simple invitation, ou minuteur facultatif (2, 5 ou 10 minutes) ?
4. **Rythme par défaut** : « une séance par jour » ou « à mon rythme » ?
5. **Faut-il garder l'Académie actuelle à part** (parcours thématiques comme « Choix et engagement ») et créer un
   espace « Mes parcours » pour les parcours de plan, ou tout réunir sous l'Académie ?
6. **Qui écrit les ~30 séances ?** L'IA propose un premier jet à partir du livre, vous corrigez dans le studio ?

## 10. Comment on saura que c'est réussi

- Un nouvel inscrit, **sur téléphone**, termine le parcours Plan de Vie en une semaine sans aide.
- Le fondateur refait le test lui-même et **ne se décourage pas**.
- Mesures simples, dans l'administration : part des inscrits qui commencent un parcours, qui terminent la station 3,
  qui terminent le parcours Plan de Vie ; plans remplis par parcours ou en mode libre.
