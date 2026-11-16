<?php

namespace App\Notification\Service;

use App\Compte\Entity\User;
use App\Notification\Enum\NotificationType;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Email accompagnant une notification, avec un lien de désinscription en un clic
 * (lien signé, sans connexion) pour ce type de rappel.
 */
final class NotificationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly UriSigner $signer,
        private readonly LoggerInterface $logger,
        #[Autowire('%app.mailer_from%')] private readonly string $from,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function send(User $user, NotificationType $type, string $title, string $message, ?string $actionUrl, ?string $template = null, array $context = []): void
    {
        $unsubscribe = $this->signer->sign($this->urls->generate('app_notification_unsubscribe', ['user' => $user->getId(), 'type' => $type->value], UrlGeneratorInterface::ABSOLUTE_URL));
        // Lien de l'action en adresse absolue (les notifications stockent des chemins relatifs)
        $action = null;
        if ($actionUrl) {
            $action = str_starts_with($actionUrl, 'http') ? $actionUrl : rtrim($this->urls->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL), '/').$actionUrl;
        }

        try {
            $email = (new TemplatedEmail())
                ->from(Address::create($this->from))
                ->to($user->getEmail())
                ->subject($title)
                ->htmlTemplate($template ?? 'notification/email/notification.html.twig')
                ->context(['name' => $user->getDisplayName(), 'title' => $title, 'message' => $message, 'actionUrl' => $action, 'unsubscribeUrl' => $unsubscribe] + $context);
            $email->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>');
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Échec de l\'envoi d\'un email de notification', ['type' => $type->value, 'exception' => $e]);
        }
    }
}
