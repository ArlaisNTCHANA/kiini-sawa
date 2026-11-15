<?php

namespace App\Compte\Service;

use App\Compte\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Suppression définitive d'un compte (droit à l'effacement).
 *
 * Toutes les tables liées à l'utilisateur ont une clé étrangère ON DELETE CASCADE
 * (directement ou via leur parent) : supprimer la ligne `user` supprime tout le reste
 * en une seule requête, sans oublier un module. Le test AccountDeletionTest vérifie
 * qu'aucune donnée ne survit.
 *
 * Toute nouvelle relation vers User (ou vers une entité qui appartient à un utilisateur)
 * doit donc déclarer onDelete: 'CASCADE' sur sa JoinColumn.
 */
final class AccountDeleter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function delete(User $user): void
    {
        $id = $user->getId();
        $this->em->getConnection()->transactional(
            fn ($connection) => $connection->delete('`user`', ['id' => $id])
        );
        $this->em->clear();
        $this->logger->info('Compte supprimé à la demande de l\'utilisateur', ['userId' => $id]);
    }
}
