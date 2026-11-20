<?php

use Logingrupa\ColorClassifier\Classes\ColorScope;
use Logingrupa\ColorClassifier\Classes\OfferDataProvider;
use Logingrupa\ColorClassifier\Classes\OfferRowBuilder;
use PHPUnit\Framework\TestCase;

/**
 * ROWS-01..05: a row exists for every full-catalog offer with an image. An
 * offer the shop lists takes the shop's names and a shop detail URL; any other
 * offer keeps its 1C names and has no detail URL.
 */
class OfferRowBuilderTest extends TestCase
{
    private const SCOPE_GROUP_ID = 'grp-scope';

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fullCatalog(): array
    {
        return [
            [
                'name'   => 'NAI_S Magnetic 1C name',
                'slug'   => 'nai-s-magnetic-1c-name',
                'offers' => [
                    ['id' => 'm#a', 'name' => '1C offer A', 'variant_title' => '1C shade A', 'image_url' => 'https://example.test/a.png'],
                    ['id' => 'm#b', 'name' => '1C offer B', 'variant_title' => '1C shade B', 'image_url' => ''],
                ],
            ],
            [
                'name'              => 'Color Gel',
                'slug'              => 'color-gel',
                'category_path_ids' => [self::SCOPE_GROUP_ID, 'grp-gel'],
                'offers'            => [
                    ['id' => 'c#a', 'name' => 'Color Gel (Red)', 'variant_title' => 'Red 1', 'image_url' => 'https://example.test/c.png'],
                    ['id' => 'c#b', 'name' => 'Color Gel (Blue)', 'image_url' => 'https://example.test/d.png'],
                ],
            ],
            [
                'name'              => 'Metal File',
                'slug'              => 'metal-file',
                'category_path_ids' => ['grp-tools', 'grp-files'],
                'offers'            => [
                    ['id' => 'f#a', 'name' => 'Metal File 180', 'variant_title' => '180', 'image_url' => 'https://example.test/f.png'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function shopDirectory(): array
    {
        return [
            'm#a' => [
                'product_name'  => 'Magnetic Gel Polish',
                'product_slug'  => 'magnetic-gel-polish',
                'offer_name'    => 'Magnetic Gel Polish, 12ml (A)',
                'variant_title' => 'DISCO 49',
            ],
        ];
    }

    public function test_listed_offer_takes_shop_names_and_shop_detail_url(): void
    {
        $arRows = (new OfferRowBuilder())->build(self::fullCatalog(), self::shopDirectory());

        $this->assertSame(
            [
                'offer_id'       => 'm#a',
                'image_url'      => 'https://example.test/a.png',
                'product_name'   => 'Magnetic Gel Polish',
                'variation_name' => 'DISCO 49',
                'detail_url'     => '/products/detail/magnetic-gel-polish#v-disco-49--sheet',
                'category_path_ids' => [],
            ],
            $arRows[0]
        );
    }

    public function test_unlisted_offer_keeps_1c_names_and_has_no_detail_url(): void
    {
        $arRows = (new OfferRowBuilder())->build(self::fullCatalog(), self::shopDirectory());

        $this->assertSame(
            [
                'offer_id'       => 'c#a',
                'image_url'      => 'https://example.test/c.png',
                'product_name'   => 'Color Gel',
                'variation_name' => 'Red 1',
                'detail_url'     => '',
                'category_path_ids' => [self::SCOPE_GROUP_ID, 'grp-gel'],
            ],
            $arRows[1]
        );
    }

    public function test_variation_name_falls_back_to_offer_name_without_variant_title(): void
    {
        $arRows = (new OfferRowBuilder())->build(self::fullCatalog(), self::shopDirectory());

        $this->assertSame('Color Gel (Blue)', $arRows[2]['variation_name']);
    }

    public function test_offer_without_image_gets_no_row(): void
    {
        $arRows = (new OfferRowBuilder())->build(self::fullCatalog(), self::shopDirectory());

        $this->assertSame(['m#a', 'c#a', 'c#b', 'f#a'], array_column($arRows, 'offer_id'));
    }

    public function test_rows_carry_the_group_path_of_their_product_and_an_empty_path_without_one(): void
    {
        $arRows = (new OfferRowBuilder())->build(self::fullCatalog(), []);

        $this->assertSame(
            [[], [self::SCOPE_GROUP_ID, 'grp-gel'], [self::SCOPE_GROUP_ID, 'grp-gel'], ['grp-tools', 'grp-files']],
            array_column($arRows, 'category_path_ids')
        );
    }

    public function test_color_scope_keeps_the_in_scope_product_and_drops_the_other_two(): void
    {
        $arRows = (new OfferRowBuilder())->build(self::fullCatalog(), []);

        $this->assertSame(
            ['c#a', 'c#b'],
            array_column(ColorScope::keepInScope($arRows, [self::SCOPE_GROUP_ID], [], []), 'offer_id')
        );
    }

    public function test_no_unlisted_row_points_at_a_shop_page(): void
    {
        $arRows = (new OfferRowBuilder())->build(self::fullCatalog(), []);

        foreach ($arRows as $arRow) {
            $this->assertSame('', $arRow['detail_url']);
        }
    }

    /**
     * @param array<int, array<string, mixed>>                  $arFullCatalog
     * @param array<string, array<string, string>>              $arShopDirectory
     */
    private static function makeProvider(array $arFullCatalog, array $arShopDirectory): OfferDataProvider
    {
        return new class ($arFullCatalog, $arShopDirectory) extends OfferDataProvider {
            public function __construct(private array $arFullCatalog, private array $arShopDirectory)
            {
            }

            protected function loadFullCatalog(): array
            {
                return $this->arFullCatalog;
            }

            public function getOfferDirectory(): array
            {
                return $this->arShopDirectory;
            }
        };
    }

    public function test_provider_builds_rows_from_the_full_catalog_while_the_shop_directory_is_unreadable(): void
    {
        $arRows = self::makeProvider(self::fullCatalog(), [])->getOffersWithImages();

        $this->assertSame(['m#a', 'c#a', 'c#b', 'f#a'], array_column($arRows, 'offer_id'));
        $this->assertSame('NAI_S Magnetic 1C name', $arRows[0]['product_name']);
        $this->assertSame('', $arRows[0]['detail_url']);
    }

    public function test_provider_returns_in_scope_and_out_of_scope_offers_alike(): void
    {
        $arRows = self::makeProvider(self::fullCatalog(), self::shopDirectory())->getOffersWithImages();

        $this->assertSame(
            ['m#a', 'c#a', 'c#b', 'f#a'],
            array_column($arRows, 'offer_id'),
            'refresh-offer-links and the collection sync read the full set'
        );
    }

    public function test_provider_gives_no_rows_when_the_full_catalog_is_unreadable(): void
    {
        $this->assertSame([], self::makeProvider([], self::shopDirectory())->getOffersWithImages());
    }
}
