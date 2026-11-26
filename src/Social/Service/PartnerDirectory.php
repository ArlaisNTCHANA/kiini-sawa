<?php

namespace App\Social\Service;

use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\User;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use App\Social\Entity\AccountabilityCommitment;
use App\Social\Entity\Connection;
use App\Social\Enum\ShareScope;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Point 6 (26/11/2026) : une seule rubrique « Mes Partenaires de Redevabilité ». Un Partenaire de Redevabilité est une
 * personne, inscrite ou non :
 * - un membre de KIINI SAWA avec qui l'on partage ses plans (Connection) ;
 * - et/ou une personne qui nous interpelle sur un objectif ou un rituel (AccountabilityCommitment, par e-mail).
 * Ce service regroupe les deux par personne, et porte la demande de partage entre membres.
 */
final class PartnerDirectory
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Entitlements $entitlements,
        private readonly Notifier $notifier,
    ) {
    }

    /**
     * Demande de partage à un membre. Ne révèle jamais si un compte existe pour cette adresse : sans compte, rien ne se
     * passe et la réponse est la même qu'en cas de succès.
     *
     * @return string|null message d'erreur, ou null si la demande est partie (ou si l'adresse n'a pas de compte)
     */
    public function requestConnection(User $user, string $email): ?string
    {
        $email = mb_strtolower(trim($email));
        $active = \count(array_filter(array_merge($user->getConnections()->toArray(), $user->getConnectedTo()->toArray()), fn ($c) => 'REJECTED' !== $c->getStatus()));
        $this->entitlements->requireBelowLimit($user, 'connections', $active, 'Limite de Partenaires de Redevabilité de l\'offre gratuite atteinte : passez à PRO pour en inviter d\'autres.');

        if ('' === $email) {
            return 'L\'adresse e-mail est manquante.';
        }
        if ($email === mb_strtolower((string) $user->getEmail())) {
            return 'Choisissez une autre personne que vous-même.';
        }
        $receiver = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$receiver instanceof User) {
            return null;
        }
        $repo = $this->em->getRepository(Connection::class);
        if ($repo->findOneBy(['requester' => $user, 'receiver' => $receiver]) || $repo->findOneBy(['requester' => $receiver, 'receiver' => $user])) {
            return 'Une demande de partage existe déjà avec cette personne.';
        }

        $connection = new Connection();
        $connection->setRequester($user);
        $connection->setReceiver($receiver);
        $connection->setStatus('PENDING');
        $connection->setType('FRIEND');
        $connection->setShareScope(ShareScope::TITLES_ONLY);
        $this->em->persist($connection);
        $this->em->flush();

        $this->notifier->notify($receiver, NotificationType::CONNECTION_REQUEST, 'Nouvelle demande de partage',
            sprintf('%s souhaite que vous soyez son Partenaire de Redevabilité.', $user->getDisplayName()), '/social');

        return null;
    }

    /** Le premier pas « Inviter un Partenaire de Redevabilité » : un partage entre membres ou un engagement. */
    public function hasAny(User $user): bool
    {
        $connections = $this->em->getRepository(Connection::class);

        return $connections->count(['requester' => $user]) + $connections->count(['receiver' => $user]) > 0
            || $this->em->getRepository(AccountabilityCommitment::class)->count(['owner' => $user]) > 0;
    }

    /**
     * Les Partenaires de Redevabilité de la personne, une entrée par personne : d'abord les membres avec qui elle
     * partage, puis les personnes qui ne suivent qu'un engagement. Un membre à la fois dans le partage et dans un
     * engagement n'apparaît qu'une fois.
     *
     * @param list<array{connectionId: int, partnerId: int, partnerName: string, partnerEmail: string}> $activePartners
     * @param list<AccountabilityCommitment>                                                         $commitments
     *
     * @return list<array{key: string, name: string, member: bool, connection: ?array, commitments: list<AccountabilityCommitment>}>
     */
    public function people(array $activePartners, array $commitments): array
    {
        $people = [];
        foreach ($activePartners as $p) {
            $people['u'.$p['partnerId']] = ['key' => 'u'.$p['partnerId'], 'name' => $p['partnerName'], 'member' => true, 'connection' => $p, 'commitments' => []];
        }
        foreach ($commitments as $c) {
            $partner = $c->getPartnerUser();
            $key = null !== $partner ? 'u'.$partner->getId() : 'e'.mb_strtolower($c->getPartnerEmail());
            $people[$key] ??= ['key' => $key, 'name' => $c->getPartnerName(), 'member' => null !== $partner, 'connection' => null, 'commitments' => []];
            $people[$key]['commitments'][] = $c;
        }

        return array_values($people);
    }
}
