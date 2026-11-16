# KIINI SAWA — application web

**Bâtir sa vie avec Dieu.** Une application chrétienne de planification de vie : raison d'être, 5 plans (Vie,
Croissance, Développement, Transmission, Hygiène de vie), rituels, journal, revues, partenaires, tests de personnalité,
espace couple et organisations. En ligne sur **https://app.kiinisawa.com**.

## Pour commencer

- **Vous êtes une IA ou un développeur** : lisez d'abord [`AGENTS.md`](AGENTS.md) (règles, organisation du code,
  commandes, production, pièges connus).
- Documentation technique : [`docs/index.md`](docs/index.md) et une fiche par module dans [`docs/modules/`](docs/modules/).
- Règles du projet : [`docs/charte-projet.md`](docs/charte-projet.md), [`docs/lexique.md`](docs/lexique.md),
  [`docs/design.md`](docs/design.md), [`docs/glossaire.md`](docs/glossaire.md).
- Mise en ligne et exploitation : [`docs/mise-en-ligne.md`](docs/mise-en-ligne.md).
- Le livre de la méthode : [`docs/livre/`](docs/livre/).

## Démarrage rapide

```bash
composer install
php bin/console doctrine:migrations:migrate -n
php bin/console app:install --admin-email=vous@exemple.com
php bin/console tailwind:build --watch
php -S 127.0.0.1:8010 -t public
php bin/phpunit
```

## Technique

Symfony 8.1 · PHP 8.4+ · Doctrine · MySQL / MariaDB · AssetMapper · Tailwind · Stimulus · Alpine.js · Dompdf.
Hébergement : Hostinger (mutualisé). Emails : Brevo.

Code privé : © KIINI SAWA. Tous droits réservés.
