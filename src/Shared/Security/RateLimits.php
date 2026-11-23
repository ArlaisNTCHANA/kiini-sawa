<?php

namespace App\Shared\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Limites anti-abus nommées (config/packages/rate_limiter.yaml) : `allow()` pour un refus silencieux, `require()` pour répondre « 429 ».
 * La clé est l'identifiant du compte (actions connectées) ou l'adresse IP (formulaires publics).
 */
final class RateLimits
{
    /** @var array<string, RateLimiterFactory> */
    private array $factories;

    public function __construct(
        #[Autowire(service: 'limiter.push_test')] RateLimiterFactory $pushTestLimiter,
        #[Autowire(service: 'limiter.org_invite')] RateLimiterFactory $orgInviteLimiter,
        #[Autowire(service: 'limiter.org_invite_licensed')] RateLimiterFactory $orgInviteLicensedLimiter,
        #[Autowire(service: 'limiter.org_create')] RateLimiterFactory $orgCreateLimiter,
        #[Autowire(service: 'limiter.couple_create')] RateLimiterFactory $coupleCreateLimiter,
        #[Autowire(service: 'limiter.plan_email')] RateLimiterFactory $planEmailLimiter,
        #[Autowire(service: 'limiter.account_security')] RateLimiterFactory $accountSecurityLimiter,
        #[Autowire(service: 'limiter.public_form')] RateLimiterFactory $publicFormLimiter,
        #[Autowire(service: 'limiter.public_form_ip')] RateLimiterFactory $publicFormIpLimiter,
        #[Autowire(service: 'limiter.mobile_login_ip')] RateLimiterFactory $mobileLoginIpLimiter,
        #[Autowire(service: 'limiter.mobile_login_account')] RateLimiterFactory $mobileLoginAccountLimiter,
        #[Autowire(service: 'limiter.mobile_refresh')] RateLimiterFactory $mobileRefreshLimiter,
        #[Autowire(service: 'limiter.accountability_invite')] RateLimiterFactory $accountabilityInviteLimiter,
        #[Autowire(service: 'limiter.boussole_submit')] RateLimiterFactory $boussoleSubmitLimiter,
        #[Autowire(service: 'limiter.boussole_submit_ip')] RateLimiterFactory $boussoleSubmitIpLimiter,
    ) {
        $this->factories = [
            'push_test' => $pushTestLimiter, 'org_invite' => $orgInviteLimiter, 'org_invite_licensed' => $orgInviteLicensedLimiter,
            'org_create' => $orgCreateLimiter, 'couple_create' => $coupleCreateLimiter, 'plan_email' => $planEmailLimiter, 'account_security' => $accountSecurityLimiter,
            'public_form' => $publicFormLimiter, 'public_form_ip' => $publicFormIpLimiter,
            'mobile_login_ip' => $mobileLoginIpLimiter, 'mobile_login_account' => $mobileLoginAccountLimiter, 'mobile_refresh' => $mobileRefreshLimiter,
            'accountability_invite' => $accountabilityInviteLimiter,
            'boussole_submit' => $boussoleSubmitLimiter, 'boussole_submit_ip' => $boussoleSubmitIpLimiter,
        ];
    }

    /** Consomme un jeton ; false si la limite est atteinte. */
    public function allow(string $limiter, string $key, int $tokens = 1): bool
    {
        return $this->factories[$limiter]->create($limiter.'|'.$key)->consume($tokens)->isAccepted();
    }

    /** @throws TooManyRequestsHttpException quand la limite est atteinte */
    public function require(string $limiter, string $key, string $message = 'Trop de tentatives. Merci de réessayer un peu plus tard.'): void
    {
        $limit = $this->factories[$limiter]->create($limiter.'|'.$key)->consume();
        if (!$limit->isAccepted()) {
            $retry = $limit->getRetryAfter();
            throw new TooManyRequestsHttpException(max(1, $retry->getTimestamp() - time()), $message);
        }
    }
}
