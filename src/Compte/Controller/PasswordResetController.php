<?php

namespace App\Compte\Controller;

use App\Mobile\Service\MobileTokenService;
use App\Compte\Entity\AccountToken;
use App\Compte\Repository\UserRepository;
use App\Compte\Service\AccountMailer;
use App\Compte\Service\AccountTokenService;
use App\Compte\Service\PasswordPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Mot de passe oublié : demande d'un lien par email, puis choix d'un nouveau mot de passe.
 *
 * La réponse est la même que l'adresse existe ou non (pas de fuite d'information).
 * Le jeton est retiré de l'URL dès la première visite et conservé en session,
 * pour qu'il ne fuite pas via l'historique ou l'en-tête Referer.
 */
class PasswordResetController extends AbstractController
{
    private const SESSION_KEY = 'reset_password_token';

    public function __construct(private readonly AccountTokenService $tokens)
    {
    }

    #[Route('/mot-de-passe/oublie', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        UserRepository $users,
        AccountMailer $mailer,
        #[Autowire(service: 'limiter.password_reset')] RateLimiterFactoryInterface $limiter,
    ): Response {
        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            if (!$this->isCsrfTokenValid('forgot_password', $payload->getString('_csrf_token'))) {
                $this->addFlash('error', 'La page a expiré. Merci de réessayer.');

                return $this->redirectToRoute('app_forgot_password');
            }
            if (!$limiter->create($request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('error', 'Trop de demandes depuis cette connexion. Réessayez dans une heure.');

                return $this->redirectToRoute('app_forgot_password');
            }

            $user = $users->findOneBy(['email' => mb_strtolower(trim($payload->getString('email')))]);
            if ($user) {
                [$selector, $secret] = $this->tokens->createResetToken($user);
                $mailer->sendPasswordReset($user, $selector, $secret);
            }

            $this->addFlash('info', 'Si un compte est associé à cette adresse, un lien de réinitialisation vient d\'être envoyé. Il est valable une heure.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('compte/forgot_password.html.twig');
    }

    #[Route('/mot-de-passe/reinitialiser/{selector}/{secret}', name: 'app_reset_password', requirements: ['selector' => '[a-f0-9]{24}', 'secret' => '[a-f0-9]{40}'], defaults: ['selector' => null, 'secret' => null], methods: ['GET', 'POST'])]
    #[Route('/mot-de-passe/reinitialiser', name: 'app_reset_password_form', methods: ['GET', 'POST'])]
    public function reset(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        PasswordPolicy $passwordPolicy,
        MobileTokenService $mobileTokens,
        ?string $selector = null,
        ?string $secret = null,
    ): Response {
        $session = $request->getSession();
        if (null !== $selector && null !== $secret) {
            $session->set(self::SESSION_KEY, [$selector, $secret]);

            return $this->redirectToRoute('app_reset_password_form');
        }

        [$selector, $secret] = $session->get(self::SESSION_KEY) ?? [null, null];
        $token = $selector ? $this->tokens->findResetToken($selector, $secret) : null;
        $error = null;

        if ($token && $request->isMethod('POST')) {
            $payload = $request->getPayload();
            $password = $payload->getString('password');
            $user = $token->getUser();

            if (!$this->isCsrfTokenValid('reset_password', $payload->getString('_csrf_token'))) {
                $error = 'La page a expiré. Merci de réessayer.';
            } elseif ($error = $passwordPolicy->validate($password, $user->getEmail())) {
                // message renvoyé dans le formulaire
            } else {
                $user->setPassword($hasher->hashPassword($user, $password));
                // Recevoir le lien prouve la possession de l'adresse email
                $user->setIsVerified(true);
                $this->tokens->removeAll($user, AccountToken::RESET_PASSWORD);
                $em->flush();
                // Un mot de passe réinitialisé ferme aussi les sessions de l'application mobile
                $mobileTokens->revokeAll($user);
                $session->remove(self::SESSION_KEY);
                $this->addFlash('success', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('compte/reset_password.html.twig', [
            'valid' => null !== $token,
            'error' => $error,
        ], new Response(status: null === $error ? 200 : 422));
    }
}
