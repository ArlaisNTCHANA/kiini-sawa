# Système visuel de KIINI SAWA

Un seul style pour toutes les pages de l'application. Les valeurs sont dans `templates/base.html.twig` (`:root`),
les composants dans `assets/styles/app.css` (classes `ks-…`).

## Palette (celle du livre, depuis le 17/11/2026)

Source : la palette des schémas du livre (`livre-et-ressources/sources/livre/figs.py`) : vert forêt, vert, sauge, or.
Le livre et l'application doivent sembler sortir du même atelier.

| Rôle | Variable | Valeur |
|---|---|---|
| Fond / fond secondaire / carte | `--bg` / `--bg-alt` / `--card-bg` | #F7F8F5 / #EEF1EC / #FFFFFF |
| Bordures | `--border` / `--border-strong` | #E1E6DF / #C9D2C8 |
| Texte : principal, secondaire, discret, très discret | `--fg` / `--fg-light` / `--fg-muted` / `--fg-subtle` | #1B1B1B / #374238 / #66706A / #9AA39C |
| Marque (vert du livre) | `--primary` (+ `-dark` #1F4D3A, `-light`, `-soft` #EEF3EF, `-tint`) | #2E6B53 |
| Or du livre (le point d'or, le noyau) | `--gold` (+ `-light`, `-soft`) ; texte : `--accent` | #C8962E ; #9A6B12 |
| Sauge, brume | `--sage`, `--mist` | #7A9E7E, #C8D6CD |
| Sens : succès, attention, danger, information, couple | `--success`, `--warning`, `--danger`, `--info`, `--couple` (+ `-soft`) | #3A7D52, #B7791F, #B3402A, #3B6E8F, #B5562F |

**Les plans** (`--plan-vie`, `--plan-croissance`, `--plan-developpement`, `--plan-hygiene`, `--plan-intendance`,
`--plan-transmission`, chacun avec `-soft`) suivent la figure Être/Faire/Avoir/Donner du livre : Croissance (Être)
vert forêt, Développement (Faire) vert, Intendance (Avoir) sauge, Transmission (Donner) or ; Hygiène (le socle)
vert gris ; Plan de Vie, le point d'or. Plus de couleur arc-en-ciel par plan.

Les classes Tailwind `teal-*` sont redéfinies sur les verts du livre (`assets/styles/app.css`, `@theme`).

**Schémas du livre** : `assets/images/livre/` (copiés de `livre-et-ressources/illustrations`), insérés avec
`{% include 'methode/_figure.html.twig' with {name, alt, caption, collapsed, max} only %}`. Ne pas utiliser
`fig_bedohavegive` tant qu'il dit « De quoi ai-je besoin ? » (contraire au chapitre 18) et BE/DO/HAVE/GIVE.

Règle : pas de nouvelle couleur en dur dans un gabarit de l'application. Exceptions : PDF et emails (les variables CSS
n'y fonctionnent pas), graphiques et cartes (JavaScript).

## Typographie

- Titres : `var(--font-serif)` (Playfair Display). Texte : `var(--font-sans)` (Inter).
- Titre de page : `.ks-header__title` ; titre de section : `.ks-section-title` ; surtitre : `.ks-header__eyebrow` / `.ks-eyebrow`.

## Composants

| Besoin | Classe ou gabarit |
|---|---|
| Conteneur de page | `.ks-page` (`--narrow` 820 px, `--reading` 720 px) |
| En-tête de page | `{% include 'shared/_page_header.html.twig' with {title, eyebrow, icon, lead, back, center} %}` (ou `embed` avec un bloc `actions`) |
| Carte | `.ks-card` (`--flat`, `--accent`, `--link`), grille `.ks-grid` |
| Boutons | `.ks-btn` + `--primary` (une seule action principale par écran), `--secondary`, `--ghost`, `--danger`, `--sm` |
| Onglets | `.ks-tabs` > `.ks-tab.is-active` |
| État vide | `.ks-empty` (`__icon`, `__title`, `__text`) + un bouton principal |
| Messages | `.ks-alert--info / success / warning / danger`, pastille `.ks-badge` |
| Formulaires | `.ks-field` > `.ks-label` + `.ks-input` |

## Navigation (17/11/2026)

Une seule définition pour l'ordinateur et le téléphone : `App\Shared\Twig\NavigationExtension` (`nav_sections()`,
`nav_mobile_bar()`, `nav_active_section()`). Six rubriques qui suivent la méthode :

| Rubrique | Pages |
|---|---|
| Aujourd'hui | Accueil |
| Me connaître | Mes tests, Mon profil KIINI SAWA, Mon évolution, Feedback nourricier |
| Mes plans | Plan de Vie, Plan de Croissance, Plan de Développement, Plan de Transmission, Plan d'Hygiène de Vie, Vue d'ensemble de mes plans |
| Mon quotidien | Rituels, Agenda, Journal, Bilan |
| Ensemble | Partenaires, Notre couple, Défis, Mon organisation, Inviter mes proches |
| La Méthode | Les chapitres du livre |

- Ordinateur : barre du haut, un panneau par rubrique (survol ou clic, Échap pour fermer).
- Téléphone : barre du bas (Aujourd'hui, Mes plans, Rituels, Journal, Menu) ; « Menu » ouvre les six rubriques puis le compte.
- Menu du compte : profil, abonnement, notifications, installation, avis, déconnexion ; **jamais de fonctionnalité**.
- Ajouter une page : l'inscrire dans `NavigationExtension::SECTIONS` (et son préfixe d'adresse dans `match`) ;
  `tests/Shared/NavigationTest.php` vérifie que chaque page du menu s'ouvre.
