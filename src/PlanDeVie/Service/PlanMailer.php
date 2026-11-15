<?php

namespace App\PlanDeVie\Service;

use App\Compte\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Envoi d'un plan par email à son propriétaire (version « EMAIL » du gabarit de visualisation).
 */
final class PlanMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly PlanDataProvider $data,
        private readonly LoggerInterface $logger,
        #[Autowire('%app.mailer_from%')] private readonly string $from,
    ) {
    }

    /** Renvoie true si l'email est parti. */
    public function send(User $user, string $planKey): bool
    {
        try {
            $data = 'VIE' === $planKey ? $this->data->getLifePlanData($user) : $this->data->getStrategiesData($user);
            $data['variant'] = 'EMAIL';
            $this->mailer->send((new Email())
                ->from(Address::create($this->from))
                ->to($user->getEmail())
                ->subject('Votre '.PlanCatalog::label($planKey).' — KIINI SAWA')
                ->html($this->twig->render(PlanCatalog::PLANS[$planKey]['template'], $data)));

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Échec de l\'envoi du plan par email', ['plan' => $planKey, 'exception' => $e]);

            return false;
        }
    }
}
