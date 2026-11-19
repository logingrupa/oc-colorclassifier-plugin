<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * ColorLabOfferDirectory - decides which stored color entries the public Color
 * Lab shows, and with which names and links.
 *
 * Built on the theme parser's offer directory (offer id to current names). An
 * entry whose offer the directory no longer lists is hidden from the public
 * outputs; the backend list keeps every row. When the directory is empty (feed
 * outage) only entries that already carry a shop detail URL are shown, because
 * those are the offers the shop listed when they were stored; the rest of the
 * table holds 1C-only offers that never belonged on the public page.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class ColorLabOfferDirectory
{
    /** @var string Version while no directory is readable, distinct from every directory hash. */
    private const UNREADABLE_VERSION = 'unreadable';

    /** @var array<string, array{product_name: string, product_slug: string, offer_name: string, variant_title: string}> */
    private array $arOfferDirectory;

    /**
     * @param array<string, array{product_name: string, product_slug: string, offer_name: string, variant_title: string}> $arOfferDirectory
     *        Empty when the catalog cannot be read.
     */
    public function __construct(array $arOfferDirectory)
    {
        $this->arOfferDirectory = $arOfferDirectory;
    }

    /**
     * @param OfferDataProvider|null $obOfferDataProvider Provider instance or null to use default.
     *
     * @return self
     */
    public static function load(?OfferDataProvider $obOfferDataProvider = null): self
    {
        return new self(($obOfferDataProvider ?? new OfferDataProvider())->getOfferDirectory());
    }

    /**
     * @return bool False while the catalog cannot be read.
     */
    public function isReadable(): bool
    {
        return $this->arOfferDirectory !== [];
    }

    /**
     * @return array<int, string> Ids of the offers the catalog lists, empty while it cannot be read.
     */
    public function listedOfferIds(): array
    {
        return array_map('strval', array_keys($this->arOfferDirectory));
    }

    /**
     * Stamp that changes whenever the listed offers or their names change, so a
     * cached public payload built from another directory is never served.
     *
     * @return string
     */
    public function version(): string
    {
        if (!$this->isReadable()) {
            return self::UNREADABLE_VERSION;
        }

        return md5((string) json_encode($this->arOfferDirectory));
    }

    /**
     * Keep the entries the public Color Lab may show.
     *
     * @param iterable<object> $obEntries Rows carrying an offer_id and a detail_url.
     *
     * @return array<int, object> In order. While the catalog cannot be read: the rows with a detail URL.
     */
    public function filterListed(iterable $obEntries): array
    {
        $arListedEntries = [];

        foreach ($obEntries as $obEntry) {
            if ($this->isListed($obEntry)) {
                $arListedEntries[] = $obEntry;
            }
        }

        return $arListedEntries;
    }

    /**
     * @param string $sOfferId
     *
     * @return array{productName: string, variationName: string, detailUrl: string}|null Null when the catalog has no entry for the offer.
     */
    public function resolveLabels(string $sOfferId): ?array
    {
        if (!array_key_exists($sOfferId, $this->arOfferDirectory)) {
            return null;
        }

        return [
            'productName'   => $this->arOfferDirectory[$sOfferId]['product_name'],
            'variationName' => $this->arOfferDirectory[$sOfferId]['variant_title'],
            'detailUrl'     => NailoLabTransforms::buildListedOfferDetailUrl($this->arOfferDirectory[$sOfferId]),
        ];
    }

    /**
     * @param object $obEntry Row carrying an offer_id and a detail_url.
     *
     * @return bool
     */
    private function isListed(object $obEntry): bool
    {
        if (!$this->isReadable()) {
            return (string) $obEntry->detail_url !== '';
        }

        return array_key_exists((string) $obEntry->offer_id, $this->arOfferDirectory);
    }
}
