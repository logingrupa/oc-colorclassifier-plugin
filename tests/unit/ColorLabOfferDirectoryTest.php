<?php

use Logingrupa\ColorClassifier\Classes\ColorLabMapper;
use Logingrupa\ColorClassifier\Classes\ColorLabOfferDirectory;
use PHPUnit\Framework\TestCase;

/**
 * The public Color Lab shows only entries whose offer the catalog lists, under
 * the catalog's names and links. While the catalog is unreadable it shows only
 * the entries that already carry a shop detail URL.
 */
class ColorLabOfferDirectoryTest extends TestCase
{
    /** @var array<string, array{product_name: string, product_slug: string, offer_name: string, variant_title: string}> */
    private const DIRECTORY = [
        'listed-1' => ['product_name' => 'Magnetic Gel Polish', 'product_slug' => 'magnetic-gel-polish', 'offer_name' => 'Magnetic Gel Polish, 12ml (Clear)', 'variant_title' => '000 Transparent'],
        'listed-2' => ['product_name' => 'Q5 Builder Gel UV/LED', 'product_slug' => 'q5-builder-gel-uv-led', 'offer_name' => 'Q5 Builder Gel UV/LED, 50ml (Milky Way)', 'variant_title' => 'Cosmic Milky'],
    ];

    /**
     * @param array<string, string> $arDetailUrlsByOfferId Offer id to stored detail URL.
     *
     * @return array<int, object> Stored entries, each carrying an offer_id and a detail_url.
     */
    private function makeEntries(array $arDetailUrlsByOfferId): array
    {
        $arEntries = [];

        foreach ($arDetailUrlsByOfferId as $sOfferId => $sDetailUrl) {
            $arEntries[] = (object) ['offer_id' => (string) $sOfferId, 'detail_url' => $sDetailUrl];
        }

        return $arEntries;
    }

    /**
     * @param array<int, object> $arEntries
     *
     * @return array<int, string>
     */
    private function offerIdsOf(array $arEntries): array
    {
        return array_map(static fn (object $obEntry): string => $obEntry->offer_id, $arEntries);
    }

    public function test_entry_whose_offer_lost_its_translation_is_hidden(): void
    {
        $arEntries = $this->makeEntries(['listed-1' => '', 'retired-offer' => '/products/detail/old#v-x--sheet', 'listed-2' => '']);

        $arShown = (new ColorLabOfferDirectory(self::DIRECTORY))->filterListed($arEntries);

        $this->assertSame(['listed-1', 'listed-2'], $this->offerIdsOf($arShown));
    }

    public function test_outage_shows_only_entries_that_carry_a_shop_detail_url(): void
    {
        $arEntries = $this->makeEntries([
            'listed-1'      => '/products/detail/magnetic-gel-polish#v-000-transparent--sheet',
            'only-in-1c'    => '',
            'retired-offer' => '/products/detail/old#v-x--sheet',
        ]);

        $arShown = (new ColorLabOfferDirectory([]))->filterListed($arEntries);

        $this->assertSame(['listed-1', 'retired-offer'], $this->offerIdsOf($arShown), 'A 1C-only entry must never reach the public page.');
    }

    public function test_outage_entry_with_a_null_detail_url_is_hidden(): void
    {
        $arEntries = [(object) ['offer_id' => 'only-in-1c', 'detail_url' => null]];

        $this->assertSame([], (new ColorLabOfferDirectory([]))->filterListed($arEntries));
    }

    public function test_labels_and_link_come_from_the_directory(): void
    {
        $obDirectory = new ColorLabOfferDirectory(self::DIRECTORY);

        $this->assertSame(
            [
                'productName'   => 'Q5 Builder Gel UV/LED',
                'variationName' => 'Cosmic Milky',
                'detailUrl'     => '/products/detail/q5-builder-gel-uv-led#v-cosmic-milky--sheet',
            ],
            $obDirectory->resolveLabels('listed-2')
        );
        $this->assertNull($obDirectory->resolveLabels('retired-offer'));
        $this->assertNull((new ColorLabOfferDirectory([]))->resolveLabels('listed-2'), 'No directory, no labels: stored values stay.');
    }

    public function test_current_labels_replace_the_stored_names_and_link_only(): void
    {
        $arMappedEntry = ['id' => 7, 'productName' => 'Magnetic UV/LED Gel Polish', 'variationName' => '601', 'detailUrl' => '', 'hexColor' => '#aabbcc'];

        $arRelabelled = ColorLabMapper::applyCurrentLabels($arMappedEntry, [
            'productName'   => 'Magnetic Gel Polish',
            'variationName' => '000 Transparent',
            'detailUrl'     => '/products/detail/magnetic-gel-polish#v-000-transparent--sheet',
        ]);

        $this->assertSame('Magnetic Gel Polish', $arRelabelled['productName']);
        $this->assertSame('000 Transparent', $arRelabelled['variationName']);
        $this->assertSame('/products/detail/magnetic-gel-polish#v-000-transparent--sheet', $arRelabelled['detailUrl'], 'A stale or empty stored link must not survive.');
        $this->assertSame('#aabbcc', $arRelabelled['hexColor']);
        $this->assertSame($arMappedEntry, ColorLabMapper::applyCurrentLabels($arMappedEntry, null));
    }

    public function test_listed_offer_ids_and_readability_follow_the_directory(): void
    {
        $obDirectory = new ColorLabOfferDirectory(self::DIRECTORY);

        $this->assertTrue($obDirectory->isReadable());
        $this->assertSame(['listed-1', 'listed-2'], $obDirectory->listedOfferIds());
        $this->assertFalse((new ColorLabOfferDirectory([]))->isReadable());
        $this->assertSame([], (new ColorLabOfferDirectory([]))->listedOfferIds());
    }

    public function test_version_follows_the_directory(): void
    {
        $sVersion = (new ColorLabOfferDirectory(self::DIRECTORY))->version();

        $this->assertSame($sVersion, (new ColorLabOfferDirectory(self::DIRECTORY))->version());

        $arRenamedDirectory = self::DIRECTORY;
        $arRenamedDirectory['listed-1']['product_name'] = 'Renamed';

        $this->assertNotSame($sVersion, (new ColorLabOfferDirectory($arRenamedDirectory))->version(), 'A rename must invalidate the cached payload.');
        $this->assertNotSame($sVersion, (new ColorLabOfferDirectory(['listed-1' => self::DIRECTORY['listed-1']]))->version(), 'A retired offer must invalidate the cached payload.');
        $this->assertNotSame($sVersion, (new ColorLabOfferDirectory([]))->version(), 'An outage must not reuse a filtered payload.');
    }
}
