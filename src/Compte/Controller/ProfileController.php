<?php

namespace App\Compte\Controller;

use App\Compte\Entity\AccountToken;
use App\Compte\Entity\User;
use App\Compte\Entity\UserProfile;
use App\Compte\Repository\UserRepository;
use App\Compte\Security\LoginAuthenticator;
use App\Compte\Service\AccountDeleter;
use App\Compte\Service\AccountMailer;
use App\Compte\Service\AccountTokenService;
use App\Compte\Service\PasswordPolicy;
use App\Compte\Service\UserDataExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Mon Profil : informations personnelles, email, mot de passe,
 * export des données et suppression du compte (RGPD).
 */
#[Route('/profil', name: 'app_profile')]
class ProfileController extends AbstractController
{
    /** Champs du profil modifiables, avec leur longueur maximale. */
    private const PROFILE_FIELDS = [
        'firstName' => 100,
        'lastName' => 100,
        'city' => 100,
        'country' => 100,
        'profession' => 150,
        'bio' => 2000,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly Security $security,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->currentUser();

        return $this->render('compte/profile.html.twig', [
            'profile' => $user->getUserProfile(),
            'pendingEmail' => $this->em->getRepository(AccountToken::class)->findOneBy(['user' => $user, 'purpose' => AccountToken::CHANGE_EMAIL], ['id' => 'DESC'])?->getPayload(),
            'minPasswordLength' => PasswordPolicy::MIN_LENGTH,
        ]);
    }

    #[Route('/informations', name: '_info', methods: ['POST'])]
    public function updateInfo(Request $request): Response
    {
        $user = $this->currentUser();
        $payload = $request->getPayload();
        if (!$this->isCsrfTokenValid('profile_info', $payload->getString('_csrf_token'))) {
            return $this->expired();
        }

        $profile = $user->getUserProfile() ?? new UserProfile();
        foreach (self::PROFILE_FIELDS as $field => $max) {
            $value = trim($payload->getString($field));
            if (mb_strlen($value) > $max) {
                $this->addFlash('error', 'Un des champs est trop long.');

                return $this->redirectToRoute('app_profile');
            }
            $profile->{'set'.ucfirst($field)}('' === $value ? null : $value);
        }
        if (null === $user->getUserProfile()) {
            $user->setUserProfile($profile);
            $this->em->persist($profile);
        }
        $this->em->flush();
        $this->addFlash('success', 'Vos informations ont été enregistrées.');

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/mot-de-passe', name: '_password', methods: ['POST'])]
    public function changePassword(Request $request, PasswordPolicy $policy): Response
    {
        $user = $this->currentUser();
        $payload = $request->getPayload();
        if (!$this->isCsrfTokenValid('profile_password', $payload->getString('_csrf_token'))) {
            return $this->expired();
        }
        if (!$this->hasher->isPasswordValid($user, $payload->getString('currentPassword'))) {
            $this->addFlash('error', 'Mot de passe actuel incorrect.');

            return $this->redirectToRoute('app_profile', ['_fragment' => 'securite']);
        }

        $new = $payload->getString('newPassword');
        if ($error = $policy->validate($new, $user->getEmail())) {
            $this->addFlash('error', $error);

            return $this->redirectToRoute('app_profile', ['_fragment' => 'securite']);
        }

        $user->setPassword($this->hasher->hashPassword($user, $new));
        $this->em->flush();
        // Le mot de passe fait partie de l'identité de session : on reconnecte l'utilisateur
        $this->security->login($user, LoginAuthenticator::class, 'main');
        $this->addFlash('success', 'Votre mot de passe a été modifié.');

        return $this->redirectToRoute('app_profile', ['_fragment' => 'securite']);
    }

    #[Route('/email', name: '_email', methods: ['POST'])]
    public function requestEmailChange(Request $request, UserRepository $users, ValidatorInterface $validator, AccountTokenService $tokens, AccountMailer $mailer): Response
    {
        $user = $this->currentUser();
        $payload = $request->getPayload();
        if (!$this->isCsrfTokenValid('profile_email', $payload->getString('_csrf_token'))) {
            return $this->expired();
        }
        if (!$this->hasher->isPasswordValid($user, $payload->getString('currentPassword'))) {
            $this->addFlash('error', 'Mot de passe incorrect.');

            return $this->redirectToRoute('app_profile', ['_fragment' => 'securite']);
        }

        $email = mb_strtolower(trim($payload->getString('newEmail')));
        if ('' === $email || \strlen($email) > 180 || \count($validator->validate($email, new Email(mode: Email::VALIDATION_MODE_STRICT))) > 0) {
            $this->addFlash('error', 'Adresse email invalide.');
        } elseif ($email === $user->getEmail()) {
            $this->addFlash('error', 'C\'est déjà votre adresse actuelle.');
        } elseif ($users->findOneBy(['email' => $email])) {
            $this->addFlash('error', 'Cette adresse ne peut pas être utilisée.');
        } elseif (!$tokens->canResend($user, AccountToken::CHANGE_EMAIL)) {
            $this->addFlash('error', 'Un code vient d\'être envoyé. Patientez une minute.');
        } else {
            $mailer->sendVerificationCode($user, $tokens->createCode($user, AccountToken::CHANGE_EMAIL, $email), $email);
            $this->addFlash('success', 'Un code a été envoyé à '.$email.'. Saisissez-le ci-dessous pour confirmer.');
        }

        return $this->redirectToRoute('app_profile', ['_fragment' => 'securite']);
    }

    #[Route('/email/confirmer', name: '_email_confirm', methods: ['POST'])]
    public function confirmEmailChange(Request $request, UserRepository $users, AccountTokenService $tokens): Response
    {
        $user = $this->currentUser();
        $payload = $request->getPayload();
        if (!$this->isCsrfTokenValid('profile_email_confirm', $payload->getString('_csrf_token'))) {
            return $this->expired();
        }

        $token = $tokens->consumeCode($user, AccountToken::CHANGE_EMAIL, $payload->getString('code'));
        if (null === $token) {
            $this->addFlash('error', 'Code incorrect ou expiré.');
        } elseif ($users->findOneBy(['email' => $token->getPayload()])) {
            $this->addFlash('error', 'Cette adresse ne peut pas être utilisée.');
        } else {
            $user->setEmail($token->getPayload());
            $user->setIsVerified(true);
            $this->em->flush();
            // L'email est l'identifiant de connexion : on met à jour la session
            $this->security->login($user, LoginAuthenticator::class, 'main');
            $this->addFlash('success', 'Votre adresse email est maintenant '.$user->getEmail().'.');
        }

        return $this->redirectToRoute('app_profile', ['_fragment' => 'securite']);
    }

    #[Route('/email/annuler', name: '_email_cancel', methods: ['POST'])]
    public function cancelEmailChange(Request $request, AccountTokenService $tokens): Response
    {
        if ($this->isCsrfTokenValid('profile_email_cancel', $request->getPayload()->getString('_csrf_token'))) {
            $tokens->removeAll($this->currentUser(), AccountToken::CHANGE_EMAIL);
        }

        return $this->redirectToRoute('app_profile', ['_fragment' => 'securite']);
    }

    #[Route('/export', name: '_export', methods: ['GET'])]
    public function export(UserDataExporter $exporter): Response
    {
        $response = new JsonResponse($exporter->export($this->currentUser()));
        $response->setEncodingOptions(\JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'kiini-sawa-mes-donnees-'.date('Y-m-d').'.json'
        ));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/supprimer', name: '_delete', methods: ['POST'])]
    public function delete(Request $request, AccountDeleter $deleter, AccountMailer $mailer): Response
    {
        $user = $this->currentUser();
        $payload = $request->getPayload();
        if (!$this->isCsrfTokenValid('profile_delete', $payload->getString('_csrf_token'))) {
            return $this->expired();
        }
        if ('SUPPRIMER' !== mb_strtoupper(trim($payload->getString('confirmation')))
            || !$this->hasher->isPasswordValid($user, $payload->getString('currentPassword'))) {
            $this->addFlash('error', 'Suppression annulée : tapez SUPPRIMER et indiquez votre mot de passe.');

            return $this->redirectToRoute('app_profile', ['_fragment' => 'donnees']);
        }

        $email = $user->getEmail();
        $name = $user->getDisplayName();
        $deleter->delete($user);
        $this->security->logout(false);
        $request->getSession()->invalidate();
        $mailer->sendAccountDeleted($email, $name);
        $this->addFlash('success', 'Votre compte et toutes vos données ont été supprimés.');

        return $this->redirectToRoute('app_login');
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }

    private function expired(): Response
    {
        $this->addFlash('error', 'La page a expiré. Merci de réessayer.');

        return $this->redirectToRoute('app_profile');
    }
}
