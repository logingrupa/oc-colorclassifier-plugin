<?php

use Logingrupa\ColorClassifier\Classes\ColorLabMapper;
use Logingrupa\ColorClassifier\Classes\ColorLabOfferDirectory;
use PHPUnit\Framework\TestCase;

/**
 * The public Color Lab shows only entries whose offer the catalog lists, under
 * the catalog's names; an unreadable catalog shows every stored entry unchanged.
 */
class ColorLabOfferDirectoryTest extends TestCase
{
    /** @var array<string, array{product_name: string, offer_name: string, variant_title: string}> */
    private const DIRECTORY = [
        'listed-1' => ['product_name' => 'Magnetic Gel Polish', 'offer_name' => 'Magnetic Gel Polish, 12ml (Clear)', 'variant_title' => '000 Transparent'],
        'listed-2' => ['product_name' => 'Q5 Builder Gel UV/LED', 'offer_name' => 'Q5 Builder Gel UV/LED, 50ml (Milky Way)', 'variant_title' => 'Cosmic Milky'],
    ];

    /**
     * @param string ...$arOfferIds
     *
     * @return array<int, object> Stored entries, each carrying an offer_id.
     */
    private function makeEntries(string ...$arOfferIds): array
    {
        return array_map(static fn (string $sOfferId): object => (object) ['offer_id' => $sOfferId], $arOfferIds);
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
        $arEntries = $this->makeEntries('listed-1', 'retired-offer', 'listed-2');

        $arShown = (new ColorLabOfferDirectory(self::DIRECTORY))->filterListed($arEntries);

        $this->assertSame(['listed-1', 'listed-2'], $this->offerIdsOf($arShown));
    }

    public function test_outage_shows_every_stored_entry(): void
    {
        $arEntries = $this->makeEntries('listed-1', 'retired-offer');

        $arShown = (new ColorLabOfferDirectory([]))->filterListed($arEntries);

        $this->assertSame(['listed-1', 'retired-offer'], $this->offerIdsOf($arShown), 'An unreadable catalog must not blank the page.');
    }

    public function test_labels_come_from_the_directory(): void
    {
        $obDirectory = new ColorLabOfferDirectory(self::DIRECTORY);

        $this->assertSame(
            ['productName' => 'Q5 Builder Gel UV/LED', 'variationName' => 'Cosmic Milky'],
            $obDirectory->resolveLabels('listed-2')
        );
        $this->assertNull($obDirectory->resolveLabels('retired-offer'));
        $this->assertNull((new ColorLabOfferDirectory([]))->resolveLabels('listed-2'), 'No directory, no labels: stored names stay.');
    }

    public function test_current_labels_replace_the_stored_names_only(): void
    {
        $arMappedEntry = ['id' => 7, 'productName' => 'Magnetic UV/LED Gel Polish', 'variationName' => '601', 'hexColor' => '#aabbcc'];

        $arRelabelled = ColorLabMapper::applyCurrentLabels($arMappedEntry, ['productName' => 'Magnetic Gel Polish', 'variationName' => '000 Transparent']);

        $this->assertSame('Magnetic Gel Polish', $arRelabelled['productName']);
        $this->assertSame('000 Transparent', $arRelabelled['variationName']);
        $this->assertSame('#aabbcc', $arRelabelled['hexColor']);
        $this->assertSame($arMappedEntry, ColorLabMapper::applyCurrentLabels($arMappedEntry, null));
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
