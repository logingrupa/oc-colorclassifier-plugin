<?php

use Logingrupa\ColorClassifier\Classes\CollectionTagExtractor;
use PHPUnit\Framework\TestCase;

/**
 * TAG-01: the extractor turns a parsed catalog into the planner's tag input.
 */
class CollectionTagExtractorTest extends TestCase
{
    private const DISCO = ['value_id' => 'v-disco', 'value' => 'DISCO'];

    /**
     * @return array<int, array<string, mixed>>
     */
    private function makeCatalog(): array
    {
        return [
            [
                'offers' => [
                    ['id' => 'p1#a', 'image_url' => 'a.jpg', 'collections' => [self::DISCO], 'shade_code' => 'DISCO 30'],
                    ['id' => 'p1#b', 'image_url' => '', 'collections' => [self::DISCO], 'shade_code' => 'DISCO 31'],
                    ['id' => 'p1#c', 'image_url' => 'c.jpg', 'collections' => [], 'shade_code' => null],
                ],
            ],
            ['offers' => []],
        ];
    }

    public function test_offers_are_keyed_by_full_id_and_image_less_offers_stay(): void
    {
        $arTags = (new CollectionTagExtractor())->extract($this->makeCatalog());

        $this->assertTrue($arTags['has_collection_key']);
        $this->assertSame(3, $arTags['offer_count']);
        $this->assertSame(['p1#a', 'p1#b', 'p1#c'], array_keys($arTags['offers']));
        $this->assertSame(['shade_code' => 'DISCO 31', 'collections' => [self::DISCO]], $arTags['offers']['p1#b']);
        $this->assertSame(['shade_code' => null, 'collections' => []], $arTags['offers']['p1#c']);
    }

    public function test_shade_code_longer_than_the_column_becomes_null(): void
    {
        $sFits = str_repeat('D', CollectionTagExtractor::SHADE_CODE_MAX_LENGTH);
        $sTooLong = str_repeat('D', CollectionTagExtractor::SHADE_CODE_MAX_LENGTH + 1);
        $sMultibyteFits = str_repeat('ž', CollectionTagExtractor::SHADE_CODE_MAX_LENGTH);
        $arCatalog = [[
            'offers' => [
                ['id' => 'p1#fits', 'collections' => [self::DISCO], 'shade_code' => $sFits],
                ['id' => 'p1#long', 'collections' => [self::DISCO], 'shade_code' => $sTooLong],
                ['id' => 'p1#multibyte', 'collections' => [self::DISCO], 'shade_code' => $sMultibyteFits],
                ['id' => 'p1#blank', 'collections' => [self::DISCO], 'shade_code' => ''],
            ],
        ]];

        $arOffers = (new CollectionTagExtractor())->extract($arCatalog)['offers'];

        $this->assertSame($sFits, $arOffers['p1#fits']['shade_code']);
        $this->assertNull($arOffers['p1#long']['shade_code']);
        $this->assertSame($sMultibyteFits, $arOffers['p1#multibyte']['shade_code']);
        $this->assertNull($arOffers['p1#blank']['shade_code']);
    }

    public function test_catalog_from_before_the_collections_key_is_reported(): void
    {
        $arCatalog = [['offers' => [['id' => 'p1#a', 'image_url' => 'a.jpg', 'variant_title' => 'X']]]];

        $arTags = (new CollectionTagExtractor())->extract($arCatalog);

        $this->assertFalse($arTags['has_collection_key']);
        $this->assertSame(1, $arTags['offer_count']);
        $this->assertSame(['shade_code' => null, 'collections' => []], $arTags['offers']['p1#a']);
    }

    public function test_empty_catalog_has_no_offers_and_no_key(): void
    {
        $arTags = (new CollectionTagExtractor())->extract([]);

        $this->assertSame(['has_collection_key' => false, 'offer_count' => 0, 'offers' => []], $arTags);
    }
}
