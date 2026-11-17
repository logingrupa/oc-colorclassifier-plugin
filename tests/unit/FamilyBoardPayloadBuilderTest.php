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
            [
                'family'                => 'Red',
                'slug'                  => 'red',
                'hex'                   => '#d32f2f',
                'active'                => true,
                'custom'                => false,
                'kind'                  => 'color',
                'fromFeed'              => false,
                'swatch'                => '#d32f2f',
                'feedOffersWithoutCard' => 0,
            ],
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
            'extraColors'      => [],
            'collections'      => [],
            'collectionStates' => [],
        ], $arCard);
    }

    private function makeCollectionRow(int $iId, string $sFamily, int $iSortOrder, bool $blActive = false): object
    {
        return (object) array_merge((array) $this->makeFamilyRow($iId, $sFamily, $iSortOrder, $blActive), [
            'kind'          => 'collection',
            'feed_value_id' => 'value-' . $iId,
            'gradient'      => ['#ff00aa', '#00e5ff'],
            'gradient_type' => 'radial',
            'gradient_angle' => 90,
        ]);
    }

    private function makeMembership(int $iFamilyId, string $sOfferId, bool $blInFeed, ?string $sHandState = null): array
    {
        return ['family_id' => $iFamilyId, 'offer_id' => $sOfferId, 'in_feed' => $blInFeed ? 1 : 0, 'hand_state' => $sHandState];
    }

    public function test_collection_rows_carry_kind_feed_flag_swatch_and_the_count_of_tagged_offers_without_a_card(): void
    {
        $arPayload = $this->obBuilder->build(
            [$this->makeEntry()],
            [$this->makeFamilyRow(1, 'Red', 1), $this->makeCollectionRow(2, 'DISCO', 2)],
            [
                $this->makeMembership(2, 'product-uuid#offer-uuid', true),
                $this->makeMembership(2, 'p#no-image-1', true),
                $this->makeMembership(2, 'p#no-image-2', true),
                $this->makeMembership(2, 'p#no-image-3', true, 'removed'),
                $this->makeMembership(2, 'p#hand-only', false, 'added'),
            ]
        );

        $this->assertSame(
            ['family' => 'DISCO', 'slug' => 'disco', 'hex' => '#d32f2f', 'active' => false, 'custom' => false, 'kind' => 'collection', 'fromFeed' => true, 'swatch' => 'radial-gradient(circle, #ff00aa, #00e5ff)', 'feedOffersWithoutCard' => 2],
            $arPayload['families'][1]
        );
        $this->assertSame(0, $arPayload['families'][0]['feedOffersWithoutCard']);
    }

    public function test_card_lists_extra_colors_and_collections_in_family_order_with_every_collection_state(): void
    {
        $arFamilies = [
            $this->makeFamilyRow(1, 'Red', 1),
            $this->makeFamilyRow(2, 'Pink', 2),
            $this->makeCollectionRow(3, 'NEON', 3),
            $this->makeCollectionRow(4, 'DISCO', 4, true),
            $this->makeCollectionRow(5, 'MATTE', 5),
        ];
        $arMemberships = [
            $this->makeMembership(4, 'product-uuid#offer-uuid', true),
            $this->makeMembership(3, 'product-uuid#offer-uuid', false, 'added'),
            $this->makeMembership(5, 'product-uuid#offer-uuid', true, 'removed'),
            $this->makeMembership(2, 'product-uuid#offer-uuid', false, 'added'),
            $this->makeMembership(1, 'product-uuid#offer-uuid', false, 'added'),
        ];

        $arCard = $this->obBuilder->build([$this->makeEntry()], $arFamilies, $arMemberships)['cards'][0];

        $this->assertSame(['Pink'], $arCard['extraColors'], 'the primary color is never an extra');
        $this->assertSame(['NEON', 'DISCO'], $arCard['collections']);
        $this->assertSame(['NEON' => 'added', 'DISCO' => 'feed', 'MATTE' => 'removed'], $arCard['collectionStates']);
    }

    public function test_map_cards_reads_memberships_for_the_given_entries_only(): void
    {
        $arCards = $this->obBuilder->mapCards(
            [$this->makeEntry(['id' => 8, 'offer_id' => 'p#o2'])],
            [$this->makeFamilyRow(1, 'Red', 1), $this->makeCollectionRow(2, 'DISCO', 2)],
            [$this->makeMembership(2, 'product-uuid#offer-uuid', true), $this->makeMembership(2, 'p#o2', true)]
        );

        $this->assertCount(1, $arCards);
        $this->assertSame(8, $arCards[0]['id']);
        $this->assertSame(['DISCO'], $arCards[0]['collections']);
    }

    public function test_a_membership_row_that_is_neither_in_the_feed_nor_hand_edited_is_not_listed(): void
    {
        $arCard = $this->obBuilder->build(
            [$this->makeEntry()],
            [$this->makeFamilyRow(1, 'Red', 1), $this->makeCollectionRow(2, 'DISCO', 2)],
            [$this->makeMembership(2, 'product-uuid#offer-uuid', false)]
        )['cards'][0];

        $this->assertSame([], $arCard['collections']);
        $this->assertSame([], $arCard['collectionStates']);
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
