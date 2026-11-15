# Dépendances JavaScript

Aucune bibliothèque n'est chargée depuis un CDN : tout est servi par l'application. La politique de sécurité (CSP) l'impose d'ailleurs, sauf pour les exceptions listées dans `src/Shared/EventSubscriber/SecurityHeadersSubscriber.php`.

## Scripts classiques (`assets/lib/`)

Ces bibliothèques sont utilisées comme variables globales (`Alpine`, `lucide`, `mermaid`) par les scripts inline des templates. Elles sont donc chargées par une balise `<script>` classique, et non par l'importmap.

| Bibliothèque | Version | Fichier | SHA-256 |
|---|---|---|---|
| Alpine.js | 3.17.5 | `assets/lib/alpinejs-3.17.5.min.js` | `b4036e9e9ac22fa6f6ae1ba292e015e1f3e9c85d0d79e73e4c8eb9bb139b522e` |
| Lucide (icônes) | 1.73.0 | `assets/lib/lucide-1.73.0.min.js` | `ed271ffbe31f0905af8a6ef7b45a70831a517a70909795e637135e218a543c5e` |
| Mermaid (carte stratégique) | 10.9.8 | `assets/lib/mermaid-10.9.8.min.js` | `8d607d7ef1d077a8aa202e18e62212bfa992c68bfeabc5cf45d51a128fe6675d` |

**Mettre à jour une bibliothèque :**

1. Télécharger la version précise, par exemple `https://unpkg.com/alpinejs@<version>/dist/cdn.min.js`, dans `assets/lib/<nom>-<version>.min.js`.
2. Remplacer le nom du fichier dans les templates : `grep -rn "lib/<nom>-" templates`.
3. Supprimer l'ancien fichier et mettre à jour ce tableau (`shasum -a 256 assets/lib/*`).

## Modules (importmap)

Stimulus, Turbo, Chart.js et les contrôleurs UX passent par l'importmap (`importmap.php`). Les fichiers sont téléchargés dans `assets/vendor/` (non versionné) par `bin/console importmap:install`, lancé automatiquement par `composer install`.

Ajouter un module : `bin/console importmap:require <paquet>`.

## Exception restante

- **highlight.js** (coloration du code dans `/admin/doc`), chargé depuis cdnjs en version fixe 11.9.0. Il est réservé à l'administration ; on pourra l'intégrer de la même façon.
- **Google Fonts** (Inter, Playfair Display) : à auto-héberger avant la mise en production, pour la confidentialité (RGPD).
