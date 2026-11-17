<?php

use Logingrupa\ColorClassifier\Classes\FamilyExportBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The families export is a contract for external stores: one entry per
 * families-table row, keyed by a stable slug, carrying localized names,
 * per-locale synonyms, a canonical hex, the Families page order and the
 * active flag. The version stamp must be deterministic so ETag revalidation
 * works. Payload key ORDER is the pill order consumers render.
 */
class FamilyExportBuilderTest extends TestCase
{
    private const LOCALES = ['en', 'lv', 'ru', 'lt', 'nb-no'];

    /**
     * Rows shaped like ColorFamily models, built from the seed file.
     *
     * @return array<int, object>
     */
    private function makeSeedRows(): array
    {
        $arSeed = require dirname(__DIR__, 2) . '/updates/data/color_families_seed.php';
        $arRows = [];

        foreach ($arSeed as $iIndex => $arFamily) {
            $arRows[] = (object) [
                'id'         => $iIndex + 1,
                'family'     => $arFamily['family'],
                'slug'       => $arFamily['slug'],
                'hex'        => $arFamily['hex'],
                'names'      => $arFamily['names'],
                'synonyms'   => $arFamily['synonyms'],
                'sort_order' => $iIndex + 1,
                'is_active'  => true,
            ];
        }

        return $arRows;
    }

    public function test_every_row_is_exported_with_full_metadata(): void
    {
        $arPayload = (new FamilyExportBuilder())->build($this->makeSeedRows());

        $this->assertSame(23, $arPayload['count']);
        $this->assertCount(23, $arPayload['families']);

        foreach ($arPayload['families'] as $sSlug => $arFamily) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $sSlug);
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $arFamily['hex']);
            $this->assertIsInt($arFamily['sort_order']);
            $this->assertTrue($arFamily['active']);
            foreach (self::LOCALES as $sLocale) {
                $this->assertNotSame('', $arFamily['names'][$sLocale], $sSlug . ' ' . $sLocale);
                $this->assertArrayHasKey($sLocale, $arFamily['synonyms'], $sSlug . ' ' . $sLocale);
            }
        }

        $this->assertSame('Red', $arPayload['families']['red']['name']);
        $this->assertSame('Raudona', $arPayload['families']['red']['names']['lt']);
    }

    public function test_version_stamp_is_deterministic(): void
    {
        $obBuilder = new FamilyExportBuilder();
        $arRows = $this->makeSeedRows();

        $this->assertSame($obBuilder->build($arRows)['version'], $obBuilder->build($arRows)['version']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $obBuilder->build($arRows)['version']);
    }

    public function test_version_stamp_rotates_when_a_name_changes(): void
    {
        $obBuilder = new FamilyExportBuilder();
        $arRows = $this->makeSeedRows();
        $sBaseVersion = $obBuilder->build($arRows)['version'];

        $arRows[0]->names['lv'] = 'Sarkans!';

        $this->assertNotSame($sBaseVersion, $obBuilder->build($arRows)['version']);
    }

    public function test_shuffled_input_is_ordered_by_sort_order_then_id(): void
    {
        $arRows = $this->makeSeedRows();
        $arShuffled = array_reverse($arRows);

        $arSlugList = array_keys((new FamilyExportBuilder())->build($arShuffled)['families']);

        $this->assertSame(array_column($arRows, 'slug'), $arSlugList);
    }

    public function test_equal_sort_order_falls_back_to_id(): void
    {
        $arRows = $this->makeSeedRows();
        $arRows[0]->sort_order = 5;
        $arRows[0]->id = 900;
        $arRows[4]->sort_order = 5;
        $arRows[4]->id = 4;

        $arSlugList = array_keys((new FamilyExportBuilder())->build($arRows)['families']);
        $iYellowPosition = array_search('yellow', $arSlugList, true);
        $iRedPosition = array_search('red', $arSlugList, true);

        $this->assertLessThan($iRedPosition, $iYellowPosition);
    }

    public function test_inactive_rows_stay_in_the_export(): void
    {
        $arRows = $this->makeSeedRows();
        $arRows[1]->is_active = false;

        $arPayload = (new FamilyExportBuilder())->build($arRows);

        $this->assertSame(23, $arPayload['count']);
        $this->assertFalse($arPayload['families']['orange']['active']);
        $this->assertTrue($arPayload['families']['red']['active']);
    }

    public function test_seeded_colors_export_kind_color_and_no_gradient(): void
    {
        $arPayload = (new FamilyExportBuilder())->build($this->makeSeedRows());

        foreach ($arPayload['families'] as $sSlug => $arFamily) {
            $this->assertSame('color', $arFamily['kind'], $sSlug);
            $this->assertNull($arFamily['gradient'], $sSlug);
        }
    }

    /**
     * @return array<int, object>
     */
    private function makeRowsWithCollections(): array
    {
        $arRows = $this->makeSeedRows();
        $arRows[] = (object) [
            'id' => 24, 'family' => 'DISCO', 'slug' => 'disco', 'hex' => '#9e9e9e', 'names' => ['en' => 'DISCO'],
            'synonyms' => [], 'sort_order' => 3, 'is_active' => true, 'kind' => 'collection',
            'gradient' => ['#FF00AA', '#00e5ff', '#FFD400'], 'gradient_type' => 'conic', 'gradient_angle' => 20,
        ];
        $arRows[] = (object) [
            'id' => 25, 'family' => 'NEON', 'slug' => 'neon', 'hex' => '#9e9e9e', 'names' => ['en' => 'NEON'],
            'synonyms' => [], 'sort_order' => 4, 'is_active' => false, 'kind' => 'collection', 'gradient' => null,
            'gradient_type' => 'linear', 'gradient_angle' => 90,
        ];

        return $arRows;
    }

    public function test_active_collection_is_exported_in_order_with_kind_and_gradient(): void
    {
        $arPayload = (new FamilyExportBuilder())->build($this->makeRowsWithCollections());

        $this->assertSame('collection', $arPayload['families']['disco']['kind']);
        $this->assertSame(['type' => 'conic', 'angle' => 20, 'stops' => ['#ff00aa', '#00e5ff', '#ffd400']], $arPayload['families']['disco']['gradient']);
        $this->assertSame('#9e9e9e', $arPayload['families']['disco']['hex']);
        $arSlugList = array_keys($arPayload['families']);
        $sSlugWithSameSortOrder = $this->makeSeedRows()[2]->slug;
        $this->assertSame(array_search($sSlugWithSameSortOrder, $arSlugList, true) + 1, array_search('disco', $arSlugList, true));
    }

    public function test_inactive_collection_is_left_out_but_inactive_color_stays(): void
    {
        $arRows = $this->makeRowsWithCollections();
        $arRows[1]->is_active = false;

        $arPayload = (new FamilyExportBuilder())->build($arRows);

        $this->assertArrayNotHasKey('neon', $arPayload['families']);
        $this->assertArrayHasKey('orange', $arPayload['families']);
        $this->assertSame(24, $arPayload['count']);
    }

    public function test_bad_stored_gradient_throws(): void
    {
        $arRows = $this->makeRowsWithCollections();
        $arRows[23]->gradient = ['#ff00aa'];

        $this->expectException(\InvalidArgumentException::class);
        (new FamilyExportBuilder())->build($arRows);
    }

    public function test_bad_stored_gradient_type_throws(): void
    {
        $arRows = $this->makeRowsWithCollections();
        $arRows[23]->gradient_type = 'spiral';

        $this->expectException(\InvalidArgumentException::class);
        (new FamilyExportBuilder())->build($arRows);
    }

    public function test_custom_family_is_exported(): void
    {
        $arRows = $this->makeSeedRows();
        $arRows[] = (object) [
            'id'         => 24,
            'family'     => 'Nude Pink',
            'slug'       => 'nude-pink',
            'hex'        => '#e8b4b8',
            'names'      => ['en' => 'Nude Pink'],
            'synonyms'   => [],
            'sort_order' => 24,
            'is_active'  => true,
        ];

        $arPayload = (new FamilyExportBuilder())->build($arRows);

        $this->assertSame(24, $arPayload['count']);
        $this->assertSame('Nude Pink', $arPayload['families']['nude-pink']['name']);
        $this->assertSame('nude-pink', array_key_last($arPayload['families']));
    }

    public function test_array_rows_work_like_object_rows(): void
    {
        $arObjectRows = $this->makeSeedRows();
        $arArrayRows = array_map(static fn (object $obRow): array => (array) $obRow, $arObjectRows);

        $this->assertSame(
            (new FamilyExportBuilder())->build($arObjectRows),
            (new FamilyExportBuilder())->build($arArrayRows)
        );
    }

    public function test_missing_slug_throws_naming_the_family(): void
    {
        $arRows = $this->makeSeedRows();
        $arRows[0]->slug = '';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('"Red"');
        (new FamilyExportBuilder())->build($arRows);
    }

    public function test_missing_hex_throws_naming_the_family(): void
    {
        $arRows = $this->makeSeedRows();
        $arRows[1]->hex = '';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('"Orange"');
        (new FamilyExportBuilder())->build($arRows);
    }

    public function test_duplicate_slug_throws_naming_the_family_and_slug(): void
    {
        $arRows = $this->makeSeedRows();
        $arRows[15]->slug = 'red';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('"Pink" duplicates slug "red"');
        (new FamilyExportBuilder())->build($arRows);
    }
}
