<?php

namespace App\Abonnement\Controller;

use App\Abonnement\Entity\Transaction;
use App\Abonnement\Service\Entitlements;
use App\Abonnement\Service\SubscriptionManager;
use App\Compte\Entity\User;
use App\Shared\Service\PdfFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Espace abonnement : offre actuelle, prix, paiement manuel, historique et reçus.
 * Sert aussi de page « Passer à PRO » quand une fonction est réservée (?fonction=map).
 */
#[Route('/abonnement', name: 'app_subscription')]
class AbonnementController extends AbstractController
{
    public function __construct(private readonly Entitlements $entitlements, private readonly SubscriptionManager $manager)
    {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->user();
        $config = $this->entitlements->config();
        $feature = $request->query->getString('fonction');
        $transactions = $user->getSubscription()?->getTransactions()->toArray() ?? [];
        usort($transactions, fn ($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $this->render('abonnement/index.html.twig', [
            'isPro' => $this->entitlements->isPro($user),
            'isTrialing' => $this->entitlements->isTrialing($user),
            'proUntil' => $this->entitlements->proUntil($user),
            'feature' => isset(Entitlements::FEATURES[$feature]) ? Entitlements::featureLabel($feature) : null,
            'limitReached' => match ($request->query->getString('limite')) {
                'habits' => sprintf('Vous avez atteint %d rituels, le maximum de l\'offre gratuite.', $config->getMaxHabitsFree()),
                'connections' => sprintf('Vous avez atteint %d partenaire(s), le maximum de l\'offre gratuite.', $config->getMaxConnectionsFree()),
                default => null,
            },
            'prices' => [
                'EUR' => ['MONTHLY' => $this->manager->price('MONTHLY', 'EUR'), 'YEARLY' => $this->manager->price('YEARLY', 'EUR')],
                'XAF' => ['MONTHLY' => $this->manager->price('MONTHLY', 'XAF'), 'YEARLY' => $this->manager->price('YEARLY', 'XAF')],
            ],
            'defaultCurrency' => $request->query->getString('devise') ?: (str_starts_with((string) $request->getPreferredLanguage(['fr_CM', 'fr_FR']), 'fr_CM') ? 'XAF' : 'EUR'),
            'bankInstructions' => $config->getBankTransferInstructions(),
            'mobileMoneyInstructions' => $config->getMobileMoneyInstructions(),
            'pending' => $this->manager->pendingFor($user),
            'transactions' => $transactions,
            'freeHabits' => $config->getMaxHabitsFree(),
            'freeConnections' => $config->getMaxConnectionsFree(),
        ]);
    }

    #[Route('/declarer', name: '_declare', methods: ['POST'])]
    public function declare(Request $request): Response
    {
        $payload = $request->getPayload();
        if (!$this->isCsrfTokenValid('subscription_declare', $payload->getString('_csrf_token'))) {
            $this->addFlash('error', 'La page a expiré. Merci de réessayer.');

            return $this->redirectToRoute('app_subscription');
        }
        $user = $this->user();
        if ($this->manager->pendingFor($user)) {
            $this->addFlash('error', 'Un paiement est déjà en cours de vérification. Nous revenons vers vous très vite.');

            return $this->redirectToRoute('app_subscription');
        }
        $reference = trim($payload->getString('reference'));
        if ('' === $reference && 'CASH' !== $payload->getString('method')) {
            $this->addFlash('error', 'Indiquez la référence de votre paiement (n° de transaction, libellé du virement…) pour que nous puissions le retrouver.');

            return $this->redirectToRoute('app_subscription');
        }
        $paidAt = \DateTimeImmutable::createFromFormat('!Y-m-d', $payload->getString('paidAt')) ?: null;

        try {
            $t = $this->manager->declare($user, $payload->getString('period'), $payload->getString('currency'), $payload->getString('method'), $reference, $paidAt, trim($payload->getString('note')) ?: null);
        } catch (\InvalidArgumentException) {
            $this->addFlash('error', 'Formule ou moyen de paiement invalide.');

            return $this->redirectToRoute('app_subscription');
        }
        $this->addFlash('success', sprintf('Merci ! Votre paiement de %s est enregistré. Il sera vérifié sous 24 à 48 h ouvrées, et vous serez prévenu dès l\'activation de PRO.',
            SubscriptionManager::formatAmount($t->getAmount(), $t->getCurrency())));

        return $this->redirectToRoute('app_subscription');
    }

    #[Route('/recu/{id}', name: '_receipt', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function receipt(Transaction $transaction, PdfFactory $pdf): Response
    {
        $user = $this->user();
        $isOwner = $transaction->getSubscription()->getUser() === $user;
        if ((!$isOwner && !$this->isGranted('ROLE_ADMIN')) || Transaction::STATUS_VALIDATED !== $transaction->getStatus() || !$transaction->getReceiptNumber()) {
            throw $this->createNotFoundException();
        }

        $content = $pdf->render($this->renderView('abonnement/receipt.html.twig', [
            't' => $transaction,
            'customer' => $transaction->getSubscription()->getUser(),
            'amount' => SubscriptionManager::formatAmount($transaction->getAmount(), $transaction->getCurrency()),
        ]));

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'recu-'.$transaction->getReceiptNumber().'.pdf'),
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
