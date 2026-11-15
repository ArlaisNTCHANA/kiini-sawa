<?php

namespace App\Organisation\Service;

/**
 * Invitation en attente : un visiteur qui ouvre /rejoindre/{code} sans compte est accueilli,
 * crée son compte ou se connecte, puis revient automatiquement sur l'invitation.
 */
final class PendingInvitation
{
    public const SESSION_KEY = 'pending_org_invite';

    public static function redirectPath(?\Symfony\Component\HttpFoundation\Session\SessionInterface $session): ?string
    {
        $code = $session?->get(self::SESSION_KEY);

        return \is_string($code) && preg_match('/^[a-f0-9]{16}$/', $code) ? '/rejoindre/'.$code : null;
    }
}
