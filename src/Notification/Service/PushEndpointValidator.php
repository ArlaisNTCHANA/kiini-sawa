<?php

namespace App\Notification\Service;

/**
 * Protège contre le SSRF du Web Push : le serveur appelle l'adresse (« endpoint ») fournie par le navigateur de l'utilisateur ; sans
 * contrôle, quelqu'un pourrait faire émettre des requêtes vers le réseau interne de l'hébergeur ou vers n'importe quel site.
 *
 * Règles : HTTPS seulement, sans identifiants ni port particulier, hôte dans la liste des services Web Push officiels
 * (Google FCM, Mozilla, Apple, Microsoft), et l'hôte ne doit jamais se résoudre vers une adresse privée, locale ou réservée
 * (contrôlé à l'inscription ET avant chaque envoi, contre le « DNS rebinding »). En cas de doute (résolution impossible) : refus.
 */
final class PushEndpointValidator
{
    /** Hôtes exacts. */
    private const HOSTS = ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'];
    /** Suffixes de domaine (sous-domaines des services officiels). */
    private const SUFFIXES = ['.push.services.mozilla.com', '.push.apple.com', '.notify.windows.com'];

    /** Remplaçable dans les tests (aucun accès réseau) : fn(string $host): list<string> d'adresses IP. */
    public static ?\Closure $resolver = null;

    public function isAllowed(string $endpoint): bool
    {
        if (\strlen($endpoint) > 2000 || preg_match('/[\x00-\x20\x7F]/', $endpoint)) {
            return false;
        }
        $parts = parse_url($endpoint);
        if (!$parts || 'https' !== strtolower($parts['scheme'] ?? '') || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (isset($parts['port']) && 443 !== $parts['port'])) {
            return false;
        }
        $host = strtolower(rtrim($parts['host'] ?? '', '.'));
        if ('' === $host || filter_var($host, \FILTER_VALIDATE_IP) || !$this->hostIsOfficial($host)) {
            return false;
        }

        return $this->resolvesToPublicAddressesOnly($host);
    }

    public function hostIsOfficial(string $host): bool
    {
        if (\in_array($host, self::HOSTS, true)) {
            return true;
        }
        foreach (self::SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix) && \strlen($host) > \strlen($suffix)) {
                return true;
            }
        }

        return false;
    }

    private function resolvesToPublicAddressesOnly(string $host): bool
    {
        $ips = (self::$resolver ?? self::systemResolve(...))($host);
        if ([] === $ips) {
            return false;
        }
        foreach ($ips as $ip) {
            if (false === filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private static function systemResolve(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, \DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
