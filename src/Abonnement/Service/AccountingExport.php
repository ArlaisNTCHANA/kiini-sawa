<?php

namespace App\Abonnement\Service;

use App\Abonnement\Entity\Transaction;
use App\Compte\Entity\User;
use App\Organisation\Entity\Membership;
use App\Organisation\Entity\SeatOrder;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Exports pour la comptabilité (Admin › Les chiffres › Exports pour la comptabilité, 28/11/2026) : des tableaux à
 * ouvrir dans Excel, LibreOffice ou Google Sheets (CSV au point-virgule, accents gardés, montants à la française).
 *
 * Paiements des membres et commandes de places des organisations sur une période ; liste des membres (coordonnées et
 * offre seulement, jamais le contenu de leurs plans ni de leur journal).
 */
final class AccountingExport
{
    private const STATUS = [Transaction::STATUS_PENDING => 'à vérifier', Transaction::STATUS_VALIDATED => 'validé', Transaction::STATUS_REJECTED => 'refusé'];

    public function __construct(private readonly EntityManagerInterface $em, private readonly \App\Abonnement\Service\Entitlements $entitlements)
    {
    }

    /** @return list<list<string>> en-tête puis lignes */
    public function payments(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = [['Date de déclaration', 'N° de reçu', 'Membre', 'E-mail', 'Formule', 'Montant', 'Devise', 'Moyen de paiement', 'Référence', 'Décision', 'Date de décision', 'Note ou motif']];
        $list = $this->em->createQueryBuilder()->select('t', 's', 'u')->from(Transaction::class, 't')
            ->join('t.subscription', 's')->join('s.user', 'u')
            ->where('t.createdAt >= :from AND t.createdAt < :to')->setParameter('from', $from)->setParameter('to', $to)
            ->orderBy('t.createdAt', 'ASC')->getQuery()->getResult();
        foreach ($list as $t) {
            \assert($t instanceof Transaction);
            $u = $t->getSubscription()?->getUser();
            $rows[] = [
                $t->getCreatedAt()?->format('d/m/Y') ?? '',
                (string) $t->getReceiptNumber(),
                (string) $u?->getDisplayName(),
                (string) $u?->getEmail(),
                SubscriptionManager::PERIODS[$t->getPeriod()] ?? $t->getPeriod(),
                $this->amount((float) $t->getAmount(), $t->getCurrency()),
                $this->currency($t->getCurrency()),
                $t->getMethodLabel(),
                (string) $t->getReference(),
                self::STATUS[(string) $t->getStatus()] ?? (string) $t->getStatus(),
                $t->getProcessedAt()?->format('d/m/Y') ?? '',
                (string) $t->getAdminNote(),
            ];
        }

        return $rows;
    }

    /** @return list<list<string>> */
    public function seatOrders(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = [['Date de commande', 'N° de reçu', 'Organisation', 'Commandée par', 'Places', 'Montant', 'Devise', 'Moyen de paiement', 'Référence', 'Décision', 'Date de décision', 'Note']];
        $list = $this->em->createQueryBuilder()->select('o')->from(SeatOrder::class, 'o')
            ->where('o.createdAt >= :from AND o.createdAt < :to')->setParameter('from', $from)->setParameter('to', $to)
            ->orderBy('o.createdAt', 'ASC')->getQuery()->getResult();
        foreach ($list as $o) {
            \assert($o instanceof SeatOrder);
            $rows[] = [
                $o->getCreatedAt()->format('d/m/Y'),
                (string) $o->getReceiptNumber(),
                $o->getOrganisation()->getName(),
                (string) $o->getOrderedBy()?->getEmail(),
                (string) $o->getSeats(),
                $this->amount($o->getAmount(), $o->getCurrency()),
                $this->currency($o->getCurrency()),
                Transaction::METHODS[$o->getMethod()] ?? $o->getMethod(),
                (string) $o->getReference(),
                self::STATUS[$o->getStatus()] ?? $o->getStatus(),
                $o->getProcessedAt()?->format('d/m/Y') ?? '',
                (string) $o->getAdminNote(),
            ];
        }

        return $rows;
    }

    /** @return list<list<string>> */
    public function members(): array
    {
        $rows = [['Prénom et nom', 'E-mail', 'Inscrit le', 'Adresse vérifiée', 'Offre', 'PRO jusqu\'au', 'Organisation', 'Dernière visite']];
        $orgs = [];
        foreach ($this->em->getRepository(Membership::class)->findBy(['active' => true]) as $m) {
            $orgs[$m->getUser()->getId()][] = $m->getOrganisation()->getName();
        }
        foreach ($this->em->getRepository(User::class)->findBy([], ['id' => 'ASC']) as $u) {
            if (\in_array('ROLE_ADMIN', $u->getRoles(), true)) {
                continue;
            }
            $trial = $this->entitlements->isTrialing($u);
            $pro = $this->entitlements->isPro($u);
            $until = $pro ? $this->entitlements->proUntil($u) : null;
            $rows[] = [
                $u->getDisplayName(), (string) $u->getEmail(), $u->getCreatedAt()?->format('d/m/Y') ?? '', $u->isVerified() ? 'oui' : 'non',
                $trial ? 'essai PRO' : ($pro ? 'PRO' : 'gratuit'), $until?->format('d/m/Y') ?? '',
                implode(', ', $orgs[$u->getId()] ?? []), $u->getLastActiveAt()?->format('d/m/Y') ?? '',
            ];
        }

        return $rows;
    }

    /** Tableau → CSV pour Excel en français : point-virgule, marque UTF-8 (accents), guillemets si besoin. */
    public static function csv(array $rows): string
    {
        $out = "\u{FEFF}";
        foreach ($rows as $row) {
            $out .= implode(';', array_map(function ($v): string {
                $v = (string) $v;
                // Pas de formule involontaire dans le tableur (=, +, -, @ en tête de cellule)
                if ('' !== $v && \in_array($v[0], ['=', '+', '-', '@'], true) && !is_numeric(str_replace([',', ' '], ['.', ''], $v))) {
                    $v = "'".$v;
                }

                return preg_match('/[;"\r\n]/', $v) ? '"'.str_replace('"', '""', $v).'"' : $v;
            }, $row))."\r\n";
        }

        return $out;
    }

    private function amount(float $amount, string $currency): string
    {
        return number_format($amount, 'EUR' === $currency ? 2 : 0, ',', '');
    }

    private function currency(string $currency): string
    {
        return 'EUR' === $currency ? 'EUR' : 'FCFA';
    }
}
