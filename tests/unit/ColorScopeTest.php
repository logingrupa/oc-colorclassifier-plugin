<?php

use Logingrupa\ColorClassifier\Classes\ColorScope;
use PHPUnit\Framework\TestCase;

/**
 * SCOPE-01..07: an offer row is in the color scope when the 1C group path of its
 * product holds a scope group id; a row without a path is out of scope.
 */
class ColorScopeTest extends TestCase
{
    private const SCOPE_GROUP_ID = 'scope-group';

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
        return array_column(ColorScope::keepInScope($arRows, [self::SCOPE_GROUP_ID, 'other-scope-group']), 'offer_id');
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
            [self::SCOPE_GROUP_ID]
        );

        $this->assertSame([0, 1], array_keys($arKept));
        $this->assertSame(['first', 'second'], array_column($arKept, 'offer_id'));
    }

}
