<?php

namespace App\Contenu\Repository;

use App\Contenu\Entity\SiteText;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SiteText> */
class SiteTextRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteText::class);
    }

    /** @return array<string, SiteText> tous les textes modifiés, par code (une seule requête par page) */
    public function map(): array
    {
        $out = [];
        foreach ($this->findAll() as $t) {
            $out[$t->getCode()] = $t;
        }

        return $out;
    }
}
