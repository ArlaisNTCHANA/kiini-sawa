<?php

namespace App\Personnalite\Service;

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
            'INTELLIGENCES' => $this->calcIntelligences($answers),
            'TALENT'        => $this->calcTalent($answers),
            'EQUILIBRE'     => $this->calcEquilibre($answers),
            'HYGIENE'       => $this->calcHygiene($answers),
            default         => ['result' => 'N/A', 'insights' => ''],
        };
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
    // EQUILIBRE DE VIE — 8 domaines (Roue de la vie)
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
