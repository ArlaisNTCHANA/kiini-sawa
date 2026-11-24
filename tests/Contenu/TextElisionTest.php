<?php

namespace App\Tests\Contenu;

use App\Contenu\Twig\SiteTextExtension;
use PHPUnit\Framework\TestCase;

/** Insertion d'un prénom dans un texte : l'élision du français est respectée (« qu'Arlais », « d'Élodie », « de Marie »). */
class TextElisionTest extends TestCase
{
    public function testElisionBeforeAVowelOrH(): void
    {
        $text = 'Ce que {prenom} fait de mieux, le regard de {prenom}, chez {prenom}.';
        $this->assertSame('Ce qu\'Arlais fait de mieux, le regard d\'Arlais, chez Arlais.', SiteTextExtension::fill($text, ['prenom' => 'Arlais']));
        $this->assertSame('Ce qu\'Hélène fait de mieux, le regard d\'Hélène, chez Hélène.', SiteTextExtension::fill($text, ['prenom' => 'Hélène']));
        $this->assertSame('Ce que Marie fait de mieux, le regard de Marie, chez Marie.', SiteTextExtension::fill($text, ['prenom' => 'Marie']));
        $this->assertSame('Ce que Yannick fait de mieux, le regard de Yannick, chez Yannick.', SiteTextExtension::fill($text, ['prenom' => 'Yannick']));
        $this->assertSame('Qu\'Élodie vienne.', SiteTextExtension::fill('Que {prenom} vienne.', ['prenom' => 'Élodie']));
        $this->assertSame('Jusqu\'au 3 mai', SiteTextExtension::fill('Jusqu\'au {date}', ['date' => '3 mai']), 'Rien ne change hors de « que » ou « de » devant la variable');
    }
}
