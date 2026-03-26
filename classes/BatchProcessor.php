<?php

namespace Logingrupa\ColorClassifier\Classes;

use Logingrupa\ColorClassifier\Models\ColorEntry;
use Kharanenka\Helper\CCache;

/**
 * BatchProcessor — Orchestrates end-to-end offer color classification.
 *
 * Fetches offers from OfferDataProvider, runs each through the image
 * processing pipeline (ColorExtractor), converts colors (ColorConverter),
 * finds names (ColorNamer), classifies taxonomy (ColorClassifier), and
 * upserts results into the ColorEntry model.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class BatchProcessor
{
    /** @var array<int, string> CCache tags for offer list caching. */
    private const CACHE_TAGS = ['logingrupa', 'colorclassifier'];

    /** @var string CCache key for the raw offer list. */
    private const OFFER_LIST_CACHE_KEY = 'offer_list';

    /** @var int Chunk size for querying existing offer IDs to avoid memory issues. */
    private const EXISTING_OFFERS_CHUNK_SIZE = 500;

    /** @var OfferDataProvider The data provider used to fetch offers. */
    private OfferDataProvider $offerDataProvider;

    /**
     * Initialise the BatchProcessor with an optional OfferDataProvider.
     *
     * Dependency injection allows test doubles to replace the real provider.
     *
     * @param OfferDataProvider|null $offerDataProvider Provider instance or null to use default.
     */
    public function __construct(?OfferDataProvider $offerDataProvider = null)
    {
        $this->offerDataProvider = $offerDataProvider ?? new OfferDataProvider();
    }

    /**
     * Process all offers — re-process even those already in the database.
     *
     * Clears the offer list cache before fetching to ensure fresh data.
     * Each offer runs through the full pipeline and its ColorEntry is
     * created or updated via updateOrCreate.
     *
     * @return array{processed: int, skipped: int, failed: int, total: int}
     */
    public function processAll(): array
    {
        CCache::clear(self::CACHE_TAGS, self::OFFER_LIST_CACHE_KEY);

        $offersWithImages = $this->fetchAndCacheOfferList();

        return $this->processBatch($offersWithImages, []);
    }

    /**
     * Process only new offers — skip any offer already in the database.
     *
     * Fetches the existing offer IDs from the database in chunks to avoid
     * memory issues, then skips any offer that is already processed.
     *
     * @return array{processed: int, skipped: int, failed: int, total: int}
     */
    public function processNew(): array
    {
        $offersWithImages   = $this->fetchAndCacheOfferList();
        $existingOfferIds   = $this->fetchExistingOfferIds();

        return $this->processBatch($offersWithImages, $existingOfferIds);
    }

    /**
     * Fetch the offer list, using CCache if available, or fetching fresh.
     *
     * @return array<int, array{offer_id: string, product_name: string, variation_name: string, image_url: string}>
     */
    private function fetchAndCacheOfferList(): array
    {
        $cachedOfferList = CCache::get(self::CACHE_TAGS, self::OFFER_LIST_CACHE_KEY);

        if (!empty($cachedOfferList)) {
            return $cachedOfferList;
        }

        $offersWithImages = $this->offerDataProvider->getOffersWithImages();

        CCache::forever(self::CACHE_TAGS, self::OFFER_LIST_CACHE_KEY, $offersWithImages);

        return $offersWithImages;
    }

    /**
     * Fetch all offer IDs that already have a ColorEntry in the database.
     *
     * Queries in chunks to avoid loading all IDs into memory at once.
     *
     * @return array<string, true> Lookup map of offer_id => true for O(1) existence checks.
     */
    private function fetchExistingOfferIds(): array
    {
        $existingOfferIdMap = [];

        ColorEntry::select('offer_id')->chunk(self::EXISTING_OFFERS_CHUNK_SIZE, function ($entries) use (&$existingOfferIdMap) {
            foreach ($entries as $colorEntry) {
                $existingOfferIdMap[$colorEntry->offer_id] = true;
            }
        });

        return $existingOfferIdMap;
    }

    /**
     * Process a batch of offers, skipping those in the excluded IDs map.
     *
     * For each offer: download and analyze the image, classify the dominant
     * color, and upsert a ColorEntry record.
     *
     * @param array<int, array{offer_id: string, product_name: string, variation_name: string, image_url: string}> $offersWithImages All offers to consider.
     * @param array<string, true> $existingOfferIdMap Offer IDs to skip (already processed).
     *
     * @return array{processed: int, skipped: int, failed: int, total: int}
     */
    private function processBatch(array $offersWithImages, array $existingOfferIdMap): array
    {
        $processedCount = 0;
        $skippedCount   = 0;
        $failedCount    = 0;
        $totalCount     = count($offersWithImages);

        foreach ($offersWithImages as $offer) {
            $offerId = $offer['offer_id'];

            if (isset($existingOfferIdMap[$offerId])) {
                $skippedCount++;
                continue;
            }

            $imageResult = ColorExtractor::processImage($offer['image_url']);

            if ($imageResult === null) {
                $failedCount++;
                continue;
            }

            $this->storeColorEntry($offer, $imageResult);
            $processedCount++;
        }

        return [
            'processed' => $processedCount,
            'skipped'   => $skippedCount,
            'failed'    => $failedCount,
            'total'     => $totalCount,
        ];
    }

    /**
     * Re-process a single existing ColorEntry by re-downloading and re-analyzing its image.
     *
     * @param ColorEntry $colorEntry The existing entry to re-process.
     *
     * @return bool True if re-processing succeeded, false on failure.
     */
    public function reprocessEntry(ColorEntry $colorEntry): bool
    {
        $imageResult = ColorExtractor::processImage($colorEntry->image_url);

        if ($imageResult === null) {
            return false;
        }

        $dominantRgb = $imageResult['rgb'];
        $red         = $dominantRgb['red'];
        $green       = $dominantRgb['green'];
        $blue        = $dominantRgb['blue'];

        $hexColor  = ColorConverter::rgbToHex($red, $green, $blue);
        $taxonomy  = ColorClassifier::classify($red, $green, $blue);

        $colorEntry->update([
            'hex_color'          => $hexColor,
            'oklch_values'       => ColorConverter::rgbToOklch($red, $green, $blue),
            'palette_colors'     => $imageResult['palette'],
            'color_name'         => ColorNamer::findNearestColorName($hexColor),
            'taxonomy'           => $taxonomy,
            'confidence_score'   => $taxonomy['confidence_score'],
            'cropped_image_data' => $imageResult['cropped_image_data'] ?? null,
            'processed_at'       => now(),
        ]);

        return true;
    }

    /**
     * Convert image analysis results to taxonomy and persist a ColorEntry record.
     *
     * @param array{offer_id: string, product_name: string, variation_name: string, image_url: string} $offer       Offer metadata.
     * @param array{rgb: array{red: int, green: int, blue: int}, palette: array<int, string>}          $imageResult Image processing result.
     *
     * @return void
     */
    private function storeColorEntry(array $offer, array $imageResult): void
    {
        $dominantRgb = $imageResult['rgb'];
        $red         = $dominantRgb['red'];
        $green       = $dominantRgb['green'];
        $blue        = $dominantRgb['blue'];

        $hexColor   = ColorConverter::rgbToHex($red, $green, $blue);
        $oklchValues = ColorConverter::rgbToOklch($red, $green, $blue);
        $colorName  = ColorNamer::findNearestColorName($hexColor);
        $taxonomy   = ColorClassifier::classify($red, $green, $blue);

        $confidenceScore = $taxonomy['confidence_score'];

        ColorEntry::updateOrCreate(
            ['offer_id' => $offer['offer_id']],
            [
                'product_name'       => $offer['product_name'],
                'variation_name'     => $offer['variation_name'],
                'image_url'          => $offer['image_url'],
                'detail_url'         => $offer['detail_url'] ?? null,
                'hex_color'          => $hexColor,
                'oklch_values'       => $oklchValues,
                'palette_colors'     => $imageResult['palette'],
                'color_name'         => $colorName,
                'taxonomy'           => $taxonomy,
                'confidence_score'   => $confidenceScore,
                'cropped_image_data' => $imageResult['cropped_image_data'] ?? null,
                'processed_at'       => now(),
            ]
        );
    }
}
