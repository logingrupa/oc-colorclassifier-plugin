<?php

use PHPUnit\Framework\TestCase;
use Logingrupa\ColorClassifier\Classes\SheetExportBuilder;

/**
 * Unit tests for SheetExportBuilder.
 *
 * Covers the compact offer color export requirements:
 *   - Happy path: valid rows export family / hex / hue / lightness /
 *     confidence keyed by offer_id (confidence null when missing or malformed)
 *   - Rows without a non-empty hex_color are skipped
 *   - Rows with malformed taxonomy (not an array, or unresolved family) are skipped
 *   - Rows with malformed oklch_values are skipped
 *   - Count reflects exported rows only
 *   - Version stamp is deterministic 40-hex, stable when nothing changes, and
 *     rotates when any exported value, the payload shape, or updated_at changes
 *   - last_updated_at is one global value: the latest updated_at among exported rows
 *   - Offers map key is the offer UUID after the last "#" of offer_id
 *   - Keys without "#" are exported unchanged
 *   - Rows resolving to an empty key are skipped
 *   - On key collision the first row wins, later rows are skipped
 */
class SheetExportBuilderTest extends TestCase
{
    /** @var SheetExportBuilder */
    private $exportBuilder;

    protected function setUp(): void
    {
        $this->exportBuilder = new SheetExportBuilder();
    }

    /**
     * Build a valid ColorEntry-like row with optional overrides.
     */
    private function makeColorEntryRow(array $overrides = []): object
    {
        return (object) array_merge([
            'offer_id'     => 'offer-1',
            'hex_color'    => '#EC407A',
            'taxonomy'     => [
                'family'           => 'Pink',
                'depth'            => 'Medium',
                'undertone'        => 'Cool',
                'secondary_family' => 'Red',
            ],
            'oklch_values' => [
                'hue'       => 354.2,
                'chroma'    => 0.15,
                'lightness' => 0.64,
            ],
            'confidence_score' => '0.85',
            'updated_at'   => '2026-03-27 08:56:23',
        ], $overrides);
    }

    /**
     * Valid rows should export the four compact fields keyed by offer_id.
     */
    public function test_happy_path_exports_compact_offer_map(): void
    {
        $payload = $this->exportBuilder->build([
            $this->makeColorEntryRow(),
            $this->makeColorEntryRow([
                'offer_id'     => 'offer-2',
                'hex_color'    => '#8D6E63',
                'taxonomy'     => ['family' => 'Nude'],
                'oklch_values' => ['hue' => 31.88, 'lightness' => 0.8168],
            ]),
        ]);

        $this->assertSame(2, $payload['count']);
        $this->assertSame(['offer-1', 'offer-2'], array_keys($payload['offers']));
        $this->assertSame(
            [
                'family'     => 'Pink',
                'hex'        => '#EC407A',
                'hue'        => 354.2,
                'lightness'  => 0.64,
                // decimal:2 cast strings become floats on export
                'confidence' => 0.85,
            ],
            $payload['offers']['offer-1']
        );
        $this->assertSame('Nude', $payload['offers']['offer-2']['family']);
    }

    /**
     * A missing or malformed confidence exports as null - the color data is
     * still valid, only the in-family ordering signal is absent.
     */
    public function test_missing_or_malformed_confidence_exports_as_null(): void
    {
        $payload = $this->exportBuilder->build([
            $this->makeColorEntryRow(['offer_id' => 'offer-none', 'confidence_score' => null]),
            $this->makeColorEntryRow(['offer_id' => 'offer-junk', 'confidence_score' => 'high']),
        ]);

        $this->assertSame(2, $payload['count'], 'a bad confidence must not skip the row');
        $this->assertNull($payload['offers']['offer-none']['confidence']);
        $this->assertNull($payload['offers']['offer-junk']['confidence']);
    }

    /**
     * Rows with an empty hex_color should be skipped and not counted.
     */
    public function test_row_skipped_when_hex_color_missing(): void
    {
        $payload = $this->exportBuilder->build([
            $this->makeColorEntryRow(['offer_id' => 'offer-no-hex', 'hex_color' => '']),
            $this->makeColorEntryRow(['offer_id' => 'offer-null-hex', 'hex_color' => null]),
            $this->makeColorEntryRow(['offer_id' => 'offer-valid']),
        ]);

        $this->assertSame(1, $payload['count']);
        $this->assertSame(['offer-valid'], array_keys($payload['offers']));
    }

    /**
     * Rows with malformed taxonomy should be skipped and not counted.
     */
    public function test_row_skipped_when_taxonomy_malformed(): void
    {
        $payload = $this->exportBuilder->build([
            $this->makeColorEntryRow(['offer_id' => 'offer-null-taxonomy', 'taxonomy' => null]),
            $this->makeColorEntryRow(['offer_id' => 'offer-string-taxonomy', 'taxonomy' => 'Pink']),
            $this->makeColorEntryRow(['offer_id' => 'offer-no-family', 'taxonomy' => ['depth' => 'Medium']]),
            $this->makeColorEntryRow(['offer_id' => 'offer-empty-family', 'taxonomy' => ['family' => '']]),
            $this->makeColorEntryRow(['offer_id' => 'offer-valid']),
        ]);

        $this->assertSame(1, $payload['count']);
        $this->assertSame(['offer-valid'], array_keys($payload['offers']));
    }

    /**
     * Rows with malformed oklch_values should be skipped and not counted.
     */
    public function test_row_skipped_when_oklch_values_malformed(): void
    {
        $payload = $this->exportBuilder->build([
            $this->makeColorEntryRow(['offer_id' => 'offer-null-oklch', 'oklch_values' => null]),
            $this->makeColorEntryRow(['offer_id' => 'offer-no-hue', 'oklch_values' => ['lightness' => 0.64]]),
            $this->makeColorEntryRow(['offer_id' => 'offer-string-hue', 'oklch_values' => ['hue' => 'red', 'lightness' => 0.64]]),
            $this->makeColorEntryRow(['offer_id' => 'offer-valid']),
        ]);

        $this->assertSame(1, $payload['count']);
        $this->assertSame(['offer-valid'], array_keys($payload['offers']));
    }

    /**
     * A composite offer_id should export under the part after the last "#".
     */
    public function test_composite_offer_id_exports_under_offer_uuid_suffix(): void
    {
        $payload = $this->exportBuilder->build([
            $this->makeColorEntryRow(['offer_id' => 'aaa#bbb']),
        ]);

        $this->assertSame(1, $payload['count']);
        $this->assertSame(['bbb'], array_keys($payload['offers']));
    }

    /**
     * An offer_id without "#" should export under the unchanged key.
     */
    public function test_bare_offer_id_exports_unchanged(): void
    {
        $payload = $this->exportBuilder->build([
            $this->makeColorEntryRow(['offer_id' => 'ccc']),
        ]);

        $this->assertSame(1, $payload['count']);
        $this->assertSame(['ccc'], array_keys($payload['offers']));
    }

    /**
     * An offer_id ending in "#" resolves to an empty key and is skipped.
     */
    public function test_row_skipped_when_offer_key_resolves_empty(): void
    {
        $payload = $this->exportBuilder->build([
            $this->makeColorEntryRow(['offer_id' => 'aaa#']),
            $this->makeColorEntryRow(['offer_id' => 'ddd#eee']),
        ]);

        $this->assertSame(1, $payload['count']);
        $this->assertSame(['eee'], array_keys($payload['offers']));
    }

    /**
     * On key collision the first row wins and later rows are skipped.
     */
    public function test_first_row_wins_on_offer_key_collision(): void
    {
        $payload = $this->exportBuilder->build([
            $this->makeColorEntryRow(['offer_id' => 'aaa#bbb', 'hex_color' => '#111111']),
            $this->makeColorEntryRow(['offer_id' => 'zzz#bbb', 'hex_color' => '#222222']),
        ]);

        $this->assertSame(1, $payload['count']);
        $this->assertSame(['bbb'], array_keys($payload['offers']));
        $this->assertSame('#111111', $payload['offers']['bbb']['hex']);
    }

    /**
     * Same input should always produce the same version stamp.
     */
    public function test_version_stamp_is_deterministic(): void
    {
        $rows = [
            $this->makeColorEntryRow(),
            $this->makeColorEntryRow(['offer_id' => 'offer-2', 'updated_at' => '2026-03-28 10:00:00']),
        ];

        $firstPayload  = $this->exportBuilder->build($rows);
        $secondPayload = $this->exportBuilder->build($rows);

        $this->assertSame($firstPayload['version'], $secondPayload['version']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $firstPayload['version']);
    }

    /**
     * The stamp hashes the exported rows themselves: a payload value change
     * with the SAME count and SAME updated_at must still rotate it (the
     * pre-1.5.2 updated_at|count stamp missed exactly this case and let
     * consumers 304 forever on a stale body).
     */
    public function test_version_stamp_rotates_when_row_payload_changes(): void
    {
        $basePayload = $this->exportBuilder->build([
            $this->makeColorEntryRow(),
        ]);

        $changedHexPayload = $this->exportBuilder->build([
            $this->makeColorEntryRow(['hex_color' => '#123456']),
        ]);

        $changedConfidencePayload = $this->exportBuilder->build([
            $this->makeColorEntryRow(['confidence_score' => '0.42']),
        ]);

        $this->assertNotSame($basePayload['version'], $changedHexPayload['version']);
        $this->assertNotSame($basePayload['version'], $changedConfidencePayload['version']);
    }

    /**
     * Version stamp should change when updated_at or exported count changes.
     */
    public function test_version_stamp_changes_when_data_set_changes(): void
    {
        $basePayload = $this->exportBuilder->build([
            $this->makeColorEntryRow(),
        ]);

        $newerUpdatedAtPayload = $this->exportBuilder->build([
            $this->makeColorEntryRow(['updated_at' => '2026-03-28 10:00:00']),
        ]);

        $moreRowsPayload = $this->exportBuilder->build([
            $this->makeColorEntryRow(),
            $this->makeColorEntryRow(['offer_id' => 'offer-2']),
        ]);

        $this->assertNotSame($basePayload['version'], $newerUpdatedAtPayload['version']);
        $this->assertNotSame($basePayload['version'], $moreRowsPayload['version']);
    }

    /**
     * last_updated_at should be the latest updated_at among exported rows.
     */
    public function test_last_updated_at_is_latest_among_exported_rows(): void
    {
        $payload = $this->exportBuilder->build([
            $this->makeColorEntryRow(['updated_at' => '2026-03-27 08:56:23']),
            $this->makeColorEntryRow(['offer_id' => 'offer-2', 'updated_at' => '2026-03-28 10:00:00']),
            $this->makeColorEntryRow(['offer_id' => 'offer-3', 'updated_at' => '2026-03-26 12:00:00']),
        ]);

        $this->assertSame('2026-03-28 10:00:00', $payload['last_updated_at']);
    }

    /**
     * Skipped rows should not influence last_updated_at.
     */
    public function test_last_updated_at_ignores_skipped_rows(): void
    {
        $payload = $this->exportBuilder->build([
            $this->makeColorEntryRow(['updated_at' => '2026-03-27 08:56:23']),
            $this->makeColorEntryRow([
                'offer_id'   => 'offer-skipped',
                'hex_color'  => '',
                'updated_at' => '2026-12-31 23:59:59',
            ]),
        ]);

        $this->assertSame('2026-03-27 08:56:23', $payload['last_updated_at']);
    }
}
