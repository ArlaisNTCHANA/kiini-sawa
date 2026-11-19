<?php

namespace App\Abonnement\Service;

use App\Abonnement\Entity\Subscription;
use App\Abonnement\Entity\Transaction;
use App\Abonnement\Enum\SubscriptionStatus;
use App\Abonnement\Enum\SubscriptionTier;
use App\Compte\Entity\User;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Abonnement PRO et paiements manuels (virement, Mobile Money, espèces).
 *
 * 1. Le client choisit sa formule et déclare son paiement avec une référence (statut PENDING).
 * 2. L'administrateur est prévenu, vérifie son compte et valide (ou refuse) dans /admin/paiements.
 * 3. À la validation : numéro de reçu, PRO prolongé d'un mois ou d'un an, client prévenu.
 *
 * Les paiements en ligne (carte, Mobile Money automatique) utiliseront le même parcours de validation,
 * déclenché par le fournisseur au lieu de l'administrateur : voir docs/modules/abonnement.md.
 */
final class SubscriptionManager
{
    public const CURRENCIES = ['EUR' => '€', 'XAF' => 'FCFA'];
    public const PERIODS = ['MONTHLY' => 'Mensuel', 'YEARLY' => 'Annuel', 'DUO' => 'Duo annuel (couple)'];
    public const MANUAL_METHODS = ['BANK_TRANSFER', 'MOBILE_MONEY', 'CASH'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Entitlements $entitlements,
        private readonly Notifier $notifier,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly ReceiptNumberGenerator $receipts,
        private readonly \App\Couple\Repository\CoupleRepository $couples,
        #[Autowire('%app.mailer_from%')] private readonly string $from,
    ) {
    }

    public function price(string $period, string $currency): float
    {
        $c = $this->entitlements->config();

        return match ([$period, $currency]) {
            ['MONTHLY', 'EUR'] => $c->getProMonthlyPrice(),
            ['YEARLY', 'EUR'] => $c->getProYearlyPrice(),
            ['MONTHLY', 'XAF'] => (float) $c->getProMonthlyPriceXaf(),
            ['YEARLY', 'XAF'] => (float) $c->getProYearlyPriceXaf(),
            ['DUO', 'EUR'] => $c->getDuoYearlyPriceEur(),
            ['DUO', 'XAF'] => (float) $c->getDuoYearlyPriceXaf(),
            default => throw new \InvalidArgumentException('Formule inconnue.'),
        };
    }

    public static function formatAmount(float $amount, string $currency): string
    {
        return 'XAF' === $currency
            ? number_format($amount, 0, ',', ' ').' FCFA'
            : number_format($amount, 2, ',', ' ').' €';
    }

    public function subscription(User $user): Subscription
    {
        $sub = $user->getSubscription();
        if (null === $sub) {
            $sub = (new Subscription())->setUser($user);
            $user->setSubscription($sub);
            $this->em->persist($sub);
        }

        return $sub;
    }

    public function pendingFor(User $user): ?Transaction
    {
        foreach ($user->getSubscription()?->getTransactions() ?? [] as $t) {
            if ($t->isPending()) {
                return $t;
            }
        }

        return null;
    }

    /** Le client déclare un paiement manuel. */
    public function declare(User $user, string $period, string $currency, string $method, string $reference, ?\DateTimeImmutable $paidAt, ?string $note): Transaction
    {
        if (!isset(self::PERIODS[$period], self::CURRENCIES[$currency]) || !\in_array($method, self::MANUAL_METHODS, true)) {
            throw new \InvalidArgumentException('Formule ou moyen de paiement inconnu.');
        }
        $amount = $this->price($period, $currency);
        $transaction = (new Transaction())
            ->setAmount($amount)->setCurrency($currency)->setPeriod($period)->setMethod($method)
            ->setStatus(Transaction::STATUS_PENDING)->setReference(mb_substr($reference, 0, 255))->setPaidAt($paidAt)
            ->setDescription('PRO '.mb_strtolower(self::PERIODS[$period]).($note ? ' — '.mb_substr($note, 0, 500) : ''));
        $this->subscription($user)->addTransaction($transaction);
        $this->em->persist($transaction);
        $this->em->flush();

        $this->alertAdmins($user, $transaction);

        return $transaction;
    }

    /** L'administrateur confirme avoir reçu le paiement. */
    public function validate(Transaction $transaction, ?string $note = null): void
    {
        if (!$transaction->isPending()) {
            return;
        }
        $user = $transaction->getSubscription()->getUser();
        $transaction->setStatus(Transaction::STATUS_VALIDATED)->setProcessedAt(new \DateTimeImmutable())
            ->setAdminNote($note)->setReceiptNumber($this->receipts->next());
        $end = $this->extend($user, $transaction->getPeriod());
        // Duo : le conjoint (espace couple actif) reçoit aussi un an de PRO
        $partner = 'DUO' === $transaction->getPeriod() ? $this->couples->findFor($user)?->partnerOf($user) : null;
        if ($partner) {
            $partnerEnd = $this->extend($partner, 'YEARLY');
        }
        $this->em->flush();
        if ($partner) {
            $this->notifier->notify($partner, NotificationType::PAYMENT_VALIDATED, 'Votre abonnement Duo est actif',
                sprintf('%s a réglé l\'abonnement Duo : vous êtes PRO tous les deux jusqu\'au %s.', $user->getDisplayName(), $partnerEnd->format('d/m/Y')),
                '/couple', 'duo '.$transaction->getId());
        }

        $this->notifier->notify($user, NotificationType::PAYMENT_VALIDATED, 'Paiement reçu : bienvenue en PRO !',
            sprintf('Votre paiement de %s a été validé. Votre accès PRO est actif jusqu\'au %s. Votre reçu est disponible dans votre espace abonnement.',
                self::formatAmount($transaction->getAmount(), $transaction->getCurrency()), $end->format('d/m/Y')),
            '/abonnement', 'paiement '.$transaction->getId());
    }

    public function reject(Transaction $transaction, string $reason): void
    {
        if (!$transaction->isPending()) {
            return;
        }
        $transaction->setStatus(Transaction::STATUS_REJECTED)->setProcessedAt(new \DateTimeImmutable())->setAdminNote($reason);
        $this->em->flush();
        $user = $transaction->getSubscription()->getUser();
        $this->notifier->notify($user, NotificationType::PAYMENT_REJECTED, 'Nous n\'avons pas retrouvé votre paiement',
            'Votre déclaration de paiement n\'a pas pu être validée. Motif : '.$reason.' Vous pouvez la refaire depuis votre espace abonnement ou nous répondre.',
            '/abonnement', 'paiement '.$transaction->getId());
    }

    /** PRO offert (pilotes, partenaires, églises…), tracé comme un paiement de 0. */
    /** @param bool $notify false si l'appelant envoie son propre message (ex. parrainage) */
    public function gift(User $user, int $months, ?string $note, bool $notify = true): Transaction
    {
        $transaction = (new Transaction())->setAmount(0)->setCurrency('EUR')->setPeriod('MONTHLY')->setMethod('GIFT')
            ->setStatus(Transaction::STATUS_VALIDATED)->setProcessedAt(new \DateTimeImmutable())
            ->setDescription(sprintf('PRO offert %d mois', $months))->setAdminNote($note);
        $this->subscription($user)->addTransaction($transaction);
        $this->em->persist($transaction);
        $end = $this->extend($user, null, $months);
        $this->em->flush();
        if (!$notify) {
            return $transaction;
        }
        $this->notifier->notify($user, NotificationType::PAYMENT_VALIDATED, 'Un accès PRO vous est offert',
            sprintf('Vous bénéficiez de KIINI SAWA PRO jusqu\'au %s. Profitez-en pour bâtir vos six plans !', $end->format('d/m/Y')),
            '/mes-plans', 'cadeau '.$transaction->getId());

        return $transaction;
    }

    /** Prolonge les droits PRO à partir de la fin actuelle (ou d'aujourd'hui) ; renvoie la nouvelle fin. */
    private function extend(User $user, ?string $period, int $months = 0): \DateTime
    {
        $sub = $this->subscription($user);
        $currentEnd = $this->entitlements->personalProUntil($user); // une place d'organisation ne compte pas
        // Pendant l'essai, la période payée commence à la fin de l'essai : le client ne perd aucun jour
        $start = $currentEnd ? \DateTime::createFromInterface($currentEnd) : new \DateTime();
        $end = (clone $start)->modify(match (true) {
            $months > 0 => "+$months months",
            \in_array($period, ['YEARLY', 'DUO'], true) => '+1 year',
            default => '+1 month',
        });
        $sub->setTier(SubscriptionTier::PRO)->setStatus(SubscriptionStatus::ACTIVE)
            ->setCurrentPeriodStart($sub->getCurrentPeriodStart() && $currentEnd ? $sub->getCurrentPeriodStart() : new \DateTime())
            ->setCurrentPeriodEnd($end)->setCancelAtPeriodEnd(true); // pas de renouvellement automatique en paiement manuel

        return $end;
    }


    private function alertAdmins(User $customer, Transaction $t): void
    {
        $summary = sprintf('%s déclare un paiement de %s (%s, réf. %s).', $customer->getDisplayName().' ('.$customer->getEmail().')',
            self::formatAmount($t->getAmount(), $t->getCurrency()), $t->getMethodLabel(), $t->getReference() ?: '—');

        foreach ($this->em->getRepository(User::class)->findAdmins() as $admin) {
            $this->notifier->notify($admin, NotificationType::PAYMENT_DECLARED, 'Paiement à vérifier', $summary, '/admin/paiements', 'paiement '.$t->getId());
        }
        $extra = $this->entitlements->config()->getPaymentNotificationEmail();
        if ($extra) {
            try {
                $this->mailer->send((new Email())->from(Address::create($this->from))->to($extra)
                    ->subject('Paiement à vérifier — KIINI SAWA')->text($summary."\n\nValider : /admin/paiements"));
            } catch (\Throwable $e) {
                $this->logger->error('Alerte de paiement non envoyée', ['exception' => $e]);
            }
        }
    }
}
