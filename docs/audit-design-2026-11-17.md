# Audit design de KIINI SAWA — 17/11/2026

Demande du fondateur : « Le design est vraiment problématique… un menu affiche des émoticônes, le suivant non. »
Méthode : parcours des écrans (ordinateur et téléphone, compte de test PRO en local) et mesures dans le code
(`templates/`, hors PDF et emails). Référence visuelle : le livre (`livre-et-ressources/sources/livre/figs.py`) et
`docs/design.md`.

## Le diagnostic en une phrase

L'application n'a pas un design, elle en a plusieurs : chaque module a été construit avec ses propres boutons, ses
propres en-têtes, ses propres couleurs et ses propres pictogrammes. La palette du livre a été posée le 17/11, mais
elle ne suffit pas tant que les composants restent disparates.

## Ce que mesure le code

| Mesure | Valeur | Cible |
|---|---|---|
| Émojis dans l'interface | **217** dans **44** fichiers (✓ ⚡ 🎯 🤝 ✨ 🔒 📅 💎 🌱 🏗️…) | 0 (icônes Lucide uniquement) |
| Classes de boutons différentes | **64** (`ks-btn`, `btn`, `btn-add`, `btn-toolbar`, `action-btn`, `p-btn`, `cp-btn`, `og-btn`, `k-btn`, `bilan-btn`, `btn-hero-primary`…) | 1 composant `ks-btn` + 4 variantes |
| En-têtes de page | 3 façons : `_page_header` (13 pages), `ks-header` écrit à la main (21), « hero » maison (6) | 1 composant |
| Styles écrits dans les balises (`style="…"`) | **3 805** | quelques dizaines |
| Couleurs écrites en dur | **1 631** | variables (`--primary`, `--plan-*`…) |
| Rayons d'angle différents | **40** (12px, 8px, 0.75rem, 1rem, 9999px, 99px, 999px…) | 4 jetons (`--r-sm/md/lg/full`) |

## Ce que montrent les écrans

**Pictogrammes : deux systèmes mélangés.** Le menu principal et le menu « Plus » du Plan de Vie utilisent des
icônes au trait (Lucide) ; les titres de section du même Plan de Vie (« 📅 Mon Échéancier », « 🗺️ Ma Carte
Stratégique », « 🔍 Analyse de Cohérence », « 🕰️ Historique »), la légende de la carte, l'espace couple
(« 🧭 Notre vision », « 💎 Nos valeurs »…), la page d'accueil publique et les étiquettes d'état des plans
(🏗️ 🔄 ⚪ 💪) utilisent des émojis. D'un clic à l'autre, on change de langage visuel : c'est ce que vous avez vu.

**Couleurs hors palette encore visibles.** Le grand bandeau de l'accueil reste un dégradé vert → **bleu**
(`#0369a1`) avec des halos **indigo** et **ambre** ; la référence du verset est en bleu ciel. Les rapports de
tests ont leurs propres couleurs (DISC, ennéagramme), ce qui est voulu, mais leurs encadrés « À garder en tête »
restent ambre.

**Titres à l'anglaise.** « Ma Carte Stratégique », « Objectifs Spécifiques », « Mon Échéancier », « Analyse de
Cohérence » : en français, seule la première lettre prend une majuscule.

**Hiérarchie des actions.** Plusieurs écrans proposent 3 à 4 boutons de même poids (Plan de Vie : « Continuer avec
l'assistant », « Analyse », « Plus » ; Bilan : semaine, mois, jour, saison), contrairement à la règle « une seule
action principale par écran » de `docs/design.md`.

**Accueil.** Le bandeau occupe tout le premier écran (salutation, verset, « 0/0 rituels du jour ») ; la prochaine
action utile (semaine du parcours, bilan du jour) n'apparaît qu'en dessous.

**Encart du livre mal choisi sur le Plan de Vie.** Il cite « Comment lire ce livre » (« Tu peux lire ce livre
seul… »), qui parle du livre et non de l'écran.

**Téléphone.** Barre du bas sans libellés (sauf l'onglet actif) : les icônes seules ne disent pas où l'on va.

## Plan de correction

### Lot A — Cohérence immédiate (≈ 1 jour)
1. **Zéro émoji dans l'interface** : remplacer les 217 émojis par des icônes Lucide (ou les supprimer quand le texte
   suffit). Carte stratégique : formes et couleurs au lieu d'émojis dans les nœuds.
2. **Bandeau d'accueil** et toutes les couleurs résiduelles hors palette (bleu, indigo, ambre) → vert et or du livre.
3. **Titres en casse française** partout.
4. **Encarts du livre** : vérifier que chaque écran cite le bon chapitre.

### Lot B — Un seul jeu de composants (≈ 2 jours)
5. **Boutons** : 64 classes → `ks-btn` (`--primary`, `--secondary`, `--ghost`, `--danger`, `--sm`).
6. **En-têtes** : toutes les pages sur `shared/_page_header.html.twig`.
7. **Cartes, onglets, badges, champs** : `ks-card`, `ks-tabs`, `ks-badge`, `ks-input` partout ; rayons et ombres par jetons.
8. **Styles en ligne et couleurs en dur** : migration progressive vers les classes et variables, en commençant par
   les pages les plus vues (accueil, Plan de Vie, vue d'ensemble, Bilan). Les pages « visualiser/imprimer », les PDF
   et les emails gardent leurs couleurs écrites (les variables n'y fonctionnent pas).

### Lot C — Ergonomie (≈ 1 à 2 jours)
9. **Accueil** : une carte « Ma prochaine étape » en tête (semaine du parcours, bilan du jour le soir, revue le
   dimanche), bandeau plus compact.
10. **Bilan → « Mes rythmes »** : jour, semaine, mois, saison, année sur une seule page (figure des revues du livre).
11. **Une action principale par écran** : les autres en boutons secondaires ou dans « Plus ».
12. **Téléphone** : libellés sous les icônes de la barre du bas.

### Garde-fous à ajouter
- Un test qui refuse tout émoji dans les gabarits de l'application (hors emails, PDF, contenus des tests).
- Un test qui refuse les classes de boutons hors `ks-btn`.
- `docs/design.md` mis à jour à la fin de chaque lot.
