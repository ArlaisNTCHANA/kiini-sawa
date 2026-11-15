<?php

namespace App\Service;

class PersonalityScoreService
{
    /**
     * Calculate the result from raw answers.
     * @param string $type  e.g. 'MBTI', 'DISC', 'ENNEAGRAMME', 'BIG5', 'INTELLIGENCES', 'TALENT', 'EQUILIBRE', 'HYGIENE'
     * @param array  $answers  [questionId => answerValue, ...]
     * @return array ['result' => string, 'insights' => string]
     */
    public function calculate(string $type, array $answers): array
    {
        $type = strtoupper($type);
        return match ($type) {
            'MBTI'          => $this->calcMbti($answers),
            'DISC'          => $this->calcDisc($answers),
            'ENNEAGRAMME'   => $this->calcEnneagramme($answers),
            'BIG5'          => $this->calcBig5($answers),
            'INTELLIGENCES' => $this->calcIntelligences($answers),
            'TALENT'        => $this->calcTalent($answers),
            'EQUILIBRE'     => $this->calcEquilibre($answers),
            'HYGIENE'       => $this->calcHygiene($answers),
            default         => ['result' => 'N/A', 'insights' => ''],
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MBTI — 4 dimensions binaires
    // Chaque question a une catégorie (E/I, S/N, T/F, J/P) et une valeur (1 ou 2)
    // ─────────────────────────────────────────────────────────────────────────
    private function calcMbti(array $answers): array
    {
        $scores = ['E' => 0, 'I' => 0, 'S' => 0, 'N' => 0, 'T' => 0, 'F' => 0, 'J' => 0, 'P' => 0];

        foreach ($answers as $dim => $val) {
            if (isset($scores[$dim])) {
                $scores[$dim] += (int)$val;
            }
        }

        $type =
            ($scores['E'] >= $scores['I'] ? 'E' : 'I') .
            ($scores['S'] >= $scores['N'] ? 'S' : 'N') .
            ($scores['T'] >= $scores['F'] ? 'T' : 'F') .
            ($scores['J'] >= $scores['P'] ? 'J' : 'P');

        $labels = [
            'INTJ' => 'Architecte — Stratège visionnaire et indépendant.',
            'INTP' => 'Logicien — Penseur innovant et analytique.',
            'ENTJ' => 'Commandant — Leader naturel, déterminé et ambitieux.',
            'ENTP' => 'Innovateur — Débatteur curieux et ingénieux.',
            'INFJ' => 'Avocat — Idéaliste doux mais déterminé.',
            'INFP' => 'Médiateur — Idéaliste, créatif et empathique.',
            'ENFJ' => 'Protagoniste — Leader charismatique et inspirant.',
            'ENFP' => 'Inspirateur — Enthousiaste, créatif, sociable.',
            'ISTJ' => 'Logisticien — Fiable, méthodique et discipliné.',
            'ISFJ' => 'Défenseur — Protecteur chaleureux et dévoué.',
            'ESTJ' => 'Directeur — Administrateur efficace et pragmatique.',
            'ESFJ' => 'Consul — Attentionné et très sociable.',
            'ISTP' => 'Virtuose — Expérimentateur pragmatique.',
            'ISFP' => 'Aventurier — Artiste flexible et charmant.',
            'ESTP' => 'Entrepreneur — Dynamo perspicace et direct.',
            'ESFP' => 'Amuseur — Spontané, énergie, sens du jeu.',
        ];

        return [
            'result'   => $type,
            'insights' => $labels[$type] ?? "Profil MBTI : $type",
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DISC — 4 styles comportementaux
    // Chaque réponse porte un score pour D, I, S ou C
    // ─────────────────────────────────────────────────────────────────────────
    private function calcDisc(array $answers): array
    {
        $scores = ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0];
        foreach ($answers as $dim => $val) {
            if (isset($scores[$dim])) {
                $scores[$dim] += (int)$val;
            }
        }

        arsort($scores);
        $top = array_keys($scores)[0];
        $second = array_keys($scores)[1];
        $result = $scores[$top] === $scores[$second] ? $top . $second : $top;

        $max = max($scores);
        $total = array_sum($scores) ?: 1;
        $pct = array_map(fn($v) => round(($v / $total) * 100), $scores);

        $labels = [
            'D'  => 'Dominant — Direct, décidé, orienté résultats.',
            'I'  => 'Influent — Enthousiaste, sociable, communicant.',
            'S'  => 'Stable — Patient, fiable, diplomate.',
            'C'  => 'Consciencieux — Analytique, précis, perfectionniste.',
            'DI' => 'Dominant/Influent — Leader charismatique orienté action.',
            'DC' => 'Dominant/Consciencieux — Stratège rigoureux.',
            'IS' => 'Influent/Stable — Communicant empathique.',
            'SC' => 'Stable/Consciencieux — Perfectionniste méthodique.',
            'ID' => 'Influent/Dominant — Entrepreneur enthousiaste.',
            'CD' => 'Consciencieux/Dominant — Analyste décideur.',
            'SI' => 'Stable/Influent — Médiateur chaleureux.',
            'CS' => 'Consciencieux/Stable — Expert fiable et précis.',
        ];

        return [
            'result'   => json_encode($pct),
            'insights' => $labels[$result] ?? "Profil DISC : $result",
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ENNEAGRAMME — 9 types
    // ─────────────────────────────────────────────────────────────────────────
    private function calcEnneagramme(array $answers): array
    {
        $scores = array_fill(1, 9, 0);
        foreach ($answers as $type => $val) {
            if (is_numeric($type) && isset($scores[(int)$type])) {
                $scores[(int)$type] += (int)$val;
            }
        }

        arsort($scores);
        $topType = (string)array_key_first($scores);

        $labels = [
            '1' => 'Type 1 — Le Réformateur. Perfectionniste, idéaliste, éthique.',
            '2' => 'Type 2 — L\'Altruiste. Généreux, caring, cherche à être aimé.',
            '3' => 'Type 3 — Le Battant. Ambitieux, performant, orienté succès.',
            '4' => 'Type 4 — L\'Individualiste. Créatif, romantique, introspectif.',
            '5' => 'Type 5 — L\'Investigateur. Analytique, observateur, réservé.',
            '6' => 'Type 6 — Le Loyaliste. Engagé, anxieux, cherche la sécurité.',
            '7' => 'Type 7 — L\'Enthousiaste. Spontané, curieux, positif.',
            '8' => 'Type 8 — Le Challenger. Puissant, décisif, protecteur.',
            '9' => 'Type 9 — Le Pacificateur. Accommodant, stable, médiateur.',
        ];

        return [
            'result'   => $topType,
            'insights' => $labels[$topType] ?? "Type Ennéagramme : $topType",
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BIG5 — 5 dimensions OCEAN (scores de 0 à 10)
    // ─────────────────────────────────────────────────────────────────────────
    private function calcBig5(array $answers): array
    {
        $dims = ['O' => [], 'C' => [], 'E' => [], 'A' => [], 'N' => []];
        foreach ($answers as $dim => $val) {
            if (isset($dims[$dim])) {
                $dims[$dim][] = (int)$val;
            }
        }

        $scores = [];
        foreach ($dims as $k => $vals) {
            $scores[$k] = count($vals) > 0 ? round(array_sum($vals) / count($vals) * 10) : 50;
        }

        arsort($scores);
        $dominant = array_key_first($scores);
        $pct = array_map(fn($v) => min(100, max(0, $v)), $scores);

        return [
            'result'   => json_encode($pct),
            'insights' => "Profil Big Five — Dominante : $dominant (" . $this->big5Label($dominant) . ")",
        ];
    }

    private function big5Label(string $dim): string
    {
        return match ($dim) {
            'O' => 'Ouverture à l\'expérience',
            'C' => 'Conscienciosité',
            'E' => 'Extraversion',
            'A' => 'Agréabilité',
            'N' => 'Névrosisme / Sensibilité',
            default => $dim,
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INTELLIGENCES MULTIPLES — 8 types (Gardner)
    // ─────────────────────────────────────────────────────────────────────────
    private function calcIntelligences(array $answers): array
    {
        $dims = [
            'linguistique'       => [],
            'logique'            => [],
            'spatiale'           => [],
            'musicale'           => [],
            'kinesthesique'      => [],
            'naturaliste'        => [],
            'interpersonnelle'   => [],
            'intrapersonnelle'   => [],
        ];

        foreach ($answers as $dim => $val) {
            if (isset($dims[$dim])) {
                $dims[$dim][] = (int)$val;
            }
        }

        $scores = [];
        foreach ($dims as $k => $vals) {
            $scores[$k] = count($vals) > 0 ? round(array_sum($vals) / count($vals) * 10) : 50;
        }

        arsort($scores);
        $top = array_slice($scores, 0, 3, true);
        $topNames = implode(', ', array_map('ucfirst', array_keys($top)));

        return [
            'result'   => json_encode($scores),
            'insights' => "Vos intelligences dominantes : $topNames.",
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TALENT — 3 axes (don naturel, réaction sous pression, plaisir)
    // ─────────────────────────────────────────────────────────────────────────
    private function calcTalent(array $answers): array
    {
        $axes = [
            'spontane'  => [],
            'pression'  => [],
            'plaisir'   => [],
        ];

        foreach ($answers as $dim => $val) {
            if (isset($axes[$dim])) {
                $axes[$dim][] = (int)$val;
            }
        }

        $scores = [];
        foreach ($axes as $k => $vals) {
            $scores[$k] = count($vals) > 0 ? round(array_sum($vals) / count($vals) * 10) : 50;
        }

        return [
            'result'   => json_encode($scores),
            'insights' => 'Architecture des talents identifiée. Consultez votre profil pour les zones de génie.',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EQUILIBRE DE VIE — 8 domaines (Wheel of Life)
    // ─────────────────────────────────────────────────────────────────────────
    private function calcEquilibre(array $answers): array
    {
        $domains = ['sante', 'famille', 'finances', 'carriere', 'spiritualite', 'social', 'personnel', 'loisirs'];
        $scores = [];

        foreach ($domains as $d) {
            $scores[$d] = isset($answers[$d]) ? (int)$answers[$d] : 5;
        }

        $avg = array_sum($scores) / count($scores);

        return [
            'result'   => json_encode($scores),
            'insights' => sprintf('Score global d\'équilibre : %.1f/10. %s',
                $avg,
                $avg < 5 ? 'Des déséquilibres importants à corriger.' :
                ($avg < 7 ? 'Équilibre fragile à renforcer.' : 'Bonne harmonie globale.')
            ),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HYGIENE DE VIE — 6 axes
    // ─────────────────────────────────────────────────────────────────────────
    private function calcHygiene(array $answers): array
    {
        $axes = [
            'physique' => [],
            'mental' => [],
            'relationnel' => [],
            'spirituel' => [],
            'ressources' => [],
            'environnement' => []
        ];

        $subthemesMap = [
            'physique_sommeil' => 'Sommeil & Énergie',
            'physique_nutrition' => 'Nutrition & Digestion',
            'physique_hydratation' => 'Hydratation',
            'physique_activite' => 'Activité physique',
            'mental_pause' => 'Repos cognitif',
            'mental_stress' => 'Gestion du stress',
            'mental_loisir' => 'Calme & Loisirs',
            'mental_sommeil' => 'Clarté mentale',
            'relationnel_ecoute' => 'Écoute active',
            'relationnel_partage' => 'Partage & Moments',
            'relationnel_expression' => 'Expression des besoins',
            'relationnel_soutien' => 'Soutien & Réseau',
            'spirituel_sens' => 'Sens & Utilité',
            'spirituel_valeurs' => 'Alignement',
            'spirituel_paix' => 'Paix intérieure',
            'spirituel_pratique' => 'Pratique personnelle',
            'ressources_temps' => 'Gestion du temps',
            'ressources_projets' => 'Projets personnels',
            'ressources_energie' => 'Limites d’énergie',
            'ressources_recuperation' => 'Récupération',
            'environnement_espace' => 'Clarté de l’espace',
            'environnement_lumiere' => 'Lumière naturelle',
            'environnement_niveau' => 'Niveau sonore',
            'environnement_nature' => 'Contact avec la nature',
        ];

        $subthemeValues = [];

        foreach ($answers as $key => $val) {
            $parts = explode('_', $key);
            if (count($parts) >= 2) {
                $axis = $parts[0];
                if (isset($axes[$axis])) {
                    $axes[$axis][] = (float)$val;
                    
                    $subPrefix = $axis . '_' . $parts[1];
                    $subthemeValues[$axis][$subPrefix][] = (float)$val;
                }
            } else {
                foreach (array_keys($axes) as $axis) {
                    if (str_starts_with($key, $axis)) {
                        $axes[$axis][] = (float)$val;
                        break;
                    }
                }
            }
        }

        $scores = [];
        foreach ($axes as $axis => $vals) {
            $scores[$axis] = count($vals) > 0 ? round(array_sum($vals) / count($vals), 1) : 5.0;
        }

        $details = [];
        foreach ($subthemeValues as $axis => $subPrefixes) {
            $details[$axis] = [];
            foreach ($subPrefixes as $subPrefix => $vals) {
                $label = $subthemesMap[$subPrefix] ?? ucfirst(str_replace('_', ' ', $subPrefix));
                $details[$axis][$label] = count($vals) > 0 ? round(array_sum($vals) / count($vals), 1) : 5.0;
            }
        }

        $avg = array_sum($scores) / count($scores);

        return [
            'result'   => json_encode([
                'axes' => $scores,
                'details' => $details
            ]),
            'insights' => sprintf('Vitalité globale : %.1f/10. %s',
                $avg,
                $avg < 4 ? 'Alerte critique — priorité immédiate.' :
                ($avg < 7 ? 'Équilibre fragile à consolider.' : 'Fondations vitales solides.')
            ),
        ];
    }
}
