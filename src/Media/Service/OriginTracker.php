<?php

namespace App\Media\Service;

use App\Compte\Entity\User;
use App\Media\Entity\SignupOrigin;
use App\Organisation\Service\PendingInvitation;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provenance des visiteurs puis des inscrits.
 *
 * Un cookie propre au site (« ks_origine », 30 jours, aucune donnée personnelle, jamais partagé) retient le canal
 * de la dernière arrivée par un lien suivi, ou à défaut du premier site d'origine. À l'inscription, il est recopié
 * dans SignupOrigin puis effacé. Mesure d'audience au sens de la CNIL : usage strictement statistique.
 */
final class OriginTracker
{
    public const COOKIE = 'ks_origine';
    private const TTL_DAYS = 30;

    /** Domaine d'origine => canal */
    private const REFERRER_CHANNELS = [
        'facebook.com' => 'facebook', 'fb.com' => 'facebook', 'fb.me' => 'facebook', 'messenger.com' => 'facebook',
        'instagram.com' => 'instagram', 'tiktok.com' => 'tiktok', 'youtube.com' => 'youtube', 'youtu.be' => 'youtube',
        'whatsapp.com' => 'whatsapp', 'wa.me' => 'whatsapp', 'linkedin.com' => 'linkedin', 'lnkd.in' => 'linkedin',
        't.co' => 'x', 'twitter.com' => 'x', 'x.com' => 'x', 'google.' => 'google', 'bing.com' => 'bing',
        'kiinisawa.com' => 'site-vitrine',
    ];

    /** Origine portée par la requête : paramètres utm_*, sinon site d'origine externe ; null si rien d'utile. */
    public function fromRequest(Request $request): ?array
    {
        $q = $request->query;
        $source = self::clean($q->getString('utm_source'), 60);
        if (null !== $source) {
            return [
                'source' => self::normaliseSource($source),
                'medium' => self::clean($q->getString('utm_medium'), 60),
                'campaign' => self::clean($q->getString('utm_campaign'), 120),
                'content' => self::clean($q->getString('utm_content'), 120),
                'referrer' => $this->referrerHost($request),
                'landing' => mb_substr($request->getPathInfo(), 0, 190),
            ];
        }
        $host = $this->referrerHost($request);
        if (null !== $host) {
            return ['source' => $this->channelOf($host), 'medium' => 'referral', 'campaign' => null, 'content' => null,
                'referrer' => $host, 'landing' => mb_substr($request->getPathInfo(), 0, 190)];
        }

        return null;
    }

    /** Pose ou met à jour le cookie : un lien suivi remplace toujours ; un simple site d'origine ne remplace rien. */
    public function remember(Request $request, Response $response): void
    {
        $origin = $this->fromRequest($request);
        if (null === $origin) {
            return;
        }
        $existing = $this->fromCookie($request);
        $isTracked = '' !== $request->query->getString('utm_source');
        if (null !== $existing && !$isTracked) {
            return;
        }
        $response->headers->setCookie(Cookie::create(self::COOKIE, base64_encode((string) json_encode($origin)), new \DateTimeImmutable('+'.self::TTL_DAYS.' days'))
            ->withSecure($request->isSecure())->withHttpOnly(true)->withSameSite(Cookie::SAMESITE_LAX));
    }

    public function fromCookie(Request $request): ?array
    {
        $raw = $request->cookies->get(self::COOKIE);
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }
        $data = json_decode((string) base64_decode($raw, true), true);
        if (!\is_array($data) || !isset($data['source']) || !\is_string($data['source'])) {
            return null;
        }

        return array_map(fn ($v) => \is_string($v) ? mb_substr($v, 0, 190) : null, $data + ['medium' => null, 'campaign' => null, 'content' => null, 'referrer' => null, 'landing' => null]);
    }

    /** Provenance d'un nouvel inscrit : invitation, sinon cookie, sinon requête en cours, sinon « direct ». */
    public function originFor(User $user, Request $request): SignupOrigin
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session?->has(PendingInvitation::COUPLE_KEY)) {
            return (new SignupOrigin($user, 'invitation'))->setMedium('couple');
        }
        if ($session?->has(PendingInvitation::SESSION_KEY)) {
            return (new SignupOrigin($user, 'invitation'))->setMedium('organisation');
        }
        $o = $this->fromCookie($request) ?? $this->fromRequest($request);
        if (null === $o) {
            return new SignupOrigin($user, 'direct');
        }

        return (new SignupOrigin($user, self::normaliseSource($o['source'])))
            ->setMedium($o['medium'])->setCampaign($o['campaign'])->setContent($o['content'])
            ->setReferrer($o['referrer'])->setLandingPath($o['landing']);
    }

    public function forget(Response $response): void
    {
        $response->headers->clearCookie(self::COOKIE);
    }

    public function channelOf(string $host): string
    {
        foreach (self::REFERRER_CHANNELS as $domain => $channel) {
            if (str_ends_with($host, $domain) || (str_ends_with($domain, '.') && str_contains($host, $domain))) {
                return $channel;
            }
        }

        return 'autre';
    }

    private function referrerHost(Request $request): ?string
    {
        $host = parse_url((string) $request->headers->get('referer'), \PHP_URL_HOST);
        if (!\is_string($host) || '' === $host) {
            return null;
        }
        $host = mb_strtolower(preg_replace('/^www\./', '', $host));
        // Navigation interne : ce n'est pas une provenance
        if ($host === mb_strtolower(preg_replace('/^www\./', '', $request->getHost()))) {
            return null;
        }

        return mb_substr($host, 0, 190);
    }

    private static function normaliseSource(string $source): string
    {
        $s = mb_strtolower(trim($source));
        $aliases = ['fb' => 'facebook', 'ig' => 'instagram', 'insta' => 'instagram', 'yt' => 'youtube', 'tt' => 'tiktok', 'wa' => 'whatsapp', 'email' => 'newsletter', 'mail' => 'newsletter'];

        return mb_substr(preg_replace('/[^a-z0-9._-]+/', '-', $aliases[$s] ?? $s), 0, 60);
    }

    private static function clean(string $value, int $max): ?string
    {
        $v = trim(preg_replace('/[^\p{L}\p{N} ._\-\/]+/u', '', $value));

        return '' === $v ? null : mb_strtolower(mb_substr($v, 0, $max));
    }
}
