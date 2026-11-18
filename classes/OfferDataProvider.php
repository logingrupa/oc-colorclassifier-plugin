<?php

namespace Logingrupa\ColorClassifier\Classes;

use Logingrupa\ColorClassifier\Classes\NailoLabTransforms;

/**
 * OfferDataProvider — Bridge to the theme CommerceMlParser.
 *
 * Loads the CommerceMlParser from the theme directory and returns a flat
 * array of all offers that have image URLs, or the 1C collection tags of
 * every offer. This bridges the plugin to the existing theme data layer
 * without requiring the theme to be loaded as a plugin dependency.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class OfferDataProvider
{
    /** @var string Relative path from project root to the CommerceMlParser class file. */
    private const PARSER_CLASS_PATH = 'themes/logingrupa-nailolab/classes/CommerceMlParser.php';

    /** @var string Relative path from project root to the product config file. */
    private const PRODUCT_CONFIG_PATH = 'themes/logingrupa-nailolab/data/product-config.php';

    /** @var string Fully qualified class name of the CommerceMlParser. */
    private const PARSER_FULLY_QUALIFIED_CLASS = 'Themes\\LogingrupaNailolab\\Classes\\CommerceMlParser';

    /**
     * Return a flat list of all offers that have image URLs.
     *
     * Loads the CommerceMlParser from the theme directory, calls getCatalog(),
     * and flattens each product's offers array — keeping only offers that
     * have a non-empty image_url.
     *
     * @return array<int, array{offer_id: string, product_name: string, variation_name: string, image_url: string, product_slug: string, variant_slug: string, detail_url: string}>
     */
    public function getOffersWithImages(): array
    {
        return $this->flattenCatalogToOffersWithImages($this->loadCatalog());
    }

    /**
     * Return the 1C collection tags of every catalog offer, image-less ones included.
     *
     * Reads the same getCatalog() result as getOffersWithImages, so a warm parser
     * cache means no second feed fetch. A failed load gives an empty catalog,
     * which the sync planner aborts on.
     *
     * @return array{has_collection_key: bool, offer_count: int, offers: array<string, array{shade_code: string|null, collections: array<int, array{value_id: string, value: string}>}>}
     */
    public function getCollectionTags(): array
    {
        return (new CollectionTagExtractor())->extract($this->loadCatalog());
    }

    /**
     * @return string The 1C characteristic id the collection tags come from.
     *
     * @throws \RuntimeException When the product config does not name it.
     */
    public function getCollectionCharacteristicId(): string
    {
        $sCharacteristicId = (string) ($this->loadProductConfig()['collection_characteristic_id'] ?? '');

        if ($sCharacteristicId === '') {
            throw new \RuntimeException('product-config.php has no collection_characteristic_id');
        }

        return $sCharacteristicId;
    }

    /**
     * Return the offers the shop lists now, keyed by offer id, with the names it
     * shows for each. Read from the parser's cached directory, so a public
     * request never loads the full catalog.
     *
     * @return array<string, array{product_name: string, offer_name: string, variant_title: string}> Empty when the catalog cannot be read.
     */
    public function getOfferDirectory(): array
    {
        return $this->readFromParser('getOfferDirectory');
    }

    /**
     * @return array<int, array<string, mixed>> Products from getCatalog(), empty when the parser or config is missing or fails.
     */
    private function loadCatalog(): array
    {
        return $this->readFromParser('getCatalog');
    }

    /**
     * Call a read method of the theme parser.
     *
     * @param string $sParserMethod getCatalog or getOfferDirectory.
     *
     * @return array<int|string, mixed> Empty when the parser or config is missing or fails.
     */
    private function readFromParser(string $sParserMethod): array
    {
        $parserClassFilePath = base_path(self::PARSER_CLASS_PATH);

        if (!file_exists($parserClassFilePath)) {
            error_log('[ColorClassifier] CommerceMlParser not found at: ' . $parserClassFilePath);
            return [];
        }

        $productConfig = $this->loadProductConfig();

        if ($productConfig === []) {
            return [];
        }

        if (!class_exists(self::PARSER_FULLY_QUALIFIED_CLASS)) {
            require_once $parserClassFilePath;
        }

        $parserClass = self::PARSER_FULLY_QUALIFIED_CLASS;

        try {
            return (new $parserClass($productConfig))->{$sParserMethod}();
        } catch (\Throwable $exception) {
            error_log('[ColorClassifier] CommerceMlParser error: ' . $exception->getMessage());
            return [];
        }
    }

    /**
     * @return array<string, mixed> The theme product config, empty when the file is missing.
     */
    private function loadProductConfig(): array
    {
        $productConfigPath = base_path(self::PRODUCT_CONFIG_PATH);

        if (!file_exists($productConfigPath)) {
            error_log('[ColorClassifier] Product config not found at: ' . $productConfigPath);
            return [];
        }

        return require $productConfigPath;
    }

    /**
     * Flatten a catalog array to a list of offers that have image URLs.
     *
     * Each product in the catalog may have multiple offers. This method
     * extracts each offer that has a non-empty image_url and returns
     * a flat array of offer data suitable for batch processing.
     *
     * @param array<int, array<string, mixed>> $catalog Products from getCatalog().
     *
     * @return array<int, array{offer_id: string, product_name: string, variation_name: string, image_url: string, product_slug: string, variant_slug: string, detail_url: string}>
     */
    private function flattenCatalogToOffersWithImages(array $catalog): array
    {
        $offersWithImages = [];

        foreach ($catalog as $product) {
            $sProductName  = $product['name'] ?? '';
            $sProductSlug  = $product['slug'] ?? NailoLabTransforms::buildProductSlug($sProductName);
            $productOffers = $product['offers'] ?? [];

            foreach ($productOffers as $offer) {
                $offerImageUrl = $offer['image_url'] ?? '';

                if (empty($offerImageUrl)) {
                    continue;
                }

                $variationName = $offer['variant_title'] ?? ($offer['name'] ?? '');
                $sVariantSlug  = NailoLabTransforms::buildVariantSlug($variationName);
                $sDetailUrl    = NailoLabTransforms::buildDetailUrl($sProductSlug, $sVariantSlug);

                $offersWithImages[] = [
                    'offer_id'       => $offer['id'] ?? '',
                    'product_name'   => $sProductName,
                    'variation_name' => $variationName,
                    'image_url'      => $offerImageUrl,
                    'product_slug'   => $sProductSlug,
                    'variant_slug'   => $sVariantSlug,
                    'detail_url'     => $sDetailUrl,
                ];
            }
        }

        return $offersWithImages;
    }
}
