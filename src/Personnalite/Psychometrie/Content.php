<?php

namespace App\Personnalite\Psychometrie;

use App\Personnalite\Service\SelfAssessmentProfiles;

/**
 * Point d'entrée unique des textes des tests : le contenu du code (référence), complété par les modifications
 * faites dans l'administration (table content_override, chargées une fois par requête).
 *
 * Seuls les textes sont modifiables : les clés, les lettres, les dimensions, le sens des questions, les couleurs
 * et les questions IPIP (issues de la recherche) restent dans le code.
 */
final class Content
{
    /** Clés jamais modifiables (structure, couleurs, calcul). */
    private const LOCKED_KEYS = ['color', 'light', 'dark', 'center', 'types', 'gifts', 'code'];

    /** @var array<string, string>|null */
    private static ?array $overrides = null;
    private static ?\Closure $loader = null;
    /** @var array<string, array> */
    private static array $cache = [];

    /**
     * Sources modifiables : clé => [test, libellé, chemins verrouillés (expressions régulières sur le chemin interne)].
     */
    public const SOURCES = [
        'disc.words' => ['DISC', 'Questionnaire : les 24 blocs de mots', []],
        'disc.profiles' => ['DISC', 'Les 16 profils (portraits, forces, figures bibliques, actions…)', []],
        'disc.community' => ['DISC', 'Les 16 profils dans la famille élargie et la communauté', []],
        'disc.dimensions' => ['DISC', 'Les 4 dimensions (D, I, S, C)', []],
        'disc.perception' => ['DISC', 'Perception croisée', []],
        'disc.interaction' => ['DISC', 'Interagir avec chaque profil', []],
        'disc.observed' => ['DISC', 'Descriptions vues par les proches (confirmation)', []],
        'disc.adaptation' => ['DISC', 'Lecture combinée naturel / adapté (12 cas)', []],
        'disc.verses' => ['DISC', 'Versets par dimension', []],
        'disc.meditations' => ['DISC', 'Questions à méditer', []],
        'enn.items' => ['Ennéagramme', 'Questionnaire : les 72 affirmations', ['/^\d+\.0$/']],
        'enn.types' => ['Ennéagramme', 'Les 9 types (nom, motivation, peur, don…)', []],
        'enn.profiles' => ['Ennéagramme', 'Les 9 types : rapport détaillé', []],
        'enn.community' => ['Ennéagramme', 'Les 9 types dans la famille élargie et la communauté', []],
        'enn.wings' => ['Ennéagramme', 'Les 18 ailes', []],
        'enn.centers' => ['Ennéagramme', 'Les 3 centres', []],
        'gifts.items' => ['Dons spirituels', 'Questionnaire : les 60 affirmations', ['/^\d+\.0$/']],
        'gifts.gifts' => ['Dons spirituels', 'Les 15 dons (nom, description, service)', []],
        'gifts.profiles' => ['Dons spirituels', 'Les 15 dons : rapport détaillé', []],
        'gifts.families' => ['Dons spirituels', 'Les 3 familles de dons', []],
        'bigfive.domains' => ['Big Five', 'Les 5 dimensions (définition et lecture)', []],
        'bigfive.levels' => ['Big Five', 'Les 5 dimensions par niveau (forces, vie spirituelle…)', []],
        'bigfive.facets' => ['Big Five', 'Les 29 facettes', []],
        'type.types' => ['Type de fonctionnement', 'Les 16 types (nom et résumé)', []],
        'type.profiles' => ['Type de fonctionnement', 'Les 16 types : rapport détaillé', []],
        'type.community' => ['Type de fonctionnement', 'Les 16 types dans la famille élargie et la communauté', []],
        'type.axes' => ['Type de fonctionnement', 'Les 8 préférences', []],
        'type.families' => ['Type de fonctionnement', 'Les 4 familles', []],
        'self.equilibre' => ['Équilibre et Hygiène de Vie', 'Équilibre de Vie : les 8 domaines', []],
        'self.hygiene' => ['Équilibre et Hygiène de Vie', 'Hygiène de Vie : les 6 axes', []],
        'synth.vocations' => ['Mon profil KIINI SAWA', 'Les 12 zones de vocation (DISC × famille de dons)', []],
        'synth.axes' => ['Mon profil KIINI SAWA', 'Les 3 axes de convergence', []],
        // Les citations bibliques (LSG), les noms servant à rattacher une fiche à un profil et les étiquettes restent dans le code
        'figures.library' => ['Personnages bibliques', 'Les 32 fiches : profil et indices, récit, fragilité, grâce, Christ au centre', ['/\.quote\./', '/\.quotes\./', '/\.aliases\./', '/\.tags\./']],
    ];

    /** Contenu de référence (code). */
    public static function defaults(string $source): array
    {
        return match ($source) {
            'disc.words' => DiscBank::BLOCKS,
            'disc.profiles' => DiscCombos::PROFILES,
            'disc.community' => DiscCombos::COMMUNITY,
            'disc.dimensions' => DiscProfiles::PROFILES,
            'disc.perception' => DiscProfiles::PERCEPTION,
            'disc.interaction' => DiscProfiles::INTERACTION,
            'disc.observed' => DiscProfiles::OBSERVED,
            'disc.adaptation' => DiscProfiles::ADAPTATION,
            'disc.verses' => DiscCombos::VERSES,
            'disc.meditations' => DiscCombos::MEDITATIONS,
            'enn.items' => EnneagramBank::ITEMS,
            'enn.types' => EnneagramBank::TYPES,
            'enn.profiles' => EnneagramProfiles::TYPES,
            'enn.community' => EnneagramProfiles::COMMUNITY,
            'enn.wings' => EnneagramProfiles::WINGS,
            'enn.centers' => EnneagramProfiles::CENTERS,
            'gifts.items' => SpiritualGiftsBank::ITEMS,
            'gifts.gifts' => SpiritualGiftsBank::GIFTS,
            'gifts.profiles' => GiftProfiles::GIFTS,
            'gifts.families' => GiftProfiles::FAMILIES,
            'bigfive.domains' => Interpretations::DOMAINS,
            'bigfive.levels' => BigFiveProfiles::DOMAINS,
            'bigfive.facets' => Interpretations::FACETS,
            'type.types' => Interpretations::TYPES,
            'type.profiles' => TypeProfiles::TYPES,
            'type.community' => TypeProfiles::COMMUNITY,
            'type.axes' => Interpretations::TYPE_AXES,
            'type.families' => TypeProfiles::FAMILIES,
            'self.equilibre' => SelfAssessmentProfiles::EQUILIBRE,
            'self.hygiene' => SelfAssessmentProfiles::HYGIENE,
            'synth.vocations' => SynthesisContent::VOCATIONS,
            'synth.axes' => SynthesisContent::AXES,
            'figures.library' => BiblicalFigures::raw(),
        };
    }

    /** Contenu affiché : référence + modifications de l'administrateur. */
    public static function get(string $source): array
    {
        if (isset(self::$cache[$source])) {
            return self::$cache[$source];
        }
        $data = self::defaults($source);
        $prefix = $source.'.';
        foreach (self::overrides() as $path => $text) {
            if (str_starts_with($path, $prefix) && self::isEditable($source, substr($path, \strlen($prefix)))) {
                self::set($data, explode('.', substr($path, \strlen($prefix))), $text);
            }
        }

        return self::$cache[$source] = $data;
    }

    /**
     * Textes modifiables d'une source, à plat : chemin interne => texte de référence.
     *
     * @return array<string, string>
     */
    public static function leaves(string $source, ?string $entry = null): array
    {
        $data = self::defaults($source);
        if (null !== $entry) {
            $data = [$entry => $data[$entry] ?? []];
        }
        $out = [];
        $walk = function (array $node, string $path) use (&$walk, &$out, $source): void {
            foreach ($node as $k => $v) {
                $p = '' === $path ? (string) $k : $path.'.'.$k;
                if (\is_array($v)) {
                    $walk($v, $p);
                } elseif (\is_string($v) && self::isEditable($source, $p)) {
                    $out[$p] = $v;
                }
            }
        };
        $walk($data, '');

        return $out;
    }

    public static function isEditable(string $source, string $path): bool
    {
        if (!isset(self::SOURCES[$source])) {
            return false;
        }
        $parts = explode('.', $path);
        if (array_intersect($parts, self::LOCKED_KEYS)) {
            return false;
        }
        foreach (self::SOURCES[$source][2] as $regex) {
            if (preg_match($regex, $path)) {
                return false;
            }
        }
        // Le chemin doit exister dans la référence et désigner un texte
        $node = self::defaults($source);
        foreach ($parts as $p) {
            if (!\is_array($node) || !\array_key_exists($p, $node)) {
                return false;
            }
            $node = $node[$p];
        }

        return \is_string($node);
    }

    /** Branché par OverrideLoaderSubscriber à chaque requête (chargement paresseux). */
    public static function setLoader(?\Closure $loader): void
    {
        self::$loader = $loader;
        self::reset();
    }

    public static function reset(): void
    {
        self::$overrides = null;
        self::$cache = [];
    }

    private static function overrides(): array
    {
        if (null === self::$overrides) {
            try {
                self::$overrides = self::$loader ? (self::$loader)() : [];
            } catch (\Throwable) {
                self::$overrides = []; // Base indisponible : on garde le texte d'origine
            }
        }

        return self::$overrides;
    }

    private static function set(array &$data, array $keys, string $value): void
    {
        $ref = &$data;
        foreach ($keys as $k) {
            $ref = &$ref[$k];
        }
        $ref = $value;
    }
}
