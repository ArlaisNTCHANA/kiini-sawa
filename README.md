# KIINI SAWA — application web

**Bâtir sa vie avec Dieu.** Une application chrétienne de planification de vie : raison d'être, **6 plans** (Vie,
Croissance, Développement personnel, Hygiène de vie, Intendance, Transmission), rituels, journal, revues, partenaires,
tests de personnalité, diagnostic public **Ma Boussole 360** (`/boussole`), **Sanctuaire du Couple 360** et
organisations. En ligne sur **https://kiinisawa.com**.

## Pour commencer

- **Vous êtes une IA ou un développeur** : lisez d'abord [`AGENTS.md`](AGENTS.md) (règles, organisation du code,
  commandes, production, pièges connus) et [`docs/SUIVI-IA.md`](docs/SUIVI-IA.md) (journal de passation à jour).
- Documentation technique : [`docs/index.md`](docs/index.md) et une fiche par module dans [`docs/modules/`](docs/modules/).
- Feuille de route & RFCs : [`docs/roadmap/ROADMAP.md`](docs/roadmap/ROADMAP.md) ([RFC-001 Couple 360](docs/roadmap/RFC-001-sanctuaire-couple-360.md), [RFC-002 Académie Pratique](docs/roadmap/RFC-002-academie-pratique.md)).
- Règles du projet : [`docs/charte-projet.md`](docs/charte-projet.md), [`docs/lexique.md`](docs/lexique.md),
  [`docs/design.md`](docs/design.md), [`docs/glossaire.md`](docs/glossaire.md).
- Mise en ligne et exploitation : [`docs/mise-en-ligne.md`](docs/mise-en-ligne.md).
- Le livre de la méthode (24 chapitres, 6 plans) : [`content/livre/`](content/livre/) (ancienne version v1 archivée dans [`docs/livre/`](docs/livre/)).
- Lancement du média (réseaux sociaux) : [`docs/media/brief-lancement-media.md`](docs/media/brief-lancement-media.md).

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
