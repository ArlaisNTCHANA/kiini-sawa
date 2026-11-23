# Module Boussole (Ma Boussole 360)

## Rôle

Diagnostic express des 6 plans : 24 affirmations (4 par plan, dont 2 inversées), échelle de 1 à 5, score par plan
de 0 à 100 %, score de cohérence, lecture (matrice des 30 dynamiques « plan fort / plan faible »), signaux et
3 pistes pour la semaine. **Une seule Boussole** : la même sur le site et dans l'application mobile (décision du
23/11/2026, point 2 de la relecture externe).

## Emplacements

- `assets/js/boussole-engine.js` : moteur JS (affichage immédiat sur `/boussole`, sans attendre le serveur).
- `src/Boussole/Service/BoussoleContent.php` : copie conforme des textes du moteur JS (affirmations, lectures, pistes,
  dynamiques). Les noms des plans viennent de `PlanCatalog::PLANS`.
- `src/Boussole/Service/BoussoleEngine.php` : même calcul en PHP (`evaluate()`), validation stricte des réponses
  (`normalizeAnswers()` : exactement 24 entiers de 1 à 5), liste des plans pour le radar (`plans()`).
- `src/Boussole/Service/BoussoleRecorder.php` : enregistrement (anti-doublon de 10 minutes pour un double envoi
  identique), réponses en attente dans la session pour un visiteur.
- `src/Boussole/EventSubscriber/BoussolePendingSubscriber.php` : enregistre la passation en attente à la première page
  vue une fois connecté avec un email vérifié (après inscription ou connexion), avec un message.
- `src/Boussole/Controller/BoussoleController.php` : `/boussole` (public), `POST /boussole/resultat` (public, JSON),
  `/boussole/historique` et `/boussole/passation/{id}` (compte).
- `src/Mobile/Controller/MobileBoussoleController.php` : API mobile (voir `docs/modules/mobile.md`).
- Gabarits : `templates/shared/boussole.html.twig` (questionnaire), `templates/boussole/*` (historique, détail).
  Encart « Ma Boussole 360 » dans « Mon profil KIINI SAWA » (`personnalite/synthesis.html.twig`).

## Entité

`BoussoleResult` (table `boussole_result`) : utilisateur (`ON DELETE CASCADE`), 24 réponses brutes (JSON), scores
(JSON), score de cohérence, plan fort, plan prioritaire, source (`web` / `mobile`), date. La lecture complète est
recalculée à l'affichage depuis les réponses. Incluse automatiquement dans l'export RGPD.

## Parité JS / PHP

`tests/boussole-parite.mjs` produit `tests/fixtures/boussole-parite.json` (735 cas : profils plats, aléatoires, les 30
paires) ; `tests/Boussole/BoussoleParityTest.php` vérifie que le PHP donne exactement le même résultat (textes compris).

**Modifier un texte ou une règle** : changer `boussole-engine.js` **et** `BoussoleContent.php` / `BoussoleEngine.php`,
puis `node tests/boussole-parite.mjs --ecrire`, `node tests/boussole-engine.test.mjs` et `php bin/phpunit tests/Boussole`.

## Points d'attention

- On ne fait jamais confiance à un score envoyé par le client : seul le serveur calcule ce qui est enregistré.
- MySQL réordonne les clés des colonnes JSON : `getScores()` renvoie toujours les plans dans l'ordre du livre.
- L'application mobile n'a pas de moteur : sans connexion, elle affiche la dernière passation gardée en cache, mais ne
  peut pas calculer une nouvelle passation.
