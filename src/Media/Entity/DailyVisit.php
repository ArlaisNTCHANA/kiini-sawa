<?php

namespace App\Media\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Nombre de visites de la vitrine par jour (début de l'entonnoir : visite, inscription, onboarding, parcours).
 * Un simple compteur : ni adresse IP, ni cookie, ni donnée personnelle.
 */
#[ORM\Entity]
#[ORM\Table(name: 'daily_visit')]
class DailyVisit
{
    #[ORM\Id]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $day;

    #[ORM\Column(options: ['default' => 0])]
    private int $visits = 0;

    public function getDay(): \DateTimeImmutable { return $this->day; }

    public function getVisits(): int { return $this->visits; }
}
