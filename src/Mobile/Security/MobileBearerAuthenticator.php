<?php

namespace App\Mobile\Security;

use App\Mobile\Repository\MobileTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Authentification sans session (Bearer) pour le pare-feu `mobile_api`.
 * Chaque jeton d'accès est vérifié par son empreinte SHA-256 en base.
 */
final class MobileBearerAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const PUBLIC_PATHS = [
        '/api/mobile/v1/connexion',
        '/api/mobile/v1/jeton',
        '/api/mobile/v1/deconnexion',
    ];

    public function __construct(
        private readonly MobileTokenRepository $tokens,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        if (\in_array($request->getPathInfo(), self::PUBLIC_PATHS, true)) {
            return false;
        }

        $header = (string) $request->headers->get('Authorization', '');

        return str_starts_with($header, 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $header = (string) $request->headers->get('Authorization', '');
        $rawToken = trim(substr($header, 7));

        if (\strlen($rawToken) < 32 || \strlen($rawToken) > 128) {
            throw new CustomUserMessageAuthenticationException('Jeton invalide ou expiré.');
        }

        $now = new \DateTimeImmutable();
        $token = $this->tokens->findActiveByAccessHash(hash('sha256', $rawToken), $now);
        if (null === $token) {
            throw new CustomUserMessageAuthenticationException('Jeton invalide ou expiré.');
        }

        $user = $token->getUser();
        if (!$user->isVerified()) {
            throw new CustomUserMessageAuthenticationException('Adresse email non vérifiée.');
        }
        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw new CustomUserMessageAuthenticationException('Accès mobile indisponible pour ce compte.');
        }

        $lastUsed = $token->getLastUsedAt();
        if (null === $lastUsed || $lastUsed < $now->modify('-5 minutes')) {
            $this->em->getConnection()->update('mobile_token', [
                'last_used_at' => $now->format('Y-m-d H:i:s'),
            ], ['id' => $token->getId()]);
        }

        $request->attributes->set('_mobile_token_id', $token->getId());

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), static fn () => $user)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = $exception instanceof CustomUserMessageAuthenticationException
            ? $exception->getMessageKey()
            : 'Authentification requise.';

        return $this->unauthorizedResponse($message);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->unauthorizedResponse('Authentification requise.');
    }

    private function unauthorizedResponse(string $message): JsonResponse
    {
        $response = new JsonResponse(['error' => $message], Response::HTTP_UNAUTHORIZED);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
