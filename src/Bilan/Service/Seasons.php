<?php

namespace App\Bilan\Service;

/**
 * Les saisons d'une vie, mot pour mot du livre (chapitre 11 « Saisons & Échéancier »).
 * Une saison dure généralement trois mois et porte un thème dominant ; sa revue regarde le thème, les jalons et le
 * choix de la saison suivante (chapitre 23).
 */
final class Seasons
{
    public const MONTHS = 3;

    public const KINDS = [
        'SEMIS' => ['label' => 'Semis', 'what' => 'Apprentissage, travail dans l\'ombre, peu de résultats visibles', 'work' => 'Semer avec fidélité, se former, bâtir les fondations', 'trap' => 'Se décourager de ne rien voir pousser'],
        'CROISSANCE' => ['label' => 'Croissance', 'what' => 'Les actions commencent à porter du fruit', 'work' => 'Entretenir, protéger, rester constant', 'trap' => 'Se disperser en voulant tout saisir'],
        'RECOLTE' => ['label' => 'Récolte', 'what' => 'Succès, visibilité, abondance', 'work' => 'Célébrer, rendre grâce, partager', 'trap' => 'S\'attribuer la gloire, s\'installer'],
        'HIVER' => ['label' => 'Hiver', 'what' => 'Bilan, dépouillement, pause apparente', 'work' => 'Se reposer, épurer, préparer la suite', 'trap' => 'Confondre repos et échec'],
    ];
}
