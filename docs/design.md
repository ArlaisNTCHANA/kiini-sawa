# Système visuel de KIINI SAWA

Un seul style pour toutes les pages de l'application. Les valeurs sont dans `templates/base.html.twig` (`:root`),
les composants dans `assets/styles/app.css` (classes `ks-…`).

## Palette

| Rôle | Variable | Valeur |
|---|---|---|
| Fond / fond secondaire / carte | `--bg` / `--bg-alt` / `--card-bg` | #F8FAFC / #F1F5F9 / #FFFFFF |
| Bordures | `--border` / `--border-strong` | #E2E8F0 / #CBD5E1 |
| Texte : principal, secondaire, discret, très discret | `--fg` / `--fg-light` / `--fg-muted` / `--fg-subtle` | #0F172A / #334155 / #64748B / #94A3B8 |
| Marque | `--primary` (+ `-dark`, `-light`, `-soft`, `-tint`) | #0F766E |
| Accent (livre, citations) | `--accent` | #B45309 |
| Sens : succès, attention, danger, information, couple | `--success`, `--warning`, `--danger`, `--info`, `--couple` (+ `-soft`) | #059669, #D97706, #DC2626, #2563EB, #DB2777 |

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
