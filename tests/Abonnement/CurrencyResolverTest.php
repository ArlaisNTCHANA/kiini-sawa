<?php

namespace App\Tests\Abonnement;

use App\Abonnement\Service\CurrencyResolver;
use App\Compte\Entity\User;
use App\Compte\Entity\UserProfile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class CurrencyResolverTest extends TestCase
{
    private function request(string $lang = '', string $query = ''): Request
    {
        $r = Request::create('/abonnement'.($query ? '?'.$query : ''));
        if ($lang) {
            $r->headers->set('Accept-Language', $lang);
        }

        return $r;
    }

    private function userFrom(string $country): User
    {
        $u = new User();
        $p = (new UserProfile())->setCountry($country);
        $p->setUser($u);
        $u->setUserProfile($p);

        return $u;
    }

    public function testExplicitChoiceWins(): void
    {
        $this->assertSame('EUR', (new CurrencyResolver())->resolve($this->request('fr-CM', 'devise=eur'), $this->userFrom('Cameroun')));
    }

    public function testProfileCountryDecides(): void
    {
        $r = new CurrencyResolver();
        foreach (['Cameroun', 'Côte d\'Ivoire', 'Sénégal', 'gabon', 'BF'] as $c) {
            $this->assertSame('XAF', $r->resolve($this->request('fr-FR'), $this->userFrom($c)), $c);
        }
        foreach (['France', 'Belgique', 'Ghana', 'Canada'] as $c) {
            $this->assertSame('EUR', $r->resolve($this->request('fr-CM'), $this->userFrom($c)), $c);
        }
    }

    public function testBrowserRegionThenEuroByDefault(): void
    {
        $r = new CurrencyResolver();
        $this->assertSame('XAF', $r->resolve($this->request('fr-SN,fr;q=0.8')));
        $this->assertSame('EUR', $r->resolve($this->request('fr-FR,fr;q=0.8')));
        $this->assertSame('EUR', $r->resolve($this->request('fr')));
        $this->assertSame('EUR', $r->resolve($this->request()));
    }
}
