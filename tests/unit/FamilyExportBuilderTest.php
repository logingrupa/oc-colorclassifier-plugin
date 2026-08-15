<?php

use PHPUnit\Framework\TestCase;
use Logingrupa\ColorClassifier\Classes\FamilyExportBuilder;
use Logingrupa\ColorClassifier\Classes\Taxonomy;

/**
 * The families export is a contract for external stores: one entry per
 * taxonomy family, keyed by a stable slug, carrying localized names,
 * per-locale synonyms and a canonical hex. The version stamp must be
 * deterministic so ETag revalidation works.
 */
class FamilyExportBuilderTest extends TestCase
{
    public function test_every_taxonomy_family_is_exported_with_full_metadata(): void
    {
        $arPayload = (new FamilyExportBuilder())->build();

        $this->assertSame(count(Taxonomy::$colorFamilies), $arPayload['count']);
        $this->assertCount($arPayload['count'], $arPayload['families']);

        foreach ($arPayload['families'] as $sSlug => $arFamily) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $sSlug, 'slug must be URL-stable');
            $this->assertContains($arFamily['name'], Taxonomy::$colorFamilies, 'name must be an offers.json family');
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $arFamily['hex']);
            foreach (['en', 'lv', 'ru'] as $sLocale) {
                $this->assertArrayHasKey($sLocale, $arFamily['names'], $sSlug . ' missing ' . $sLocale . ' name');
                $this->assertNotSame('', $arFamily['names'][$sLocale]);
                $this->assertArrayHasKey($sLocale, $arFamily['synonyms'], $sSlug . ' missing ' . $sLocale . ' synonym list');
            }
        }
    }

    public function test_meta_covers_exactly_the_taxonomy_family_list(): void
    {
        $this->assertSame(
            Taxonomy::$colorFamilies,
            array_keys(Taxonomy::$familyMeta),
            'familyMeta must stay in lockstep with $colorFamilies - offers.json families join on these names'
        );
    }

    public function test_version_stamp_is_deterministic(): void
    {
        $obBuilder = new FamilyExportBuilder();

        $this->assertSame($obBuilder->build()['version'], $obBuilder->build()['version']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $obBuilder->build()['version']);
    }

    public function test_slugs_are_unique(): void
    {
        $arSlugList = array_keys((new FamilyExportBuilder())->build()['families']);

        $this->assertSame($arSlugList, array_unique($arSlugList));
    }
}
