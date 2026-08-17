<?php

use PHPUnit\Framework\TestCase;
use Logingrupa\ColorClassifier\Classes\FamilyExportBuilder;
use Logingrupa\ColorClassifier\Classes\Taxonomy;

/**
 * The families export is a contract for external stores: one entry per
 * taxonomy family, keyed by a stable slug, carrying localized names,
 * per-locale synonyms and a canonical hex. The version stamp must be
 * deterministic so ETag revalidation works.
 *
 * Payload key ORDER is part of the contract - consumers render family pills
 * in it: the hand-curated taxonomy order (owner decision 2026-08-15, after
 * rejecting confidence-derived family ordering).
 */
class FamilyExportBuilderTest extends TestCase
{
    /**
     * @return string[] Export slugs in taxonomy order.
     */
    private function taxonomyOrderSlugs(): array
    {
        return array_map(
            static fn (array $arMeta): string => $arMeta['slug'],
            array_values(Taxonomy::$familyMeta)
        );
    }

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

    public function test_export_order_is_the_curated_taxonomy_order(): void
    {
        $arSlugList = array_keys((new FamilyExportBuilder())->build()['families']);

        $this->assertSame(
            $this->taxonomyOrderSlugs(),
            $arSlugList,
            'consumers render pills in payload order - it must be the curated taxonomy order'
        );
    }

    /**
     * $familyMeta is hand-authored: a family with a missing slug must throw
     * naming the family, never be silently dropped from the export.
     * Taxonomy::$familyMeta is a public static, so the test mutates it and
     * restores it in finally.
     */
    public function test_missing_slug_throws_naming_the_family(): void
    {
        $arOriginalMeta = Taxonomy::$familyMeta;
        Taxonomy::$familyMeta['Red']['slug'] = '';

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('familyMeta["Red"]');
            (new FamilyExportBuilder())->build();
        } finally {
            Taxonomy::$familyMeta = $arOriginalMeta;
        }
    }

    /**
     * Two families sharing a slug must throw naming the second family.
     */
    public function test_duplicate_slug_throws_naming_the_family(): void
    {
        $arOriginalMeta = Taxonomy::$familyMeta;
        Taxonomy::$familyMeta['Pink']['slug'] = Taxonomy::$familyMeta['Red']['slug'];

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('familyMeta["Pink"] duplicates slug "red"');
            (new FamilyExportBuilder())->build();
        } finally {
            Taxonomy::$familyMeta = $arOriginalMeta;
        }
    }
}
