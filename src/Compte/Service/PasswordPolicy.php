<?php

namespace App\Compte\Service;

/**
 * Règles communes à tous les mots de passe choisis par un utilisateur
 * (inscription, réinitialisation, changement depuis le profil).
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 10;

    /** Renvoie un message d'erreur, ou null si le mot de passe est acceptable. */
    public function validate(string $password, ?string $email = null): ?string
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return 'Le mot de passe doit contenir au moins '.self::MIN_LENGTH.' caractères.';
        }
        if (\strlen($password) > 4096) {
            return 'Le mot de passe est trop long.';
        }
        $localPart = mb_strtolower(explode('@', (string) $email)[0]);
        if (mb_strlen($localPart) >= 4 && str_contains(mb_strtolower($password), $localPart)) {
            return 'Le mot de passe ne doit pas contenir votre adresse e-mail.';
        }

        return null;
    }
}
