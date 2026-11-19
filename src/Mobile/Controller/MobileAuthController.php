<?php

namespace App\Mobile\Controller;

use App\Compte\Repository\UserRepository;
use App\Mobile\Service\MobileTokenService;
use App\Mobile\Service\MobileUserSerializer;
use App\Shared\Security\RateLimits;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Authentification de l'application mobile par jetons Bearer :
 * connexion, rotation du jeton de rafraîchissement, déconnexion.
 */
#[Route('/api/mobile/v1')]
class MobileAuthController extends AbstractController
{
    use MobileJsonTrait;

    #[Route('/connexion', name: 'api_mobile_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        MobileTokenService $tokenService,
        MobileUserSerializer $serializer,
        RateLimits $limits,
    ): JsonResponse {
        $ip = $request->getClientIp() ?? 'unknown';
        if (!$limits->allow('mobile_login_ip', $ip)) {
            return $this->jsonError('Trop de tentatives. Merci de patienter quelques minutes.', Response::HTTP_TOO_MANY_REQUESTS, 'rate_limited');
        }

        $data = $this->decodeJson($request);
        if (null === $data || !\is_string($data['email'] ?? null) || !\is_string($data['password'] ?? null)) {
            return $this->jsonError('Adresse e-mail et mot de passe requis.', Response::HTTP_BAD_REQUEST, 'invalid_payload');
        }

        $email = mb_strtolower(trim($data['email']));
        $password = $data['password'];
        if ('' === $email || '' === $password || \strlen($email) > 180) {
            return $this->jsonError('Adresse e-mail et mot de passe requis.', Response::HTTP_BAD_REQUEST, 'invalid_payload');
        }

        if (!$limits->allow('mobile_login_account', $email)) {
            return $this->jsonError('Trop de tentatives sur ce compte. Merci de patienter 15 minutes.', Response::HTTP_TOO_MANY_REQUESTS, 'rate_limited');
        }

        $user = $users->findOneBy(['email' => $email]);
        if (null === $user || !$hasher->isPasswordValid($user, $password)) {
            return $this->jsonError('Adresse e-mail ou mot de passe incorrect.', Response::HTTP_UNAUTHORIZED, 'invalid_credentials');
        }

        if (!$user->isVerified()) {
            return $this->jsonError('Votre adresse e-mail n\'est pas encore vérifiée. Confirmez-la sur le site avant de vous connecter.', Response::HTTP_FORBIDDEN, 'email_unverified');
        }

        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->jsonError('Les comptes administrateurs doivent se connecter sur le site web.', Response::HTTP_FORBIDDEN, 'admin_forbidden');
        }

        $deviceName = \is_string($data['appareil'] ?? null) ? $data['appareil'] : null;
        $issued = $tokenService->issue($user, $deviceName);

        return $this->jsonNoStore([
            'accessToken' => $issued['accessToken'],
            'refreshToken' => $issued['refreshToken'],
            'expiresIn' => $issued['expiresIn'],
            'user' => $serializer->serialize($user),
        ]);
    }

    #[Route('/jeton', name: 'api_mobile_refresh', methods: ['POST'])]
    public function refresh(
        Request $request,
        MobileTokenService $tokenService,
        MobileUserSerializer $serializer,
        RateLimits $limits,
    ): JsonResponse {
        $ip = $request->getClientIp() ?? 'unknown';
        if (!$limits->allow('mobile_refresh', $ip)) {
            return $this->jsonError('Trop de tentatives. Merci de patienter quelques minutes.', Response::HTTP_TOO_MANY_REQUESTS, 'rate_limited');
        }

        $data = $this->decodeJson($request);
        if (null === $data || !\is_string($data['refreshToken'] ?? null) || '' === trim($data['refreshToken'])) {
            return $this->jsonError('Jeton de rafraîchissement requis.', Response::HTTP_BAD_REQUEST, 'invalid_payload');
        }

        $rotated = $tokenService->rotate($data['refreshToken']);
        if (null === $rotated) {
            return $this->jsonError('Votre session a expiré. Veuillez vous reconnecter.', Response::HTTP_UNAUTHORIZED, 'refresh_invalid');
        }

        return $this->jsonNoStore([
            'accessToken' => $rotated['accessToken'],
            'refreshToken' => $rotated['refreshToken'],
            'expiresIn' => $rotated['expiresIn'],
            'user' => $serializer->serialize($rotated['user']),
        ]);
    }

    #[Route('/deconnexion', name: 'api_mobile_logout', methods: ['POST'])]
    public function logout(Request $request, MobileTokenService $tokenService): JsonResponse
    {
        $header = (string) $request->headers->get('Authorization', '');
        $rawAccess = str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : null;

        $data = $this->decodeJson($request) ?? [];
        $rawRefresh = \is_string($data['refreshToken'] ?? null) ? $data['refreshToken'] : null;

        if (true === ($data['tous'] ?? false) && null !== $rawAccess) {
            $token = $tokenService->findActiveByAccessToken($rawAccess);
            if (null !== $token) {
                $tokenService->revokeAll($token->getUser());

                return $this->jsonNoStore(['ok' => true]);
            }
        }

        $tokenService->revokeByRawToken($rawAccess, $rawRefresh);

        return $this->jsonNoStore(['ok' => true]);
    }
}
