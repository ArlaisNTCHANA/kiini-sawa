# Le livre dans l'application

Ces chapitres sont affichés dans l'application (module Méthode : `/methode`, encarts
« Comprendre cette étape »). Ils sont copiés depuis le manuscrit `KIINI SAWA/docs/livre/`.

Après une modification du manuscrit :

```bash
php bin/console app:livre:sync
```

Le manifeste et la page auteur ne sont pas publiés dans l'application.
