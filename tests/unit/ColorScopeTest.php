<?php

use Logingrupa\ColorClassifier\Classes\ColorScope;
use PHPUnit\Framework\TestCase;

/**
 * SCOPE-01..10: an offer row is in the color scope when the 1C group path of its
 * product holds a scope group id or its product id is listed, and its product is
 * not excluded; a row without a path is out of scope unless its product is listed.
 */
class ColorScopeTest extends TestCase
{
    private const SCOPE_GROUP_ID = 'scope-group';

    private const SCOPE_PRODUCT_ID = 'listed-product';

    private const EXCLUDED_PRODUCT_ID = 'excluded-product';

    /**
     * @param array<int, string>|null $arPathIds Null leaves the category_path_ids key out.
     *
     * @return array<string, mixed>
     */
    private static function row(string $sOfferId, ?array $arPathIds): array
    {
        $arRow = ['offer_id' => $sOfferId, 'image_url' => 'https://example.test/' . $sOfferId . '.png'];

        return $arPathIds === null ? $arRow : $arRow + ['category_path_ids' => $arPathIds];
    }

    /**
     * @param array<int, array<string, mixed>> $arRows
     *
     * @return array<int, string>
     */
    private static function keptOfferIds(array $arRows): array
    {
        return array_column(ColorScope::keepInScope($arRows, [self::SCOPE_GROUP_ID, 'other-scope-group'], [self::SCOPE_PRODUCT_ID], [self::EXCLUDED_PRODUCT_ID]), 'offer_id');
    }

    public function test_scope01_a_row_in_a_scope_group_is_kept(): void
    {
        $this->assertSame(['a'], self::keptOfferIds([self::row('a', [self::SCOPE_GROUP_ID])]));
    }

    public function test_scope02_a_row_in_a_subgroup_at_any_depth_is_kept(): void
    {
        $this->assertSame(
            ['child', 'grandchild'],
            self::keptOfferIds([
                self::row('child', [self::SCOPE_GROUP_ID, 'child-group']),
                self::row('grandchild', ['other-scope-group', 'child-group', 'grandchild-group']),
            ])
        );
    }

    public function test_scope03_a_row_without_a_scope_id_is_dropped(): void
    {
        $this->assertSame([], self::keptOfferIds([self::row('a', ['tools', 'files'])]));
    }

    public function test_scope04_a_row_without_the_path_key_is_dropped_without_an_exception(): void
    {
        $this->assertSame([], self::keptOfferIds([self::row('a', null)]));
    }

    public function test_scope05_a_row_with_an_empty_path_is_dropped(): void
    {
        $this->assertSame([], self::keptOfferIds([self::row('a', [])]));
    }

    public function test_scope06_kept_rows_keep_catalog_order_and_come_back_as_a_list(): void
    {
        $arKept = ColorScope::keepInScope(
            [
                self::row('first', [self::SCOPE_GROUP_ID]),
                self::row('dropped', ['tools']),
                self::row('second', [self::SCOPE_GROUP_ID]),
            ],
            [self::SCOPE_GROUP_ID],
            [],
            []
        );

        $this->assertSame([0, 1], array_keys($arKept));
        $this->assertSame(['first', 'second'], array_column($arKept, 'offer_id'));
    }

    public function test_scope07_an_offer_of_a_listed_product_is_kept_in_any_group(): void
    {
        $this->assertSame(
            [self::SCOPE_PRODUCT_ID . '#red', self::SCOPE_PRODUCT_ID . '#blue'],
            self::keptOfferIds([
                self::row(self::SCOPE_PRODUCT_ID . '#red', ['outlet']),
                self::row(self::SCOPE_PRODUCT_ID . '#blue', null),
            ])
        );
    }

    public function test_scope08_a_listed_product_id_matches_only_the_part_before_the_hash(): void
    {
        $this->assertSame(
            [self::SCOPE_PRODUCT_ID],
            self::keptOfferIds([
                self::row(self::SCOPE_PRODUCT_ID, ['outlet']),
                self::row('other-product#' . self::SCOPE_PRODUCT_ID, ['outlet']),
            ])
        );
    }

    public function test_scope09_an_excluded_product_is_dropped_inside_a_scope_group(): void
    {
        $this->assertSame(
            ['kept#1'],
            self::keptOfferIds([
                self::row(self::EXCLUDED_PRODUCT_ID . '#clear', [self::SCOPE_GROUP_ID, 'tops']),
                self::row('kept#1', [self::SCOPE_GROUP_ID, 'tops']),
            ])
        );
    }

    public function test_scope10_exclusion_wins_over_the_product_list(): void
    {
        $arKept = ColorScope::keepInScope(
            [self::row(self::SCOPE_PRODUCT_ID . '#1', ['outlet'])],
            [self::SCOPE_GROUP_ID],
            [self::SCOPE_PRODUCT_ID],
            [self::SCOPE_PRODUCT_ID]
        );

        $this->assertSame([], $arKept);
    }
}
