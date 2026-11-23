<?php

namespace App\Couple\Service;

use App\Contenu\Service\SiteTexts;

/**
 * Les textes du Sanctuaire du Couple tels qu'ils s'affichent : texte publié dans Admin › Textes du site, sinon
 * texte d'origine de CoupleSanctuaryCatalog (un administrateur en mode aperçu voit ses brouillons).
 * Même forme de données que les constantes du catalogue.
 */
final class CoupleSanctuaryTexts
{
    public function __construct(private readonly SiteTexts $texts)
    {
    }

    /** @return array{reference: string, text: string} */
    public function dailyVerse(?\DateTimeImmutable $date = null): array
    {
        $index = (int) ($date ?? new \DateTimeImmutable())->format('z') % \count(CoupleSanctuaryCatalog::DAILY_VERSES);
        $v = CoupleSanctuaryCatalog::DAILY_VERSES[$index];
        $n = $index + 1;

        return [
            'reference' => $this->t("couple.verset.$n.reference", $v['reference']),
            'text' => $this->t("couple.verset.$n.texte", $v['text']),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function challenges(): array
    {
        $out = [];
        foreach (array_keys(CoupleSanctuaryCatalog::CHALLENGES) as $code) {
            $out[$code] = $this->challenge($code);
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function challenge(string $code): ?array
    {
        $c = CoupleSanctuaryCatalog::CHALLENGES[$code] ?? null;
        if (null === $c) {
            return null;
        }
        $c['title'] = $this->t("couple.defi.$code.titre", $c['title']);
        $c['subtitle'] = $this->t("couple.defi.$code.sous_titre", $c['subtitle']);
        foreach ($c['days'] as $n => $prompt) {
            $c['days'][$n] = $this->t("couple.defi.$code.jour.$n", $prompt);
        }

        return $c;
    }

    /** @return array<string, array<string, mixed>> */
    public function meditations(): array
    {
        $out = [];
        foreach (array_keys(CoupleSanctuaryCatalog::MEDITATIONS) as $code) {
            $out[$code] = $this->meditation($code);
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function meditation(string $code): ?array
    {
        $m = CoupleSanctuaryCatalog::MEDITATIONS[$code] ?? null;
        if (null === $m) {
            return null;
        }
        $m['title'] = $this->t("couple.meditation.$code.titre", $m['title']);
        $m['subtitle'] = $this->t("couple.meditation.$code.sous_titre", $m['subtitle']);
        foreach ($m['steps'] as $n => $step) {
            foreach (['title' => 'titre', 'verse' => 'verset', 'reflection' => 'reflexion', 'question' => 'question'] as $key => $fr) {
                $m['steps'][$n][$key] = $this->t("couple.meditation.$code.etape.$n.$fr", $step[$key]);
            }
        }

        return $m;
    }

    /**
     * Signaux au conjoint : libellé du bouton, titre et message ({prenom} = prénom de l'expéditeur).
     *
     * @return array<string, array{label: string, title: string, body: string}>
     */
    public function pings(): array
    {
        $out = [];
        foreach (CoupleSanctuaryCatalog::PING_PRESETS as $code => $p) {
            $out[$code] = [
                'label' => $this->t("couple.signal.$code.bouton", $p['label']),
                'title' => $this->t("couple.signal.$code.titre", str_replace('%s', '{prenom}', $p['title'])),
                'body' => $this->t("couple.signal.$code.message", str_replace('%s', '{prenom}', $p['body'])),
            ];
        }

        return $out;
    }

    private function t(string $code, string $default): string
    {
        return $this->texts->get($code, $default);
    }
}
