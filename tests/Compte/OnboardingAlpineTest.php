<?php

namespace App\Tests\Compte;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La page d'onboarding passe les réponses à Alpine via |json_safe. Les guillemets doubles qui encadrent chaque texte JSON
 * ne doivent pas fermer l'attribut x-data (sinon Alpine affiche son code brut à la place du formulaire).
 */
class OnboardingAlpineTest extends WebTestCase
{
    use AccountTestTrait;

    public function testAlpineAttributesStayWholeWithQuotesInTheFirstName(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/inscription');
        $form = $crawler->selectButton('Créer mon compte')->form();
        $form['acceptTerms']->tick();
        $client->submit($form, ['firstName' => 'Marie "Jo" d\'Arc', 'email' => $this->uniqueEmail('onboarding-alpine'), 'password' => 'une phrase assez longue']);
        $code = $this->codeFrom($this->lastEmail());
        $crawler = $client->request('GET', '/verification-email');
        $client->submit($crawler->selectButton('Valider')->form(), ['code' => $code]);

        $crawler = $client->request('GET', '/onboarding');
        $this->assertResponseIsSuccessful();

        // L'attribut x-data contient tout l'objet (de « { » à « } ») et toutes les propriétés utilisées par le formulaire
        $xData = trim((string) $crawler->filter('form.ob-card')->attr('x-data'));
        $this->assertStringStartsWith('{', $xData);
        $this->assertStringEndsWith('}', $xData);
        foreach (['step:', 'firstName:', 'purpose:', 'values:', 'goalTitle:', 'addValue(v)', 'valid()'] as $property) {
            $this->assertStringContainsString($property, $xData);
        }
        // Les guillemets et l'apostrophe du prénom sont écrits en séquences \uXXXX
        $this->assertStringContainsString('firstName: "Marie \\u0022Jo\\u0022 d\\u0027Arc"', $xData);

        // Les boutons de suggestions de valeurs gardent leur appel complet
        // (le lecteur HTML de DomCrawler ignore les attributs « @… » : on contrôle le HTML envoyé)
        $this->assertStringContainsString("@click='addValue(\"Foi\")'", (string) $client->getResponse()->getContent());
        $this->assertStringNotContainsString('@click="addValue(', (string) $client->getResponse()->getContent());
    }
}
