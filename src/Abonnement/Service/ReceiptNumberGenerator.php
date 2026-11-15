<?php

namespace App\Abonnement\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Numéros de reçu KS-AAAA-00001, uniques et séquentiels par année,
 * communs aux abonnements individuels et aux commandes de places des organisations.
 */
final class ReceiptNumberGenerator
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function next(): string
    {
        $year = date('Y');
        $conn = $this->em->getConnection();
        $last = max(
            (string) $conn->fetchOne('SELECT MAX(receipt_number) FROM transaction WHERE receipt_number LIKE ?', ["KS-$year-%"]),
            (string) $conn->fetchOne('SELECT MAX(receipt_number) FROM seat_order WHERE receipt_number LIKE ?', ["KS-$year-%"]),
        );

        return sprintf('KS-%s-%05d', $year, $last ? ((int) substr($last, -5)) + 1 : 1);
    }
}
