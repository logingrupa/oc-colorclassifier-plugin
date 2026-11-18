<?php

use Logingrupa\ColorClassifier\Classes\OfferLinkRefreshPlanner;
use PHPUnit\Framework\TestCase;

/**
 * REFRESH-01..04: the planner reports only the link fields of stored rows that
 * differ from the catalog offer with the same offer_id, and never a color.
 */
class OfferLinkRefreshPlannerTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private static function link(string $sSuffix): array
    {
        return [
            'product_name'   => 'Product ' . $sSuffix,
            'variation_name' => 'Shade ' . $sSuffix,
            'detail_url'     => '/products/detail/product-' . $sSuffix . '#v-shade-' . $sSuffix . '--sheet',
            'image_url'      => 'https://example.test/' . $sSuffix . '.png',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function catalogOffer(string $sOfferId, string $sSuffix): array
    {
        return ['offer_id' => $sOfferId, 'product_slug' => 'product-' . $sSuffix, 'variant_slug' => 'shade-' . $sSuffix]
            + self::link($sSuffix);
    }

    public function test_changed_fields_are_reported_and_equal_fields_are_not(): void
    {
        $arStored = ['p#a' => ['product_name' => 'Old name'] + self::link('a')];

        $arPlan = (new OfferLinkRefreshPlanner())->plan($arStored, [self::catalogOffer('p#a', 'a')]);

        $this->assertSame(['p#a' => ['product_name' => 'Product a']], $arPlan['changes']);
        $this->assertSame(0, $arPlan['unchanged']);
        $this->assertSame(0, $arPlan['absent']);
    }

    public function test_every_link_field_can_change(): void
    {
        $arStored = ['p#a' => self::link('old')];

        $arPlan = (new OfferLinkRefreshPlanner())->plan($arStored, [self::catalogOffer('p#a', 'a')]);

        $this->assertSame(self::link('a'), $arPlan['changes']['p#a']);
    }

    public function test_a_row_equal_to_its_catalog_offer_is_unchanged(): void
    {
        $arPlan = (new OfferLinkRefreshPlanner())->plan(['p#a' => self::link('a')], [self::catalogOffer('p#a', 'a')]);

        $this->assertSame([], $arPlan['changes']);
        $this->assertSame(1, $arPlan['unchanged']);
        $this->assertSame(0, $arPlan['absent']);
    }

    public function test_a_row_missing_from_the_catalog_is_absent_and_yields_no_change(): void
    {
        $arPlan = (new OfferLinkRefreshPlanner())->plan(['p#gone' => self::link('gone')], [self::catalogOffer('p#a', 'a')]);

        $this->assertSame([], $arPlan['changes']);
        $this->assertSame(0, $arPlan['unchanged']);
        $this->assertSame(1, $arPlan['absent']);
    }

    public function test_color_fields_never_appear_in_a_change(): void
    {
        $arStored = ['p#a' => ['hex_color' => '#ff0000', 'taxonomy' => 'Red', 'family_override' => 'Pink', 'product_name' => 'Old'] + self::link('a')];
        $arCatalogOffer = self::catalogOffer('p#a', 'a') + ['hex_color' => '#00ff00', 'taxonomy' => 'Green'];

        $arPlan = (new OfferLinkRefreshPlanner())->plan($arStored, [$arCatalogOffer]);

        $this->assertSame(['product_name'], array_keys($arPlan['changes']['p#a']));
    }
}
