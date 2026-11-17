<?php

namespace App\Tests\Personnalite;

use App\Personnalite\Psychometrie\BigFiveProfiles;
use App\Personnalite\Psychometrie\EnneagramBank;
use App\Personnalite\Psychometrie\EnneagramProfiles;
use App\Personnalite\Psychometrie\GiftProfiles;
use App\Personnalite\Psychometrie\Interpretations;
use App\Personnalite\Psychometrie\ReportBuilder;
use App\Personnalite\Psychometrie\SpiritualGiftsBank;
use App\Personnalite\Psychometrie\TypeProfiles;
use PHPUnit\Framework\TestCase;

/**
 * Rapports illustrés : chaque type, chaque don et chaque dimension a un contenu complet.
 */
class IllustratedReportsTest extends TestCase
{
    public function testEveryTestHasAnIllustratedReport(): void
    {
        foreach (['disc', 'profil-complet', 'big-five', 'type', 'enneagramme', 'dons-spirituels'] as $slug) {
            $this->assertArrayHasKey($slug, ReportBuilder::TEMPLATES);
        }
    }

    public function testTypeProfilesAreComplete(): void
    {
        $this->assertSame(array_keys(Interpretations::TYPES), array_keys(TypeProfiles::TYPES));
        $fields = array_keys(TypeProfiles::TYPES['ISTJ']);
        foreach (TypeProfiles::TYPES as $code => $t) {
            $this->assertSame($fields, array_keys($t), $code);
            $this->assertArrayHasKey(TypeProfiles::familyOf($code), TypeProfiles::FAMILIES);
        }
    }

    public function testEnneagramProfilesAreComplete(): void
    {
        $fields = array_keys(EnneagramProfiles::TYPES[1]);
        foreach (array_keys(EnneagramBank::TYPES) as $t) {
            $this->assertSame($fields, array_keys(EnneagramProfiles::TYPES[$t]), "Type $t");
            $this->assertCount(2, EnneagramProfiles::WINGS[$t]);
            foreach (EnneagramBank::wingsOf($t) as $w) {
                $this->assertArrayHasKey($w, EnneagramProfiles::WINGS[$t], "Aile $w du type $t");
            }
        }
    }

    public function testGiftProfilesAreComplete(): void
    {
        $this->assertEqualsCanonicalizing(array_keys(SpiritualGiftsBank::GIFTS), array_keys(GiftProfiles::GIFTS));
        $all = array_merge(...array_column(GiftProfiles::FAMILIES, 'gifts'));
        $this->assertEqualsCanonicalizing(array_keys(SpiritualGiftsBank::GIFTS), $all, 'Chaque don dans une seule famille');
    }

    public function testBigFiveProfilesCoverEveryLevel(): void
    {
        foreach (BigFiveProfiles::DOMAINS as $d => $x) {
            foreach (['high', 'mid', 'low'] as $lvl) {
                $this->assertNotEmpty($x[$lvl]['label'], "$d $lvl");
                $this->assertCount(3, $x[$lvl]['strengths']);
            }
        }
    }
}
