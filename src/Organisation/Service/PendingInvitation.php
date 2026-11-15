<?php

namespace App\Organisation\Service;

/**
 * Invitation en attente (organisation ou conjoint) : un visiteur qui ouvre le lien sans compte est accueilli,
 * crée son compte ou se connecte, puis revient automatiquement sur l'invitation.
 */
final class PendingInvitation
{
    public const SESSION_KEY = 'pending_org_invite';
    public const COUPLE_KEY = 'pending_couple_invite';

    public static function redirectPath(?\Symfony\Component\HttpFoundation\Session\SessionInterface $session): ?string
    {
        foreach ([self::COUPLE_KEY => '/couple/rejoindre/', self::SESSION_KEY => '/rejoindre/'] as $key => $prefix) {
            $code = $session?->get($key);
            if (\is_string($code) && preg_match('/^[a-f0-9]{16}$/', $code)) {
                return $prefix.$code;
            }
        }

        return null;
    }
}
