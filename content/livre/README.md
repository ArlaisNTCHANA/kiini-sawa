# Le livre dans l'application

Ces chapitres sont affichés dans l'application (module Méthode : `/methode`, encarts
« Comprendre cette étape »). Ils sont **produits automatiquement** à partir du manuscrit de l'auteur :
`KIINI SAWA/livre-et-ressources/sources/livre/manuscrit.md`. Ne pas les modifier à la main.

Après une modification du manuscrit :

```bash
php bin/console app:livre:sync
```

La commande découpe le manuscrit en chapitres (`BookLibrary::CHAPTERS`), reprend le titre de partie en tête
du premier chapitre de chaque partie, et retire ce qui n'est pas destiné aux lecteurs : dédicace, page auteur,
signature, marqueurs « [HISTOIRE PERSONNELLE] » et « [À COMPLÉTER] ».
