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
use App\Compte\Service\BirthDate;
use App\Compte\Service\BirthdayTexts;
use App\Compte\Service\PasswordPolicy;
use App\Compte\Service\UserDataExporter;
use App\Notification\Service\Notifier;
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
        private readonly \App\Shared\Security\RateLimits $limits,
        private readonly \App\Mobile\Service\MobileTokenService $mobileTokens,
    ) {
    }

    /** Préférences de notification modifiables depuis le profil : champ du formulaire => setter. */
    public const NOTIFICATION_FLAGS = [
        'enableInApp' => 'setEnableInApp',
        'enableEmailDigest' => 'setEnableEmailDigest',
        'enableEmailInstant' => 'setEnableEmailInstant',
        'notifyWeeklyReview' => 'setNotifyWeeklyReview',
        'notifyMonthlyReview' => 'setNotifyMonthlyReview',
        'notifyHabitReminder' => 'setNotifyHabitReminder',
        'notifyGoalDeadline' => 'setNotifyGoalDeadline',
        'notifyPartnerInactive' => 'setNotifyPartnerInactive',
        'notifyConnection' => 'setNotifyConnection',
        'notifyOnboarding' => 'setNotifyOnboarding',
        'notifyChallenge' => 'setNotifyChallenge',
        'notifyBirthday' => 'setNotifyBirthday',
    ];

    #[Route('', name: '', methods: ['GET'])]
    public function index(Notifier $notifier): Response
    {
        $user = $this->currentUser();

        return $this->render('compte/profile.html.twig', [
            'profile' => $user->getUserProfile(),
            'pendingEmail' => $this->em->getRepository(AccountToken::class)->findOneBy(['user' => $user, 'purpose' => AccountToken::CHANGE_EMAIL], ['id' => 'DESC'])?->getPayload(),
            'minPasswordLength' => PasswordPolicy::MIN_LENGTH,
            'prefs' => $notifier->preferences($user),
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

    /**
     * Date de naissance (privée : jamais montrée à quelqu'un d'autre). Envoyée depuis « Mon profil » ou depuis l'invitation
     * de l'accueil (`retour=accueil`). Vide depuis le profil : la date est effacée.
     */
    #[Route('/date-de-naissance', name: '_birth_date', methods: ['POST'])]
    public function updateBirthDate(Request $request, \App\Contenu\Service\SiteTexts $texts): Response
    {
        $payload = $request->getPayload();
        $fromHome = 'accueil' === $payload->getString('retour');
        $back = $fromHome ? $this->redirectToRoute('app_dashboard') : $this->redirectToRoute('app_profile', ['_fragment' => 'informations']);
        if (!$this->isCsrfTokenValid('profile_birth_date', $payload->getString('_csrf_token'))) {
            return $this->expired();
        }

        $user = $this->currentUser();
        $profile = $user->getUserProfile();
        if (null === $profile) {
            $profile = new UserProfile();
            $user->setUserProfile($profile);
            $this->em->persist($profile);
        }

        $value = trim($payload->getString('dateOfBirth'));
        if ('' === $value && !$fromHome) {
            $profile->setDateOfBirth(null);
            $this->em->flush();
            $this->addFlash('success', BirthdayTexts::text($texts, 'naissance.effacee'));

            return $back;
        }
        if ($problem = BirthDate::checkInput($value, new \DateTimeImmutable('today'))) {
            $this->addFlash('error', BirthdayTexts::text($texts, $problem));

            return $back;
        }
        $profile->setDateOfBirth(\DateTime::createFromImmutable(BirthDate::parse($value)));
        $this->em->flush();
        $this->addFlash('success', BirthdayTexts::text($texts, 'naissance.enregistree'));

        return $back;
    }

    /** « Plus tard » sur l'invitation de l'accueil : elle ne revient plus (la date reste modifiable dans « Mon profil »). */
    #[Route('/date-de-naissance/plus-tard', name: '_birth_date_dismiss', methods: ['POST'])]
    public function dismissBirthDate(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('profile_birth_date_dismiss', $request->getPayload()->getString('_csrf_token'))) {
            return $this->expired();
        }
        $user = $this->currentUser();
        $profile = $user->getUserProfile();
        if (null === $profile) {
            $profile = new UserProfile();
            $user->setUserProfile($profile);
            $this->em->persist($profile);
        }
        $profile->setBirthDatePromptDismissedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/notifications', name: '_notifications', methods: ['POST'])]
    public function updateNotifications(Request $request, Notifier $notifier): Response
    {
        $payload = $request->getPayload();
        if (!$this->isCsrfTokenValid('profile_notifications', $payload->getString('_csrf_token'))) {
            return $this->expired();
        }
        $prefs = $notifier->preferences($this->currentUser());
        foreach (self::NOTIFICATION_FLAGS as $field => $setter) {
            $prefs->{$setter}($payload->getBoolean($field));
        }
        $prefs->setWeeklyReviewDay(max(0, min(6, $payload->getInt('weeklyReviewDay'))));
        $this->em->flush();
        $this->addFlash('success', 'Vos préférences de notification ont été enregistrées.');

        return $this->redirectToRoute('app_profile', ['_fragment' => 'notifications']);
    }

    #[Route('/mot-de-passe', name: '_password', methods: ['POST'])]
    public function changePassword(Request $request, PasswordPolicy $policy): Response
    {
        $user = $this->currentUser();
        $payload = $request->getPayload();
        if (!$this->isCsrfTokenValid('profile_password', $payload->getString('_csrf_token'))) {
            return $this->expired();
        }
        if (!$this->limits->allow('account_security', (string) $user->getId())) {
            return $this->tooManyAttempts();
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
        // Nouveau mot de passe : les sessions de l'application mobile sont fermées (reconnexion demandée)
        $this->mobileTokens->revokeAll($user);
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
        if (!$this->limits->allow('account_security', (string) $user->getId())) {
            return $this->tooManyAttempts();
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
        } elseif ($request->getSession()->get('email_change_at', 0) > time() - 60 || !$tokens->canResend($user, AccountToken::CHANGE_EMAIL)) {
            // Même délai d'attente que l'adresse existe déjà ou non : sinon, redemander deux fois de suite révélerait si elle est prise
            $this->addFlash('error', 'Un code vient d\'être envoyé. Patientez une minute.');
        } else {
            $request->getSession()->set('email_change_at', time());
            // Anti-énumération : si l'adresse appartient déjà à un compte, la réponse est identique (aucun code n'est envoyé)
            if (!$users->findOneBy(['email' => $email])) {
                $mailer->sendVerificationCode($user, $tokens->createCode($user, AccountToken::CHANGE_EMAIL, $email), $email);
            }
            $this->addFlash('success', 'Un code a été envoyé à '.$email.' si cette adresse peut être utilisée. Saisissez-le ci-dessous pour confirmer.');
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
            $this->mobileTokens->revokeAll($user);
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
        if (!$this->limits->allow('account_security', (string) $user->getId())) {
            return $this->tooManyAttempts();
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

    /** Trop d'essais de mot de passe actuel (changement de mot de passe, d'e-mail, suppression du compte) : pause de 15 minutes. */
    private function tooManyAttempts(): Response
    {
        $this->addFlash('error', 'Trop de tentatives. Patientez quelques minutes avant de réessayer.');

        return $this->redirectToRoute('app_profile', ['_fragment' => 'securite']);
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
