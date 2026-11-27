<?php

namespace App\Boussole\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Compteurs du jour de la Boussole pour les visiteurs (27/11/2026, Admin › Les chiffres › La Boussole 360).
 *
 * Une passation de visiteur n'est enregistrée que s'il s'inscrit ou se connecte ensuite : ces compteurs disent combien
 * l'ont passée sans compte, et combien l'ont gardée. Ni adresse IP, ni cookie, ni donnée personnelle.
 */
#[ORM\Entity]
#[ORM\Table(name: 'boussole_daily')]
class BoussoleDaily
{
    #[ORM\Id]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $day;

    /** Passations terminées par un visiteur sans compte (ou dont l'adresse n'est pas vérifiée). */
    #[ORM\Column(options: ['default' => 0])]
    private int $visitors = 0;

    /** Passations de visiteurs gardées par un compte créé dans les dernières 24 heures. */
    #[ORM\Column(options: ['default' => 0])]
    private int $newMembers = 0;

    /** Passations de visiteurs gardées en se connectant à un compte plus ancien. */
    #[ORM\Column(name: 'returning_members', options: ['default' => 0])] // « returning » est un mot clé de MariaDB
    private int $returning = 0;

    public function getDay(): \DateTimeImmutable { return $this->day; }
    public function getVisitors(): int { return $this->visitors; }
    public function getNewMembers(): int { return $this->newMembers; }
    public function getReturning(): int { return $this->returning; }
}
