<?php

use Logingrupa\ColorClassifier\Classes\Taxonomy;
use PHPUnit\Framework\TestCase;

/**
 * The seed file feeds the color_families table and the families.json export,
 * so its shape is a contract: stable slugs, valid hex, five locales.
 */
class ColorFamilySeedDataTest extends TestCase
{
    private const LOCALES = ['en', 'lv', 'ru', 'lt', 'nb-no'];

    /** @return array<int, array<string, mixed>> */
    private function loadSeed(): array
    {
        return require dirname(__DIR__, 2) . '/updates/data/color_families_seed.php';
    }

    /**
     * @return array<string, mixed>
     */
    private function findSeedFamily(string $sFamily): array
    {
        foreach ($this->loadSeed() as $arFamily) {
            if ($arFamily['family'] === $sFamily) {
                return $arFamily;
            }
        }

        $this->fail('Seed has no family ' . $sFamily);
    }

    public function test_seed_has_the_23_classifier_families_in_order(): void
    {
        $arSeedFamilies = array_column($this->loadSeed(), 'family');

        $this->assertCount(23, $arSeedFamilies);
        $this->assertSame(Taxonomy::$colorFamilies, $arSeedFamilies);
        $this->assertSame('Red', $arSeedFamilies[0]);
        $this->assertSame('Black', $arSeedFamilies[22]);
    }

    public function test_slugs_are_unique_and_url_stable(): void
    {
        $arSlugs = array_column($this->loadSeed(), 'slug');

        $this->assertSame($arSlugs, array_unique($arSlugs));
        foreach ($arSlugs as $sSlug) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $sSlug);
        }
    }

    public function test_hex_values_are_lowercase_rrggbb(): void
    {
        foreach ($this->loadSeed() as $arFamily) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $arFamily['hex'], $arFamily['family']);
        }
    }

    public function test_every_family_names_all_five_locales(): void
    {
        foreach ($this->loadSeed() as $arFamily) {
            foreach (self::LOCALES as $sLocale) {
                $this->assertArrayHasKey($sLocale, $arFamily['names'], $arFamily['family']);
                $this->assertNotSame('', trim($arFamily['names'][$sLocale]), $arFamily['family'] . ' ' . $sLocale);
                $this->assertIsArray($arFamily['synonyms'][$sLocale] ?? null, $arFamily['family'] . ' ' . $sLocale);
                foreach ($arFamily['synonyms'][$sLocale] as $sSynonym) {
                    $this->assertIsString($sSynonym);
                    $this->assertNotSame('', trim($sSynonym));
                }
            }
        }
    }

    public function test_new_locale_names_match_the_owner_table(): void
    {
        $arRed = $this->findSeedFamily('Red');
        $this->assertSame('Raudona', $arRed['names']['lt']);
        $this->assertSame('Rød', $arRed['names']['nb-no']);

        $arRose = $this->findSeedFamily('Rose');
        $this->assertSame('Pudrinė', $arRose['names']['lt']);
        $this->assertSame('Støvrosa', $arRose['names']['nb-no']);

        $this->assertSame(['melsvai žali'], $this->findSeedFamily('Teal')['synonyms']['lt']);
        $this->assertSame(['nude', 'kūno spalvos'], $this->findSeedFamily('Nude')['synonyms']['lt']);
    }
}
