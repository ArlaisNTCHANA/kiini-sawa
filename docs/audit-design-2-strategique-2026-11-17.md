# Audit design n° 2 : de l'application à l'entreprise (17/11/2026)

Suite de `docs/audit-design-2026-11-17.md` (cohérence technique). Demande du fondateur : une application « qui va
générer 1 M€ », trop d'incohérences, très peu de visuel interactif, un schéma « muet » sur « Mes stratégies », et
une page appelée « stratégies » alors qu'elle présente les plans.

Méthode : parcours de la vitrine publique (production), du compte connecté (local, ordinateur et téléphone),
mesures dans le code.

## 1. Ce qu'implique « 1 M€ » (ordre de grandeur, à valider)

Avec les prix actuels (PRO 79,99 €/an, Duo 119,99 €, Organisation dès 19,50 € par membre et par an), 1 M€ de chiffre
d'affaires annuel représente environ **12 500 abonnés PRO annuels**, ou un mélange de PRO, de couples et
d'organisations. Avec un taux de passage du gratuit au payant de 2 à 5 % (fourchette courante des produits à abonnement
gratuit/payant, **non mesurée chez vous**), il faut de l'ordre de **250 000 à 600 000 comptes gratuits**.

Conséquence : le design doit servir trois choses, dans cet ordre.
1. **Convaincre en 30 secondes** (vitrine) : sans cela, pas d'inscription.
2. **Faire gagner une victoire dans la première semaine** (premier usage) : sans cela, pas de retour.
3. **Faire sentir le progrès** (visuels vivants) : c'est ce qui fait payer, puis renouveler.

L'application est aujourd'hui solide sur le contenu et faible sur ces trois points.

## 2. La vitrine publique (app.kiinisawa.com)

| Constat | Pourquoi c'est un problème |
|---|---|
| **0 image, 0 schéma, 0 capture d'écran.** | Le visiteur ne voit jamais le produit ni les beaux schémas du livre. |
| **Aucun visage, aucun témoignage, aucun auteur.** | Un produit de foi et de vie personnelle se vend sur la confiance : qui est derrière, pour qui ça a marché. |
| Les plans sont présentés par des émojis (🧭 💎 🎓 🛡️ ⚖️ 🎁). | Aspect improvisé ; les émojis changent d'une machine à l'autre. |
| Le titre parle de « raison d'être » alors que votre livre dit « But de Dieu » et l'Équation du But. | Le concept fort du livre est absent de la première page. |
| La page ne montre pas le livre (couverture, extrait, achat). | Le livre est votre meilleur outil d'acquisition et il est invisible. |
| **Promesse fausse** : « La méthode : tous les chapitres du livre » dans l'offre gratuite. | Depuis hier, le gratuit n'a que l'essentiel. **Corrigé dans le code** (non déployé). |
| Les prix sont à la fin d'une page longue ; deux boutons presque identiques en tête. | Parcours d'achat peu guidé. |

## 3. Le premier usage

Inscription → vérification de l'email → onboarding (3 minutes) → score de cohérence. Le principe est bon, mais :
- **Le premier écran connecté** (accueil) est un grand bandeau (salutation, verset, « 0/0 rituels ») ; la seule
  action utile (le parcours de 90 jours) arrive plus bas.
- **Aucune « victoire rapide » visible** : votre propre livre en prévoit une (l'Équation du But écrite, une première
  décision d'intendance), l'accueil ne la met pas en scène.

## 4. Le visuel interactif : presque inexistant

| Mesure | Valeur |
|---|---|
| Écrans avec un vrai graphique interactif (Chart.js, présent dans la pile technique) | **0** |
| Écrans avec un diagramme (Mermaid) | 2 : la carte du Plan de Vie et « Mes plans » ; **non cliquables** |
| Schémas du livre dans l'application | 15, tous des **images fixes** |

**« Mes stratégies » : le schéma est muet.** C'est exact, et c'est de mon fait : le 17/11 j'ai posé la figure des
6 plans comme une simple image (`<img>`), sans lien ni survol. Elle ressemble à une carte et ne fait rien. Même
constat pour la cascade, les saisons, le cycle de l'intendant et les revues : jolis, inertes.

Ce qui manque, dans l'ordre de valeur :
1. **Le cercle des 6 plans vivant** : chaque plan cliquable, avec sa progression (anneau), son état (rien commencé,
   en cours, à jour) et un survol qui dit quoi faire ensuite.
2. **La carte stratégique cliquable** : cliquer un objectif ouvre ses plans de soutien et ses rituels.
3. **Le cycle de l'intendant** avec les compteurs réels (combien d'éléments à chaque étape).
4. **Les saisons** : roue avec la saison en cours et le temps restant.
5. **Mes rythmes** : jour, semaine, mois, saison, année, avec ce qui est fait ou en retard.
6. **Un tableau de progrès** : anneaux, radar d'équilibre (test Équilibre de vie), courbes d'humeur et d'énergie du
   bilan du jour, parcours de 90 jours.

## 5. Les noms : « Mes stratégies » à tort

- L'adresse est `/mes-strategies`, la route `app_strategies`, le fichier `strategies.html.twig`, alors que la page
  s'intitule « Vue d'ensemble de mes plans » et que le lexique interdit « Mes stratégies ». C'est un reste de
  l'ancienne version, où chaque plan s'appelait « stratégie ».
- Dans le livre, une **stratégie** est autre chose : « comment je m'y prends » (cascade : But, Vision, Objectifs,
  Plans de soutien, **Stratégies**, Habitudes).
- Le menu a trois libellés pour la même chose : « Mes plans » (rubrique), « Vue d'ensemble de mes plans » (page),
  « Mes stratégies » (adresse, anciens courriels).

Proposition : adresse **`/mes-plans`** (la page des rituels reste `/mes-plans/rituels`), route `app_plans`,
redirection 301 de l'ancienne adresse, libellé unique « Mes plans ».

## 6. Cohérence (rappel de l'audit n° 1, mesures du 17/11)

217 émojis dans 44 fichiers ; 64 classes de boutons ; 3 façons de faire un en-tête ; 3 805 styles écrits dans les
balises ; 1 631 couleurs en dur ; 40 arrondis d'angle différents ; titres en majuscules à l'anglaise. Le bandeau de
l'accueil garde un dégradé vert-bleu, des halos indigo et ambre.

## 7. Plan proposé, dans l'ordre de l'argent

| Priorité | Lot | Contenu | Effet attendu |
|---|---|---|---|
| 1 | **Vitrine** | Couverture du livre, l'auteur et sa légitimité, 3 schémas du livre, capture de l'application, témoignages dès qu'il y en a, prix mieux guidés, Équation du But en première page, plus d'émojis | Inscriptions |
| 2 | **Premier usage** | Accueil « Ma prochaine étape » ; victoire de la première semaine (Équation du But + première décision) | Activation et retour |
| 3 | **Visuels vivants** | Cercle des plans cliquable avec anneaux de progression ; carte cliquable ; saisons, intendance, rythmes ; tableau de progrès | Rétention, passage à PRO |
| 4 | **Un seul design** | Lot A (zéro émoji, couleurs) puis lot B (boutons, en-têtes, cartes, arrondis) de l'audit n° 1, avec tests qui l'imposent | Confiance, maintenance |
| 5 | **Noms** | `/mes-plans`, libellés uniques, redirections | Clarté |

Ordre conseillé : **5 (petit, immédiat), puis 1, 2, 3, 4**. Les lots 3 et 4 se font ensemble écran par écran
(chaque écran refait : sans émoji, avec ses composants et son visuel vivant).

## 8. Ce que je ne sais pas (à mesurer, pas à deviner)

- Combien de visiteurs de la vitrine s'inscrivent, et combien finissent l'onboarding : aucune mesure n'est branchée.
  Sans ces deux chiffres, on ne sait pas lequel des lots 1 et 2 rapporte le plus. Ajouter un suivi d'entonnoir
  simple (visite, inscription, onboarding terminé, première semaine) est la première chose à faire.
- Les prix : 79,99 €/an est cohérent avec la concurrence des applications de développement personnel, mais le pouvoir
  d'achat en Afrique francophone (FCFA) demande d'être testé, pas supposé.
