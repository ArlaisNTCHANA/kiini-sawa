<?php

namespace App\Mobile\Controller;

use App\Compte\Entity\UserProfile;
use App\Compte\Service\AccountDeleter;
use App\Compte\Service\AccountMailer;
use App\Compte\Service\BirthDate;
use App\Compte\Service\BirthdayTexts;
use App\Contenu\Service\SiteTexts;
use App\Mobile\Service\MobileUserSerializer;
use App\Shared\Security\RateLimits;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Profil et suppression de compte (exigée par l'App Store / RGPD) pour l'application mobile.
 */
#[Route('/api/mobile/v1')]
class MobileAccountController extends AbstractController
{
    use MobileJsonTrait;

    private const PROFILE_FIELDS = [
        'firstName' => 100,
        'lastName' => 100,
        'city' => 100,
        'country' => 100,
        'profession' => 150,
        'bio' => 2000,
    ];

    #[Route('/moi', name: 'api_mobile_me', methods: ['GET'])]
    public function me(MobileUserSerializer $serializer): JsonResponse
    {
        return $this->jsonNoStore([
            'user' => $serializer->serialize($this->currentMobileUser()),
        ]);
    }

    #[Route('/moi', name: 'api_mobile_me_update', methods: ['PATCH'])]
    public function updateMe(
        Request $request,
        EntityManagerInterface $em,
        MobileUserSerializer $serializer,
        SiteTexts $texts,
    ): JsonResponse {
        $data = $this->decodeJson($request);
        if (null === $data) {
            return $this->jsonError('Requête JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        $user = $this->currentMobileUser();
        $profile = $user->getUserProfile() ?? new UserProfile();

        foreach (self::PROFILE_FIELDS as $field => $maxLength) {
            if (!\array_key_exists($field, $data)) {
                continue;
            }
            $raw = $data[$field];
            if (null !== $raw && !\is_string($raw)) {
                return $this->jsonError('Format de champ invalide.', Response::HTTP_BAD_REQUEST);
            }
            $value = null === $raw ? '' : trim($raw);
            if (mb_strlen($value) > $maxLength) {
                return $this->jsonError('Un des champs dépasse la longueur autorisée.', Response::HTTP_BAD_REQUEST);
            }
            $profile->{'set'.ucfirst($field)}('' === $value ? null : $value);
        }

        // Date de naissance « AAAA-MM-JJ » (null ou vide : effacée) ; mêmes règles que le site (15 ans minimum)
        if (\array_key_exists('dateOfBirth', $data)) {
            $raw = $data['dateOfBirth'];
            if (null !== $raw && !\is_string($raw)) {
                return $this->jsonError('Format de champ invalide.', Response::HTTP_BAD_REQUEST);
            }
            if (null === $raw || '' === trim($raw)) {
                $profile->setDateOfBirth(null);
            } elseif ($problem = BirthDate::checkInput($raw, new \DateTimeImmutable('today'))) {
                return $this->jsonError(BirthdayTexts::text($texts, $problem), Response::HTTP_UNPROCESSABLE_ENTITY);
            } else {
                $profile->setDateOfBirth(\DateTime::createFromImmutable(BirthDate::parse($raw)));
            }
        }
        // « Plus tard » sur l'invitation de l'accueil : elle ne revient plus
        if (true === ($data['birthDatePromptDismissed'] ?? null)) {
            $profile->setBirthDatePromptDismissedAt(new \DateTimeImmutable());
        }

        if (null === $user->getUserProfile()) {
            $user->setUserProfile($profile);
            $em->persist($profile);
        }
        $em->flush();

        return $this->jsonNoStore([
            'user' => $serializer->serialize($user),
        ]);
    }

    #[Route('/compte', name: 'api_mobile_account_delete', methods: ['DELETE'])]
    public function deleteAccount(
        Request $request,
        UserPasswordHasherInterface $hasher,
        AccountDeleter $deleter,
        AccountMailer $mailer,
        RateLimits $limits,
        TokenStorageInterface $tokenStorage,
    ): JsonResponse {
        $user = $this->currentMobileUser();

        if (!$limits->allow('account_security', (string) $user->getId())) {
            return $this->jsonError('Trop de tentatives. Patientez quelques minutes avant de réessayer.', Response::HTTP_TOO_MANY_REQUESTS, 'rate_limited');
        }

        $data = $this->decodeJson($request);
        if (null === $data) {
            return $this->jsonError('Confirmation et mot de passe actuel requis.', Response::HTTP_BAD_REQUEST);
        }

        $confirmation = \is_string($data['confirmation'] ?? null) ? $data['confirmation'] : '';
        $currentPassword = \is_string($data['currentPassword'] ?? null) ? $data['currentPassword'] : '';

        if ('SUPPRIMER' !== mb_strtoupper(trim($confirmation))
            || '' === $currentPassword
            || !$hasher->isPasswordValid($user, $currentPassword)) {
            return $this->jsonError('Suppression annulée : tapez SUPPRIMER et indiquez votre mot de passe actuel.', Response::HTTP_BAD_REQUEST, 'confirmation_required');
        }

        $email = (string) $user->getEmail();
        $name = $user->getDisplayName();

        $deleter->delete($user);
        $tokenStorage->setToken(null);
        $mailer->sendAccountDeleted($email, $name);

        return $this->jsonNoStore(['ok' => true]);
    }
}
