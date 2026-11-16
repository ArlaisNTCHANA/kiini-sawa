<?php

namespace App\Compte\Controller;

use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\AccountToken;
use App\Organisation\Service\PendingInvitation;
use App\Compte\Entity\User;
use App\Compte\Entity\UserProfile;
use App\Compte\Repository\UserRepository;
use App\Compte\Security\LoginAuthenticator;
use App\Compte\Service\AccountMailer;
use App\Compte\Service\AccountTokenService;
use App\Compte\Service\PasswordPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Inscription et vérification de l'adresse email par code à 6 chiffres.
 *
 * Après l'inscription, l'utilisateur est connecté mais ne peut rien faire
 * d'autre que saisir son code tant que son email n'est pas vérifié
 * (voir VerifiedUserSubscriber).
 */
class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly AccountTokenService $tokens,
        private readonly AccountMailer $mailer,
    ) {
    }

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        PasswordPolicy $passwordPolicy,
        ValidatorInterface $validator,
        Security $security,
        Entitlements $entitlements,
        #[Autowire(service: 'limiter.registration')] RateLimiterFactoryInterface $registrationLimiter,
        \App\Media\Service\OriginTracker $origins,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $data = [];
        $errors = [];

        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            $data = [
                'firstName' => trim($payload->getString('firstName')),
                'email' => mb_strtolower(trim($payload->getString('email'))),
                'acceptTerms' => $payload->getBoolean('acceptTerms'),
            ];
            $password = $payload->getString('password');

            if (!$this->isCsrfTokenValid('register', $payload->getString('_csrf_token'))) {
                $errors['global'][] = 'La page a expiré. Merci de réessayer.';
            }
            // Champ piège rempli : c'est un robot. On fait comme si tout s'était bien passé.
            if ('' !== $payload->getString('website')) {
                return $this->redirectToRoute('app_login');
            }
            if (!$registrationLimiter->create($request->getClientIp())->consume()->isAccepted()) {
                $errors['global'][] = 'Trop d\'inscriptions depuis cette connexion. Réessayez dans une heure.';
            }

            if ('' === $data['firstName'] || mb_strlen($data['firstName']) > 100) {
                $errors['firstName'] = 'Indiquez votre prénom.';
            }
            if ('' === $data['email'] || \strlen($data['email']) > 180 || \count($validator->validate($data['email'], new Email(mode: Email::VALIDATION_MODE_STRICT))) > 0) {
                $errors['email'] = 'Adresse email invalide.';
            }
            if ($message = $passwordPolicy->validate($password, $data['email'])) {
                $errors['password'] = $message;
            }
            if (!$data['acceptTerms']) {
                $errors['acceptTerms'] = 'Vous devez accepter les conditions pour créer un compte.';
            }

            if ([] === $errors) {
                $existing = $users->findOneBy(['email' => $data['email']]);
                if ($existing) {
                    // On ne révèle pas que l'adresse a déjà un compte : on prévient son propriétaire par email.
                    $this->mailer->sendAlreadyRegistered($existing);
                    $this->addFlash('info', 'Si cette adresse peut être utilisée, un email vient de vous être envoyé. Pensez à vérifier vos courriers indésirables.');

                    return $this->redirectToRoute('app_login');
                }

                $user = (new User())
                    ->setEmail($data['email'])
                    ->setRoles([])
                    ->setTermsAcceptedAt(new \DateTimeImmutable());
                $user->setPassword($hasher->hashPassword($user, $password));
                $user->setUserProfile((new UserProfile())->setFirstName($data['firstName']));
                $em->persist($user);
                $em->persist($origins->originFor($user, $request));
                $entitlements->startTrial($user);
                $em->flush();

                $this->mailer->sendVerificationCode($user, $this->tokens->createCode($user, AccountToken::VERIFY_EMAIL));
                $security->login($user, LoginAuthenticator::class, 'main');

                $response = $this->redirectToRoute('app_verify_email');
                $origins->forget($response);

                return $response;
            }
        }

        return $this->render('compte/register.html.twig', [
            'data' => $data,
            'errors' => $errors,
        ], new Response(status: [] === $errors ? 200 : 422));
    }

    #[Route('/verification-email', name: 'app_verify_email', methods: ['GET', 'POST'])]
    public function verifyEmail(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->isVerified()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            if (!$this->isCsrfTokenValid('verify_email', $payload->getString('_csrf_token'))) {
                $this->addFlash('error', 'La page a expiré. Merci de réessayer.');
            } elseif ($this->tokens->consumeCode($user, AccountToken::VERIFY_EMAIL, $payload->getString('code'))) {
                $user->setIsVerified(true);
                $em->flush();
                $this->addFlash('success', 'Votre adresse email est vérifiée. Bienvenue !');

                return ($invite = PendingInvitation::redirectPath($request->getSession())) ? $this->redirect($invite) : $this->redirectToRoute('app_onboarding');
            } else {
                $this->addFlash('error', 'Code incorrect ou expiré. Après 5 essais, demandez un nouveau code.');
            }

            return $this->redirectToRoute('app_verify_email');
        }

        return $this->render('compte/verify_email.html.twig', ['email' => $user->getEmail()]);
    }

    #[Route('/verification-email/renvoyer', name: 'app_verify_email_resend', methods: ['POST'])]
    public function resendCode(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->isVerified()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if (!$this->isCsrfTokenValid('verify_email_resend', $request->getPayload()->getString('_csrf_token'))) {
            $this->addFlash('error', 'La page a expiré. Merci de réessayer.');
        } elseif (!$this->tokens->canResend($user, AccountToken::VERIFY_EMAIL)) {
            $this->addFlash('error', 'Un code vient d\'être envoyé. Patientez une minute avant d\'en demander un autre.');
        } else {
            $this->mailer->sendVerificationCode($user, $this->tokens->createCode($user, AccountToken::VERIFY_EMAIL));
            $this->addFlash('success', 'Un nouveau code vous a été envoyé.');
        }

        return $this->redirectToRoute('app_verify_email');
    }
}
