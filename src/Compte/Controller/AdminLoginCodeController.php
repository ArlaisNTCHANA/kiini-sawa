<?php

namespace App\Compte\Controller;

use App\Compte\Entity\AccountToken;
use App\Compte\Entity\User;
use App\Compte\Security\AdminSecondFactorSubscriber;
use App\Compte\Service\AccountMailer;
use App\Compte\Service\AccountTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Deuxième étape de connexion des administrateurs : saisie du code reçu par e-mail.
 */
#[IsGranted('ROLE_ADMIN')]
class AdminLoginCodeController extends AbstractController
{
    public function __construct(private readonly AccountTokenService $tokens, private readonly AccountMailer $mailer)
    {
    }

    #[Route('/connexion/code', name: 'app_admin_login_code', methods: ['GET', 'POST'])]
    public function code(Request $request): Response
    {
        $session = $request->getSession();
        if (true === $session->get(AdminSecondFactorSubscriber::SESSION_KEY)) {
            return $this->redirectToRoute('admin_dashboard');
        }
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            if (!$this->isCsrfTokenValid('admin_login_code', $payload->getString('_csrf_token'))) {
                $this->addFlash('error', 'La page a expiré. Merci de réessayer.');
            } elseif ($this->tokens->consumeCode($user, AccountToken::ADMIN_LOGIN, $payload->getString('code'))) {
                $session->migrate(true);
                $session->set(AdminSecondFactorSubscriber::SESSION_KEY, true);
                $this->addFlash('success', 'Connexion confirmée.');

                return $this->redirectToRoute('app_dashboard');
            } else {
                $this->addFlash('error', 'Code incorrect ou expiré. Après 5 essais, demandez un nouveau code.');
            }

            return $this->redirectToRoute('app_admin_login_code');
        }

        // Premier affichage de la session : envoi du code (sans en renvoyer un à chaque rechargement)
        if (!$session->get('admin_login_code_sent') && $this->tokens->canResend($user, AccountToken::ADMIN_LOGIN)) {
            $this->mailer->sendAdminLoginCode($user, $this->tokens->createCode($user, AccountToken::ADMIN_LOGIN));
            $session->set('admin_login_code_sent', true);
        }

        return $this->render('compte/admin_login_code.html.twig', ['email' => $user->getEmail()]);
    }

    #[Route('/connexion/code/renvoyer', name: 'app_admin_login_code_resend', methods: ['POST'])]
    public function resend(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->isCsrfTokenValid('admin_login_code_resend', $request->getPayload()->getString('_csrf_token'))) {
            $this->addFlash('error', 'La page a expiré. Merci de réessayer.');
        } elseif (!$this->tokens->canResend($user, AccountToken::ADMIN_LOGIN)) {
            $this->addFlash('error', 'Un code vient d\'être envoyé. Patientez une minute avant d\'en demander un autre.');
        } else {
            $this->mailer->sendAdminLoginCode($user, $this->tokens->createCode($user, AccountToken::ADMIN_LOGIN));
            $this->addFlash('success', 'Un nouveau code vous a été envoyé.');
        }

        return $this->redirectToRoute('app_admin_login_code');
    }
}
