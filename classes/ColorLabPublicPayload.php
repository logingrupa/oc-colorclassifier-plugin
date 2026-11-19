<?php

namespace Logingrupa\ColorClassifier\Classes;

use Logingrupa\ColorClassifier\Models\ColorEntry;
use Logingrupa\ColorClassifier\Models\ColorFamily;

/**
 * ColorLabPublicPayload - the payloads of the public Color Lab: the page entries
 * and the /api/color-lab/data document.
 *
 * Sole responsibility: assemble them for the offers the catalog lists, without
 * the cropped image blobs, and remember them per catalog directory version so
 * the page, the component and the data route share one cached copy. While the
 * catalog cannot be read, the last payload built for a real directory is served,
 * and cacheControl() tells the browser not to keep it.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class ColorLabPublicPayload
{
    /** @var string Browser cache of a payload served while the catalog cannot be read. */
    private const CACHE_CONTROL_DEGRADED = 'no-store';

    private ColorLabOfferDirectory $obOfferDirectory;

    /**
     * @param ColorLabOfferDirectory|null $obOfferDirectory Directory or null to load the current one.
     */
    public function __construct(?ColorLabOfferDirectory $obOfferDirectory = null)
    {
        $this->obOfferDirectory = $obOfferDirectory ?? ColorLabOfferDirectory::load();
    }

    /**
     * @return array{entriesJson: string, entryCount: int} Entries pre-encoded, so a cache hit never hydrates models.
     */
    public function pageEntries(): array
    {
        return $this->remember(ColorLabPayloadCache::KEY_PAGE_ENTRIES, function (): array {
            $arColorData = $this->mapListedEntries();

            return [
                'entriesJson' => (string) json_encode($arColorData),
                'entryCount'  => count($arColorData),
            ];
        });
    }

    /**
     * @return array{last_updated_at: string, entries: array<int, array<string, mixed>>, taxonomy: array<string, array<int, string>>}
     */
    public function data(): array
    {
        return $this->remember(ColorLabPayloadCache::KEY_DATA, function (): array {
            return [
                'last_updated_at' => (string) ColorEntry::max('updated_at'),
                'entries'         => $this->mapListedEntries(),
                'taxonomy'        => ColorLabMapper::mapTaxonomy(ColorFamily::listFamilyNamesInOrder()),
            ];
        });
    }

    /**
     * @param string $sCurrentCacheControl Cache-Control of a response built for the current catalog.
     *
     * @return string That value, or no-store while the catalog cannot be read.
     */
    public function cacheControl(string $sCurrentCacheControl): string
    {
        return $this->obOfferDirectory->isReadable() ? $sCurrentCacheControl : self::CACHE_CONTROL_DEGRADED;
    }

    /**
     * @param string   $sPayloadKey     One of the ColorLabPayloadCache KEY_* constants.
     * @param \Closure $fnBuildPayload  Builds the payload on a cache miss.
     *
     * @return array<string, mixed>
     */
    private function remember(string $sPayloadKey, \Closure $fnBuildPayload): array
    {
        return ColorLabPayloadCache::remember(
            $sPayloadKey,
            $fnBuildPayload,
            $this->obOfferDirectory->version(),
            !$this->obOfferDirectory->isReadable()
        );
    }

    /**
     * @return array<int, array<string, mixed>> Public entries; only the rows of listed offers are read while the catalog is readable.
     */
    private function mapListedEntries(): array
    {
        $obEntryQuery = ColorEntry::withoutImageData();

        if ($this->obOfferDirectory->isReadable()) {
            $obEntryQuery->whereIn('offer_id', $this->obOfferDirectory->listedOfferIds());
        }

        return ColorLabMapper::mapPublicEntries($obEntryQuery->get(), $this->obOfferDirectory);
    }
}
