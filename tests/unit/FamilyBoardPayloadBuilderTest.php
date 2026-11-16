<?php

use Logingrupa\ColorClassifier\Classes\EffectiveColorResolver;
use Logingrupa\ColorClassifier\Classes\FamilyBoardPayloadBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The board payload is built from effective values only: the page never
 * decides family, hex or confidence on its own.
 */
class FamilyBoardPayloadBuilderTest extends TestCase
{
    private FamilyBoardPayloadBuilder $obBuilder;

    protected function setUp(): void
    {
        $this->obBuilder = new FamilyBoardPayloadBuilder();
    }

    private function makeEntry(array $arOverrides = []): object
    {
        return (object) array_merge([
            'id'               => 7,
            'offer_id'         => 'product-uuid#offer-uuid',
            'product_name'     => 'Gel Polish Classic',
            'variation_name'   => '#07 Cherry',
            'color_name'       => 'Crimson',
            'image_url'        => 'https://example.test/cherry.jpg',
            'palette_colors'   => ['#9B1B30', '#B0102A'],
            'hex_color'        => '#9B1B30',
            'taxonomy'         => ['family' => 'Red'],
            'oklch_values'     => ['lightness' => 0.4, 'chroma' => 0.15, 'hue' => 20],
            'confidence_score' => '0.88',
            'family_override'  => null,
            'hex_override'     => null,
            'is_locked'        => false,
        ], $arOverrides);
    }

    private function makeFamilyRow(int $iId, string $sFamily, int $iSortOrder, bool $blActive = true): object
    {
        return (object) [
            'id'         => $iId,
            'family'     => $sFamily,
            'slug'       => strtolower(str_replace(' ', '-', $sFamily)),
            'hex'        => '#d32f2f',
            'sort_order' => $iSortOrder,
            'is_active'  => $blActive,
        ];
    }

    public function test_families_are_ordered_by_sort_order_then_id_with_the_custom_flag(): void
    {
        $arPayload = $this->obBuilder->build([], [
            $this->makeFamilyRow(3, 'Nude Pink', 2),
            $this->makeFamilyRow(2, 'Orange', 2),
            $this->makeFamilyRow(1, 'Red', 1),
            $this->makeFamilyRow(4, 'Retired', 9, false),
        ]);

        $this->assertSame(['Red', 'Orange', 'Nude Pink', 'Retired'], array_column($arPayload['families'], 'family'));
        $this->assertSame(
            ['family' => 'Red', 'slug' => 'red', 'hex' => '#d32f2f', 'active' => true, 'custom' => false],
            $arPayload['families'][0]
        );
        $this->assertTrue($arPayload['families'][2]['custom']);
        $this->assertFalse($arPayload['families'][3]['active']);
    }

    public function test_card_carries_classifier_values_when_nothing_is_overridden(): void
    {
        $arCard = $this->obBuilder->mapCard($this->makeEntry());

        $this->assertSame([
            'id'              => 7,
            'offerKey'        => 'offer-uuid',
            'productName'     => 'Gel Polish Classic',
            'variationName'   => '#07 Cherry',
            'colorName'       => 'Crimson',
            'imageUrl'        => 'https://example.test/cherry.jpg',
            'palette'         => ['#9B1B30', '#B0102A'],
            'family'          => 'Red',
            'autoFamily'      => 'Red',
            'familyOverridden' => false,
            'hex'             => '#9B1B30',
            'autoHex'         => '#9B1B30',
            'hexOverridden'   => false,
            'confidence'      => 0.88,
            'autoConfidence'  => 0.88,
            'manual'          => false,
            'locked'          => false,
        ], $arCard);
    }

    public function test_family_override_card_has_full_confidence_and_manual_flag(): void
    {
        $arCard = $this->obBuilder->mapCard($this->makeEntry(['family_override' => 'Rose', 'is_locked' => true]));

        $this->assertSame('Rose', $arCard['family']);
        $this->assertSame('Red', $arCard['autoFamily']);
        $this->assertTrue($arCard['familyOverridden']);
        $this->assertSame(1.0, $arCard['confidence']);
        $this->assertSame(0.88, $arCard['autoConfidence']);
        $this->assertTrue($arCard['manual']);
        $this->assertTrue($arCard['locked']);
    }

    public function test_hex_override_card_keeps_the_classifier_hex_beside_it(): void
    {
        $arCard = $this->obBuilder->mapCard($this->makeEntry(['hex_override' => '#112233']));

        $this->assertSame('#112233', $arCard['hex']);
        $this->assertSame('#9B1B30', $arCard['autoHex']);
        $this->assertTrue($arCard['hexOverridden']);
        $this->assertSame(0.88, $arCard['confidence']);
        $this->assertTrue($arCard['manual']);
    }

    public function test_card_without_palette_or_image_gets_safe_defaults(): void
    {
        $arCard = $this->obBuilder->mapCard($this->makeEntry(['palette_colors' => null, 'image_url' => null, 'color_name' => null]));

        $this->assertSame([], $arCard['palette']);
        $this->assertSame('', $arCard['imageUrl']);
        $this->assertSame('', $arCard['colorName']);
    }

    public function test_build_returns_one_card_per_entry_and_the_review_threshold(): void
    {
        $arPayload = $this->obBuilder->build(
            [$this->makeEntry(), $this->makeEntry(['id' => 8, 'offer_id' => 'p#o2'])],
            [$this->makeFamilyRow(1, 'Red', 1)]
        );

        $this->assertCount(2, $arPayload['cards']);
        $this->assertSame(EffectiveColorResolver::LOW_CONFIDENCE_THRESHOLD, $arPayload['lowConfidenceThreshold']);
        $this->assertSame($this->obBuilder->mapCard($this->makeEntry(['id' => 8, 'offer_id' => 'p#o2'])), $arPayload['cards'][1]);
    }
}
