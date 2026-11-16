<?php

namespace App\Abonnement\Service;

use App\Compte\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Devise affichée par défaut : franc CFA pour les pays de la zone CFA (Afrique centrale et de l'Ouest), euro sinon.
 * Ordre : choix explicite (?devise=), pays du profil, région de la langue du navigateur.
 */
final class CurrencyResolver
{
    /** Codes ISO des pays de la zone franc CFA (XAF et XOF). */
    public const CFA_COUNTRY_CODES = ['CM', 'GA', 'CG', 'TD', 'CF', 'GQ', 'SN', 'CI', 'ML', 'BF', 'NE', 'TG', 'BJ', 'GW'];

    /** Noms de pays tels que saisis dans le profil (comparés sans accents ni majuscules). */
    private const CFA_COUNTRY_NAMES = ['cameroun', 'cameroon', 'gabon', 'congo', 'congo-brazzaville', 'republique du congo', 'tchad', 'chad',
        'centrafrique', 'republique centrafricaine', 'guinee equatoriale', 'senegal', 'cote d\'ivoire', 'cote divoire', 'ivory coast',
        'mali', 'burkina faso', 'burkina', 'niger', 'togo', 'benin', 'guinee-bissau', 'guinee bissau'];

    /** Fuseaux horaires de la zone CFA (utilisés côté navigateur sur la page d'accueil). */
    public const CFA_TIMEZONES = ['Africa/Douala', 'Africa/Libreville', 'Africa/Brazzaville', 'Africa/Ndjamena', 'Africa/Bangui', 'Africa/Malabo',
        'Africa/Dakar', 'Africa/Abidjan', 'Africa/Bamako', 'Africa/Ouagadougou', 'Africa/Niamey', 'Africa/Lome', 'Africa/Porto-Novo', 'Africa/Bissau'];

    public function resolve(Request $request, ?User $user = null): string
    {
        $explicit = strtoupper($request->query->getString('devise'));
        if (\in_array($explicit, ['EUR', 'XAF'], true)) {
            return $explicit;
        }
        if (null !== ($country = $user?->getUserProfile()?->getCountry()) && '' !== trim($country)) {
            return $this->isCfaCountryName($country) ? 'XAF' : 'EUR';
        }
        foreach ($request->getLanguages() as $lang) {
            $region = strtoupper((string) (preg_split('/[-_]/', $lang)[1] ?? ''));
            if ('' !== $region) {
                return \in_array($region, self::CFA_COUNTRY_CODES, true) ? 'XAF' : 'EUR';
            }
        }

        return 'EUR';
    }

    public function isCfaCountryName(string $country): bool
    {
        $n = mb_strtolower(trim($country));
        $n = strtr($n, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ô' => 'o', 'î' => 'i', '’' => '\'']);

        return \in_array($n, self::CFA_COUNTRY_NAMES, true) || \in_array(strtoupper($n), self::CFA_COUNTRY_CODES, true);
    }
}
