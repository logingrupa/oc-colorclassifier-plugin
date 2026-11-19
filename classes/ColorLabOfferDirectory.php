<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * ColorLabOfferDirectory - decides which stored color entries the public Color
 * Lab shows, and with which names.
 *
 * Built on the theme parser's offer directory (offer id to current names). An
 * entry whose offer the directory no longer lists is hidden from the public
 * outputs; the backend list keeps every row. When the directory is empty (feed
 * outage) every stored entry is shown unchanged, so an outage never blanks the page.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class ColorLabOfferDirectory
{
    /** @var string Version while no directory is readable, distinct from every directory hash. */
    private const UNFILTERED_VERSION = 'unfiltered';

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
     * Stamp that changes whenever the listed offers or their names change, so a
     * cached public payload built from another directory is never served.
     *
     * @return string
     */
    public function version(): string
    {
        if ($this->arOfferDirectory === []) {
            return self::UNFILTERED_VERSION;
        }

        return md5((string) json_encode($this->arOfferDirectory));
    }

    /**
     * Keep the entries whose offer the catalog lists.
     *
     * @param iterable<object> $obEntries Rows carrying an offer_id.
     *
     * @return array<int, object> All rows, in order, when no directory is readable.
     */
    public function filterListed(iterable $obEntries): array
    {
        $arListedEntries = [];

        foreach ($obEntries as $obEntry) {
            if ($this->arOfferDirectory === [] || array_key_exists((string) $obEntry->offer_id, $this->arOfferDirectory)) {
                $arListedEntries[] = $obEntry;
            }
        }

        return $arListedEntries;
    }

    /**
     * @param string $sOfferId
     *
     * @return array{productName: string, variationName: string}|null Null when the catalog has no entry for the offer.
     */
    public function resolveLabels(string $sOfferId): ?array
    {
        if (!array_key_exists($sOfferId, $this->arOfferDirectory)) {
            return null;
        }

        return [
            'productName'   => $this->arOfferDirectory[$sOfferId]['product_name'],
            'variationName' => $this->arOfferDirectory[$sOfferId]['variant_title'],
        ];
    }
}
