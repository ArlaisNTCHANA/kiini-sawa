# Module Rapide : « Aujourd'hui en un geste » (hors connexion)

Première étape de l'amélioration de l'application installable (PWA), demandée par le fondateur (18/11/2026) : faire l'essentiel du quotidien **sans réseau**, puis envoyer au retour de la connexion. Les points d'accès JSON serviront aussi à la future application mobile native.

## Ce que fait la page `/rapide`
- **Rituels** à cocher, **bilan du jour** (intention, tenue ou non, énergie, humeur, stress, gratitude), **journal** (une saisie rapide + les 5 dernières), et la carte « Ma prochaine étape » (son lien n'est actif qu'en ligne).
- La page est une **coquille identique pour tout le monde** (aucune donnée personnelle) : le service worker (`public/sw.js`) la garde. Hors connexion, les écrans du quotidien (/dashboard, rituels, journal, bilan du jour) y renvoient.
- Les données viennent de `GET /api/rapide/aujourdhui` ; une copie est gardée sur le téléphone (IndexedDB `ks-rapide`, clés `snap` et `queue`). Les saisies hors ligne s'affichent tout de suite et attendent dans la file ; au retour du réseau (ou à la réouverture), `POST /api/rapide/sync` les envoie.
- Déconnexion : un clic sur « Déconnexion » efface la base locale (`_pwa_head.html.twig`) pour qu'un appareil partagé ne garde rien.

## Règles de synchronisation (`Rapide\Service\QuickDay`)
- Chaque saisie a un **identifiant créé par le téléphone** (`uid`) mémorisé en base (`sync_operation`) : un renvoi après coupure ne s'applique pas deux fois (résultat « deja »). Nettoyage quotidien à 60 jours (dans `app:reminders:send`).
- Types : `habit.log` (un rituel fait/défait un jour), `review.save` (bilan du jour, **un seul par jour : mise à jour, pas doublon** ; une intention vide n'efface pas celle déjà saisie), `journal.add`.
- **Dernière modification gagne** (règle volontairement simple pour des données personnelles).
- Un jour accepté : dans les 7 derniers jours, jamais plus d'un jour dans le futur (téléphone mal réglé). Au plus 100 opérations par envoi. Chaque opération a son résultat : `ok`, `deja`, `refusee` (à abandonner) ; une opération refusée ne bloque pas les autres.
- Sécurité : l'API demande une session (401 JSON sinon) ; les requêtes d'écriture passent par le contrôle d'origine existant (`SameOriginRequestSubscriber`) ; le texte du téléphone est affiché en `textContent` (jamais en HTML).

## Pour la suite (application native)
Cette API est la base : l'app Expo pourra s'y brancher (il faudra y ajouter une authentification par jeton à la place de la session). Pas encore : lecture/écriture hors ligne des plans, des tests et des revues de la semaine et du mois.

## Tests
`tests/Rapide/QuickDayTest.php` : coquille sans donnée personnelle, API réservée, état du jour, renvoi sans doublon, rituel d'un autre compte refusé, dates invalides, bilan mis à jour sans doublon, journal, plafond de 100, service worker et raccourci du manifeste.
