<?php

namespace App\Media\Service;

use App\Abonnement\Enum\SubscriptionStatus;
use App\Abonnement\Service\SubscriptionManager;
use App\Compte\Entity\User;
use App\Media\Entity\Referral;
use App\Media\Repository\ReferralRepository;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parrainage : chaque membre a un lien personnel (/i/{code}).
 * - Le filleul a un essai PRO d'un mois (au lieu de 14 jours).
 * - Le parrain reçoit 1 mois PRO quand le filleul vérifie son email (12 récompenses par an au plus).
 *
 * Le code se calcule à partir de l'identifiant du compte et d'une signature : rien à stocker, impossible à deviner.
 */
final class ReferralService
{
    public const COOKIE = 'ks_parrain';
    public const REWARD_MONTHS = 1;
    public const REFERRED_TRIAL_DAYS = 30;
    public const MAX_REWARDS_PER_YEAR = 12;
    /** Récompenses au plus par parrain pour des filleuls inscrits depuis une même adresse IP (un foyer, un bureau : une seule). */
    public const MAX_REWARDS_PER_IP = 1;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReferralRepository $referrals,
        private readonly SubscriptionManager $subscriptions,
        private readonly Notifier $notifier,
        #[Autowire('%kernel.secret%')] private readonly string $secret,
    ) {
    }

    public function codeFor(User $user): string
    {
        return strtoupper($this->signature((int) $user->getId()).base_convert((string) $user->getId(), 10, 36));
    }

    public function referrerFromCode(string $code): ?User
    {
        $code = strtolower(trim($code));
        if (!preg_match('/^[a-z0-9]{5,16}$/', $code)) {
            return null;
        }
        $id = (int) base_convert(substr($code, 4), 36, 10);
        if ($id <= 0 || !hash_equals($this->signature($id), substr($code, 0, 4))) {
            return null;
        }
        $user = $this->em->find(User::class, $id);

        return $user instanceof User && $user->isVerified() ? $user : null;
    }

    public function rememberCode(string $code, Request $request, Response $response): void
    {
        $response->headers->setCookie(Cookie::create(self::COOKIE, strtoupper($code), new \DateTimeImmutable('+30 days'))
            ->withSecure($request->isSecure())->withHttpOnly(true)->withSameSite(Cookie::SAMESITE_LAX));
    }

    /** À l'inscription : rattache le filleul à son parrain et allonge son essai. */
    public function attach(User $newUser, Request $request): ?Referral
    {
        $code = $request->cookies->get(self::COOKIE);
        $referrer = \is_string($code) ? $this->referrerFromCode($code) : null;
        // Pas d'auto-parrainage, y compris par alias d'adresse : jean.dupont+a@gmail.com, j.e.a.n.dupont@gmail.com, jean.dupont@googlemail.com…
        if (null === $referrer || $referrer === $newUser || self::normalizeEmail((string) $referrer->getEmail()) === self::normalizeEmail((string) $newUser->getEmail())) {
            return null;
        }
        $referral = new Referral($referrer, $newUser);
        $referral->setSignupIpHash($this->ipHash($request->getClientIp()));
        $this->em->persist($referral);
        $sub = $newUser->getSubscription();
        if ($sub && SubscriptionStatus::TRIAL === $sub->getStatus() && $sub->getTrialStartAt()) {
            $sub->setTrialEndAt((\DateTime::createFromInterface($sub->getTrialStartAt()))->modify('+'.self::REFERRED_TRIAL_DAYS.' days'));
        }

        return $referral;
    }

    public function forget(Response $response): void
    {
        $response->headers->clearCookie(self::COOKIE);
    }

    /** À la vérification de l'email du filleul : récompense le parrain (une fois, dans la limite annuelle). */
    public function reward(User $referred): bool
    {
        $referral = $this->referrals->findOneBy(['referred' => $referred]);
        if (null === $referral || null !== $referral->getRewardedAt()) {
            return false;
        }
        $referrer = $referral->getReferrer();
        if ($this->referrals->rewardedSince($referrer, new \DateTimeImmutable('-1 year')) >= self::MAX_REWARDS_PER_YEAR) {
            return false;
        }
        // Même adresse IP que d'autres filleuls déjà récompensés pour ce parrain : pas de nouvelle récompense (comptes en série)
        $hash = $referral->getSignupIpHash();
        if (null !== $hash && $this->referrals->rewardedFromSameIp($referrer, $hash) >= self::MAX_REWARDS_PER_IP) {
            return false;
        }
        $referral->markRewarded();
        $this->subscriptions->gift($referrer, self::REWARD_MONTHS, 'Parrainage de '.$referred->getEmail(), false);
        $this->em->flush();
        $this->notifier->notify($referrer, NotificationType::REFERRAL_REWARD,
            $referred->getDisplayName().' a rejoint KIINI SAWA grâce à vous : 1 mois PRO offert',
            'Merci de faire connaître KIINI SAWA ! Un mois PRO vient d\'être ajouté à votre compte. Continuez : chaque ami qui crée son compte vous offre un mois de plus.',
            '/parrainage', 'parrainage '.$referral->getId());

        return true;
    }

    /** @return array{invited: int, rewarded: int, monthsEarned: int} */
    public function stats(User $referrer): array
    {
        $list = $this->referrals->forReferrer($referrer);
        $rewarded = \count(array_filter($list, fn (Referral $r) => null !== $r->getRewardedAt()));

        return ['invited' => \count($list), 'rewarded' => $rewarded, 'monthsEarned' => $rewarded * self::REWARD_MONTHS];
    }

    /**
     * Forme canonique d'une adresse e-mail pour comparer des alias d'une même boîte : minuscules, sans « +étiquette » ; chez Gmail
     * (gmail.com et googlemail.com), sans les points de la partie locale non plus.
     */
    public static function normalizeEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));
        $at = strrpos($email, '@');
        if (false === $at) {
            return $email;
        }
        [$local, $domain] = [substr($email, 0, $at), substr($email, $at + 1)];
        $local = explode('+', $local, 2)[0];
        if (\in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local.'@'.$domain;
    }

    private function ipHash(?string $ip): ?string
    {
        return $ip ? hash_hmac('sha256', 'parrainage|'.$ip, $this->secret) : null;
    }

    private function signature(int $id): string
    {
        return substr(str_pad(base_convert(substr(hash_hmac('sha256', 'parrainage-'.$id, $this->secret), 0, 10), 16, 36), 4, '0', \STR_PAD_LEFT), 0, 4);
    }
}
