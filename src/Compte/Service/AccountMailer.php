<?php

namespace App\Compte\Service;

use App\Compte\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Emails liés au compte : code de vérification, réinitialisation du mot de passe,
 * tentative d'inscription sur une adresse déjà utilisée, confirmation de suppression.
 *
 * En développement, le code est aussi affiché dans un message flash :
 * aucun serveur mail n'est nécessaire pour tester les parcours.
 */
final class AccountMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly RequestStack $requestStack,
        #[Autowire('%app.mailer_from%')] private readonly string $from,
        #[Autowire('%kernel.environment%')] private readonly string $env,
    ) {
    }

    public function sendVerificationCode(User $user, string $code, ?string $toEmail = null): void
    {
        $this->send($toEmail ?? $user->getEmail(), 'Votre code KIINI SAWA : '.$code, 'compte/email/code.html.twig', [
            'name' => $user->getDisplayName(),
            'code' => $code,
            'isEmailChange' => null !== $toEmail,
        ]);
        $this->devHint('Code envoyé : '.$code);
    }

    /** Code de connexion en deux étapes (comptes administrateurs). */
    public function sendAdminLoginCode(User $user, string $code): void
    {
        $this->send($user->getEmail(), 'Code de connexion administrateur : '.$code, 'compte/email/admin_login_code.html.twig', [
            'name' => $user->getDisplayName(),
            'code' => $code,
        ]);
        $this->devHint('Code de connexion : '.$code);
    }

    public function sendPasswordReset(User $user, string $selector, string $secret): void
    {
        $url = $this->urls->generate('app_reset_password', ['selector' => $selector, 'secret' => $secret], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->send($user->getEmail(), 'Réinitialiser votre mot de passe KIINI SAWA', 'compte/email/reset_password.html.twig', [
            'name' => $user->getDisplayName(),
            'url' => $url,
        ]);
        $this->devHint('Lien de réinitialisation : '.$url);
    }

    /** Quelqu'un a tenté de créer un compte avec une adresse déjà inscrite. */
    public function sendAlreadyRegistered(User $user): void
    {
        $this->send($user->getEmail(), 'Vous avez déjà un compte KIINI SAWA', 'compte/email/already_registered.html.twig', [
            'name' => $user->getDisplayName(),
            'loginUrl' => $this->urls->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'resetUrl' => $this->urls->generate('app_forgot_password', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    public function sendAccountDeleted(string $email, string $name): void
    {
        $this->send($email, 'Votre compte KIINI SAWA a été supprimé', 'compte/email/account_deleted.html.twig', ['name' => $name]);
    }

    private function send(string $to, string $subject, string $template, array $context): void
    {
        $this->mailer->send((new TemplatedEmail())
            ->from(Address::create($this->from))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context));
    }

    private function devHint(string $message): void
    {
        if ('dev' !== $this->env) {
            return;
        }
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('dev', $message);
        }
    }
}
