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
 * in it: average ColorEntry confidence descending, unscored families last,
 * taxonomy order breaking ties and ordering the unscored tail.
 */
class FamilyExportBuilderTest extends TestCase
{
    /**
     * Build a ColorEntry-like row carrying only what the builder reads.
     */
    private function makeEntryRow(string $sFamilyName, $confidenceScore): object
    {
        return (object) [
            'taxonomy'         => ['family' => $sFamilyName],
            'confidence_score' => $confidenceScore,
        ];
    }

    /**
     * @return string[] Export slugs in taxonomy order (the no-data baseline).
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

    public function test_no_entries_exports_in_taxonomy_order(): void
    {
        $arSlugList = array_keys((new FamilyExportBuilder())->build()['families']);

        $this->assertSame($this->taxonomyOrderSlugs(), $arSlugList);
    }

    public function test_scored_families_lead_by_average_confidence_descending(): void
    {
        $arPayload = (new FamilyExportBuilder())->build([
            // Blue avg = 0.90, Red avg = (0.40 + 0.60) / 2 = 0.50
            $this->makeEntryRow('Blue', 0.90),
            $this->makeEntryRow('Red', 0.40),
            $this->makeEntryRow('Red', 0.60),
        ]);

        $arSlugList = array_keys($arPayload['families']);

        $this->assertSame(['blue', 'red'], array_slice($arSlugList, 0, 2), 'higher average confidence must come first');

        $arExpectedTail = array_values(array_diff($this->taxonomyOrderSlugs(), ['blue', 'red']));
        $this->assertSame($arExpectedTail, array_slice($arSlugList, 2), 'unscored families must sink in taxonomy order');
        $this->assertSame(count(Taxonomy::$colorFamilies), $arPayload['count'], 'ordering must never drop a family');
    }

    public function test_equal_averages_keep_taxonomy_order(): void
    {
        $arSlugList = array_keys((new FamilyExportBuilder())->build([
            $this->makeEntryRow('Pink', 0.70),
            $this->makeEntryRow('Red', 0.70),
        ])['families']);

        $this->assertSame(['red', 'pink'], array_slice($arSlugList, 0, 2), 'ties must fall back to taxonomy order');
    }

    public function test_malformed_rows_do_not_move_families(): void
    {
        $arSlugList = array_keys((new FamilyExportBuilder())->build([
            $this->makeEntryRow('Turbogreen', 0.99),
            $this->makeEntryRow('Red', 'not-a-number'),
            $this->makeEntryRow('Blue', null),
            (object) ['taxonomy' => 'broken', 'confidence_score' => 0.99],
        ])['families']);

        $this->assertSame($this->taxonomyOrderSlugs(), $arSlugList, 'rows that say nothing must change nothing');
    }

    public function test_decimal_cast_strings_count_as_confidence(): void
    {
        // Eloquent's decimal:2 cast hands the builder strings, not floats
        $arSlugList = array_keys((new FamilyExportBuilder())->build([
            $this->makeEntryRow('Black', '0.95'),
        ])['families']);

        $this->assertSame('black', $arSlugList[0]);
    }

    public function test_reordering_changes_the_version_stamp(): void
    {
        $obBuilder = new FamilyExportBuilder();

        $sBaselineVersion = $obBuilder->build()['version'];
        $sReorderedVersion = $obBuilder->build([$this->makeEntryRow('Black', 0.95)])['version'];

        $this->assertNotSame($sBaselineVersion, $sReorderedVersion, 'a new order must invalidate consumer ETags');
    }
}
