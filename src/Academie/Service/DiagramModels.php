<?php

namespace App\Academie\Service;

/**
 * Modèles de l'atelier de schémas : ce que le fondateur peut composer sans code, toujours aux couleurs du site.
 *
 * Contenu commun : un titre, une note facultative et des éléments `{icon, title, text, detail, tone}` ; chaque modèle
 * donne un sens à ces champs (libellés de l'atelier) et borne leur nombre. Tout est vérifié ici : icône et teinte dans
 * des listes fermées, longueurs limitées ; le texte est échappé à l'affichage.
 */
final class DiagramModels
{
    public const MAX_TITLE = 80;
    public const MAX_TEXT = 300;
    public const MAX_DETAIL = 120;
    public const MAX_NOTE = 200;

    /**
     * code => [nom, description, min, max, libellés {title, text, detail}, utilise l'icône, utilise la teinte, champ « label »].
     *
     * @var array<string, array{name: string, help: string, min: int, max: int, fields: array<string, string>, icon: bool, tone: bool, label: ?string}>
     */
    public const MODELS = [
        'cards' => ['name' => 'Cartes', 'help' => 'De 2 à 6 cartes côte à côte (une par ligne sur téléphone). Ex. : les cinq principes, les quatre dérives.',
            'min' => 2, 'max' => 6, 'fields' => ['title' => 'Titre de la carte', 'text' => 'Texte', 'detail' => 'Petite ligne en dessous (facultatif)'], 'icon' => true, 'tone' => true, 'label' => null],
        'compare' => ['name' => 'Comparaison', 'help' => 'Deux colonnes face à face. Ex. : l\'usine et la forêt, Chronos et Kairos. Une ligne par point dans le texte.',
            'min' => 2, 'max' => 2, 'fields' => ['title' => 'Titre de la colonne', 'text' => 'Points (un par ligne)', 'detail' => 'Phrase de conclusion (facultatif)'], 'icon' => true, 'tone' => true, 'label' => null],
        'steps' => ['name' => 'Étapes', 'help' => 'Des étapes numérotées, dans l\'ordre. Ex. : le chemin du parcours, une méthode en 4 temps.',
            'min' => 2, 'max' => 8, 'fields' => ['title' => 'Nom de l\'étape', 'text' => 'Explication (facultatif)', 'detail' => 'Précision (facultatif)'], 'icon' => false, 'tone' => false, 'label' => null],
        'rows' => ['name' => 'Si… alors…', 'help' => 'Des lignes « situation → réponse ». Ex. : « Je me sens perdu → la Graine : quel fruit ? »',
            'min' => 2, 'max' => 8, 'fields' => ['title' => 'Situation (à gauche)', 'text' => 'Réponse (à droite)', 'detail' => 'Précision sous la réponse (facultatif)'], 'icon' => true, 'tone' => false, 'label' => null],
        'fraction' => ['name' => 'Fraction', 'help' => 'Une équation en forme de fraction. Ex. : Productivité = ce qui sort ÷ ce qui entre.',
            'min' => 2, 'max' => 2, 'fields' => ['title' => 'Terme (en haut, puis en bas)', 'text' => 'Explication courte (facultatif)', 'detail' => 'Non utilisé'], 'icon' => false, 'tone' => false, 'label' => 'Début de l\'équation (ex. « Productivité = »)'],
        'cycle' => ['name' => 'Cycle', 'help' => 'Des temps qui se succèdent et recommencent. Ex. : les saisons, semer → attendre → récolter.',
            'min' => 3, 'max' => 6, 'fields' => ['title' => 'Nom du temps', 'text' => 'Ce qui s\'y passe', 'detail' => 'Le piège ou la précision (facultatif)'], 'icon' => true, 'tone' => true, 'label' => null],
    ];

    /** Teintes possibles (variables de la palette, jamais de couleur en dur). */
    public const TONES = [
        'neutre' => 'Neutre',
        'vivant' => 'Vert (le vivant)',
        'froid' => 'Bleu gris (la machine)',
        'or' => 'Doré (le fruit, à retenir)',
        'alerte' => 'Rose (le danger)',
    ];

    /** Icônes proposées (Lucide), avec un nom en français pour le choix. */
    public const ICONS = [
        'sprout' => 'Pousse', 'trees' => 'Forêt', 'tree-deciduous' => 'Arbre', 'leaf' => 'Feuille', 'apple' => 'Fruit', 'droplets' => 'Sève, eau',
        'sun' => 'Soleil', 'sunrise' => 'Lever du jour', 'moon' => 'Nuit', 'snowflake' => 'Hiver', 'scissors' => 'Taille', 'compass' => 'Boussole',
        'factory' => 'Usine', 'cog' => 'Engrenage', 'timer' => 'Chronomètre', 'clock' => 'Horloge', 'hourglass' => 'Sablier', 'calendar' => 'Calendrier',
        'battery-low' => 'Batterie faible', 'zap' => 'Énergie', 'flame' => 'Feu', 'heart' => 'Cœur', 'heart-pulse' => 'Santé', 'brain' => 'Esprit',
        'users' => 'Personnes', 'user' => 'Une personne', 'handshake' => 'Alliance', 'house' => 'Maison', 'book-open' => 'Livre, Parole', 'church' => 'Église',
        'target' => 'Cible', 'flag' => 'Objectif', 'mountain' => 'Montagne', 'route' => 'Chemin', 'footprints' => 'Pas', 'key' => 'Clé',
        'lightbulb' => 'Idée', 'sparkles' => 'Étincelles', 'shield' => 'Protection', 'gift' => 'Don', 'hand-heart' => 'Service', 'sofa' => 'Repos',
        'pencil-line' => 'Écrire', 'list-checks' => 'Liste', 'triangle-alert' => 'Attention', 'check' => 'Validé', 'x' => 'Non', 'arrow-right' => 'Flèche',
    ];

    /**
     * Contenu propre et sûr à partir de ce qu'envoie l'atelier.
     *
     * @param array<string, mixed> $input
     *
     * @return array{label: string, note: string, items: list<array{icon: string, title: string, text: string, detail: string, tone: string}>}
     *
     * @throws \InvalidArgumentException message prêt à afficher
     */
    public static function normalize(string $model, array $input): array
    {
        $def = self::MODELS[$model] ?? throw new \InvalidArgumentException('Modèle de schéma inconnu.');
        $items = [];
        foreach (array_values(\is_array($input['items'] ?? null) ? $input['items'] : []) as $raw) {
            if (!\is_array($raw)) {
                continue;
            }
            $item = [
                'icon' => $def['icon'] && isset(self::ICONS[$raw['icon'] ?? '']) ? (string) $raw['icon'] : '',
                'title' => self::clean($raw['title'] ?? '', self::MAX_TITLE),
                'text' => self::clean($raw['text'] ?? '', self::MAX_TEXT, true),
                'detail' => self::clean($raw['detail'] ?? '', self::MAX_DETAIL),
                'tone' => $def['tone'] && isset(self::TONES[$raw['tone'] ?? '']) ? (string) $raw['tone'] : 'neutre',
            ];
            if ('' !== $item['title'] || '' !== $item['text']) {
                $items[] = $item;
            }
        }
        if (\count($items) < $def['min'] || \count($items) > $def['max']) {
            throw new \InvalidArgumentException($def['min'] === $def['max']
                ? sprintf('Le modèle « %s » demande exactement %d éléments remplis.', $def['name'], $def['min'])
                : sprintf('Le modèle « %s » demande de %d à %d éléments remplis.', $def['name'], $def['min'], $def['max']));
        }
        foreach ($items as $i => $item) {
            if ('' === $item['title']) {
                throw new \InvalidArgumentException(sprintf('L\'élément %d n\'a pas de titre.', $i + 1));
            }
        }

        return [
            'label' => null !== $def['label'] ? self::clean($input['label'] ?? '', self::MAX_TITLE) : '',
            'note' => self::clean($input['note'] ?? '', self::MAX_NOTE),
            'items' => $items,
        ];
    }

    /** Un exemple pour démarrer, propre à chaque modèle. */
    public static function starter(string $model): array
    {
        $item = fn (string $title, string $text = '', string $icon = '', string $tone = 'neutre', string $detail = '') => compact('icon', 'title', 'text', 'detail', 'tone');

        return match ($model) {
            'compare' => ['label' => '', 'note' => '', 'items' => [$item('Avant', "Un point\nUn autre point", 'factory', 'froid'), $item('Après', "Un point\nUn autre point", 'trees', 'vivant')]],
            'steps' => ['label' => '', 'note' => '', 'items' => [$item('Première étape', 'Ce que l\'on fait'), $item('Deuxième étape', 'Ce que l\'on fait'), $item('Troisième étape', 'Ce que l\'on fait')]],
            'rows' => ['label' => '', 'note' => '', 'items' => [$item('Je me sens perdu', 'la Graine', 'compass', 'neutre', 'Quel fruit ?'), $item('Je me sens épuisé', 'la Sève', 'droplets', 'neutre', 'Qui suis-je ?')]],
            'fraction' => ['label' => 'Productivité =', 'note' => '', 'items' => [$item('Ce qui sort', 'chiffres, projets aboutis'), $item('Ce qui entre', 'temps, énergie, attention')]],
            'cycle' => ['label' => '', 'note' => '', 'items' => [$item('Semer', 'Travailler dans l\'ombre', 'sprout'), $item('Attendre', 'Ce qui pousse est invisible', 'hourglass'), $item('Récolter', 'Le fruit', 'apple', 'or')]],
            default => ['label' => '', 'note' => '', 'items' => [$item('Première carte', 'Un texte court', 'sprout', 'vivant'), $item('Deuxième carte', 'Un texte court', 'sun', 'or'), $item('Troisième carte', 'Un texte court', 'scissors')]],
        };
    }

    private static function clean(mixed $value, int $max, bool $multiline = false): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", strip_tags((string) $value));
        $text = $multiline ? preg_replace("/\n{3,}/", "\n\n", $text) : preg_replace('/\s+/u', ' ', $text);

        return mb_substr(trim((string) $text), 0, $max);
    }
}
