<?php

namespace App\Compte\Service;

/**
 * Date de naissance : lecture du champ, âge, âge minimum des CGU et jour d'anniversaire.
 *
 * CGU : inscription réservée aux personnes majeures, ou de plus de 15 ans avec l'accord de leurs parents.
 * La date reste privée : elle ne sert qu'à vérifier l'âge et à souhaiter l'anniversaire (jamais montrée à quelqu'un d'autre).
 */
final class BirthDate
{
    public const MIN_AGE = 15;
    public const ADULT_AGE = 18;
    public const MAX_AGE = 120;

    /** Résultats de validation (codes de texte de BirthdayTexts). */
    public const OK = null;
    public const MISSING = 'naissance.erreur.manquante';
    public const INVALID = 'naissance.erreur.invalide';
    public const TOO_YOUNG = 'naissance.erreur.trop_jeune';

    /** Lit « AAAA-MM-JJ » (champ date des navigateurs) ou « JJ/MM/AAAA » ; null si la date n'existe pas. */
    public static function parse(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})$#', $value, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }
        if (!checkdate($mo, $d, $y)) {
            return null;
        }

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $mo, $d));
    }

    public static function age(\DateTimeInterface $birth, \DateTimeInterface $today): int
    {
        return (int) \DateTimeImmutable::createFromInterface($birth)->setTime(0, 0)
            ->diff(\DateTimeImmutable::createFromInterface($today)->setTime(0, 0))->format('%r%y');
    }

    /**
     * Vérifie une date saisie. Renvoie null si elle convient, sinon le code du message à afficher.
     */
    public static function check(?\DateTimeInterface $birth, \DateTimeInterface $today): ?string
    {
        if (null === $birth) {
            return self::MISSING;
        }
        $age = self::age($birth, $today);
        if ($birth > $today || $age > self::MAX_AGE) {
            return self::INVALID;
        }

        return $age < self::MIN_AGE ? self::TOO_YOUNG : self::OK;
    }

    /** Même vérification à partir du texte saisi (vide : manquante ; illisible : invalide). */
    public static function checkInput(string $value, \DateTimeInterface $today): ?string
    {
        if ('' === trim($value)) {
            return self::MISSING;
        }
        $birth = self::parse($value);

        return null === $birth ? self::INVALID : self::check($birth, $today);
    }

    /** Entre 15 et 17 ans : l'accord des parents est demandé (CGU). */
    public static function needsParentalConsent(\DateTimeInterface $birth, \DateTimeInterface $today): bool
    {
        $age = self::age($birth, $today);

        return $age >= self::MIN_AGE && $age < self::ADULT_AGE;
    }

    /** Jour d'anniversaire ; les personnes nées un 29 février sont fêtées le 28 février les années non bissextiles. */
    public static function isBirthday(\DateTimeInterface $birth, \DateTimeInterface $today): bool
    {
        $md = $birth->format('m-d');
        if ('02-29' === $md && !$today->format('L')) {
            $md = '02-28';
        }

        return $md === $today->format('m-d') && $birth->format('Y') < $today->format('Y');
    }
}
