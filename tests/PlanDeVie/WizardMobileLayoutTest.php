<?php

namespace App\Tests\PlanDeVie;

use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Assistants des plans sur téléphone (parcours « nouvel utilisateur » du 25/11/2026, chantier 1) : la carte suit la
 * page au lieu de défiler dans une petite fenêtre, et les listes d'objectifs ne défilent plus à l'intérieur.
 * Le rendu a été vérifié à 375 px sur les 13 étapes ; ce test garde les règles en place.
 */
final class WizardMobileLayoutTest extends WebTestCase
{
    use AccountTestTrait;

    public function testPhoneLayoutHasNoNestedScrolling(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $client->loginUser($user);

        foreach (['/plan-de-vie/assistant', '/plan-de-vie/assistant?step=9&single=0&plan=CROISSANCE'] as $url) {
            $client->request('GET', $url);
            $this->assertResponseIsSuccessful($url);
            $html = (string) $client->getResponse()->getContent();
            $this->assertSelectorExists('.wizard-card .wizard-form .wizard-scroll');
            $this->assertStringContainsString('@media (max-width: 640px)', $html);
            $this->assertMatchesRegularExpression('/\.wizard-scroll \{ max-height: none !important; overflow: visible !important;/', $html);
            $this->assertMatchesRegularExpression('/\.checkbox-list-container \{ max-height: none !important; overflow: visible !important; \}/', $html);
        }
    }
}
