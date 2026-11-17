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

    /** @var string CCache key for the offer id map whose image failed in processNewOffers(). */
    private const FAILED_NEW_OFFER_IDS_CACHE_KEY = 'failed_new_offer_ids';

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
     * Process all offers — re-process even those already in the database,
     * except locked rows, which count as skipped.
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

        return $this->processBatch($offersWithImages, $this->fetchLockedOfferIds());
    }

    /**
     * Give a color entry to at most $iLimit catalog offers that have an image and no entry yet.
     *
     * Reads a fresh offer list, never the stored one. NewOfferQueue puts offers whose
     * image failed on an earlier run after the never-tried ones. The failure memory
     * counts each offer as failed until its entry is saved, so an offer that kills
     * the run goes behind them too. An offer with an entry is never touched.
     *
     * @param int $iLimit Most offers to process, 1 or more.
     *
     * @return array{processed: int, failed: int, left: int, total: int, failed_offers: array<int, array{offer_id: string, name: string}>}
     *
     * @throws \RuntimeException When the catalog has no offers with images, which is how a failed catalog load shows.
     */
    public function processNewOffers(int $iLimit): array
    {
        $arOffers = $this->offerDataProvider->getOffersWithImages();

        if ($arOffers === []) {
            throw new \RuntimeException('The catalog has no offers with images, so it did not load. The PHP error log has the cause.');
        }

        $arFailedOfferIdMap  = CCache::get(self::CACHE_TAGS, self::FAILED_NEW_OFFER_IDS_CACHE_KEY) ?? [];
        $arSelection         = NewOfferQueue::select($arOffers, $this->fetchExistingOfferIds(), $arFailedOfferIdMap, $iLimit);
        $arCandidateOfferIds = $arSelection['candidate_offer_ids'];

        $arSucceededOfferIds = [];
        $arFailedOffers      = [];

        foreach ($arSelection['batch'] as $arOffer) {
            // A PHP fatal or an exception skips every later write, so the offer is stored as failed before the work.
            $this->storeFailedOfferIds(
                $arFailedOfferIdMap,
                $arCandidateOfferIds,
                $arSucceededOfferIds,
                [...array_column($arFailedOffers, 'offer_id'), $arOffer['offer_id']]
            );

            if ($this->processOffer($arOffer)) {
                $arSucceededOfferIds[] = $arOffer['offer_id'];
                continue;
            }

            $arFailedOffers[] = $arOffer;
        }

        $this->storeFailedOfferIds($arFailedOfferIdMap, $arCandidateOfferIds, $arSucceededOfferIds, array_column($arFailedOffers, 'offer_id'));

        return [
            'processed'     => count($arSucceededOfferIds),
            'failed'        => count($arFailedOffers),
            'left'          => $arSelection['left'],
            'total'         => $arSelection['total'],
            'failed_offers' => array_map(static fn (array $arOffer): array => [
                'offer_id' => $arOffer['offer_id'],
                'name'     => trim($arOffer['product_name'] . ' ' . $arOffer['variation_name']),
            ], $arFailedOffers),
        ];
    }

    /**
     * Store the failure memory NewOfferQueue gives for the offers handled so far in this run.
     *
     * @param array<string, true> $arFailedOfferIdMap  Failure memory before the run.
     * @param array<int, string>  $arCandidateOfferIds Every candidate of the run, processed or not.
     * @param array<int, string>  $arSucceededOfferIds Offers that got a color entry so far.
     * @param array<int, string>  $arFailedNowOfferIds Offers that count as failed so far.
     *
     * @return void
     */
    private function storeFailedOfferIds(array $arFailedOfferIdMap, array $arCandidateOfferIds, array $arSucceededOfferIds, array $arFailedNowOfferIds): void
    {
        $arNextFailedOfferIdMap = NewOfferQueue::nextFailedOfferIds($arFailedOfferIdMap, $arCandidateOfferIds, $arSucceededOfferIds, $arFailedNowOfferIds);

        CCache::forever(self::CACHE_TAGS, self::FAILED_NEW_OFFER_IDS_CACHE_KEY, $arNextFailedOfferIdMap);
    }

    /**
     * Prepare a batch run by storing a fresh offer list and returning the total count.
     *
     * The stored list is then consumed by processChunk() in subsequent requests.
     *
     * @return array{total: int}
     */
    public function prepareBatch(): array
    {
        CCache::clear(self::CACHE_TAGS, self::OFFER_LIST_CACHE_KEY);

        $arOffersWithImages = $this->fetchAndCacheOfferList();

        return ['total' => count($arOffersWithImages)];
    }

    /**
     * Process a single chunk of offers from the cached list.
     *
     * Designed to be called repeatedly from the frontend in small batches
     * to avoid HTTP timeouts on serverless or time-limited hosting.
     *
     * @param int    $iOffset    Zero-based index into the cached offer list.
     * @param int    $iBatchSize Number of offers to process in this chunk.
     * @param string $sMode      'all' (skips locked rows) or 'new' (skips existing rows).
     *
     * @return array{processed: int, skipped: int, failed: int, total: int, done: bool}
     */
    public function processChunk(int $iOffset, int $iBatchSize, string $sMode): array
    {
        $arOffersWithImages = $this->fetchAndCacheOfferList();
        $arChunk            = array_slice($arOffersWithImages, $iOffset, $iBatchSize);

        $arExistingOfferIdMap = ($sMode === 'new') ? $this->fetchExistingOfferIds() : $this->fetchLockedOfferIds();

        $arResult         = $this->processBatch($arChunk, $arExistingOfferIdMap);
        $arResult['done'] = ($iOffset + $iBatchSize) >= count($arOffersWithImages);

        return $arResult;
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
     * Fetch the offer IDs whose ColorEntry is locked, so Process All skips them.
     *
     * @return array<string, true> Lookup map of offer_id => true.
     */
    private function fetchLockedOfferIds(): array
    {
        $arLockedOfferIdMap = [];

        foreach (ColorEntry::locked()->pluck('offer_id') as $sOfferId) {
            $arLockedOfferIdMap[$sOfferId] = true;
        }

        return $arLockedOfferIdMap;
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

            if (!$this->processOffer($offer)) {
                $failedCount++;
                continue;
            }

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
     * Analyze one offer's image and upsert its ColorEntry.
     *
     * @param array{offer_id: string, product_name: string, variation_name: string, image_url: string} $offer Offer metadata.
     *
     * @return bool True when the entry was stored, false when the image could not be processed.
     */
    private function processOffer(array $offer): bool
    {
        $imageResult = ColorExtractor::processImage($offer['image_url']);

        if ($imageResult === null) {
            return false;
        }

        $this->storeColorEntry($offer, $imageResult);

        return true;
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
