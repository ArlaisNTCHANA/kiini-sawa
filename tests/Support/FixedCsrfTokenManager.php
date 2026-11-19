<?php

namespace App\Tests\Support;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Gestionnaire de jetons CSRF de l'environnement de TEST uniquement (config/services.yaml, when@test) : tous les formulaires affichent
 * le même jeton connu, ce qui permet aux tests d'envoyer des formulaires sans passer par la page. Un jeton absent ou différent
 * reste refusé : les tests de la protection (jeton manquant, mauvais jeton) gardent tout leur sens.
 */
final class FixedCsrfTokenManager implements CsrfTokenManagerInterface
{
    public const VALUE = 'jeton-de-test';

    public function getToken(string $tokenId): CsrfToken
    {
        return new CsrfToken($tokenId, self::VALUE);
    }

    public function refreshToken(string $tokenId): CsrfToken
    {
        return $this->getToken($tokenId);
    }

    public function removeToken(string $tokenId): ?string
    {
        return self::VALUE;
    }

    public function isTokenValid(CsrfToken $token): bool
    {
        return hash_equals(self::VALUE, $token->getValue());
    }
}
