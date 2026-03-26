<?php

namespace Logingrupa\ColorClassifier\Classes;

use Logingrupa\ColorClassifier\Classes\NailoLabTransforms;

/**
 * OfferDataProvider — Bridge to the theme CommerceMlParser.
 *
 * Loads the CommerceMlParser from the theme directory and returns a flat
 * array of all offers that have image URLs. This bridges the plugin to
 * the existing theme data layer without requiring the theme to be loaded
 * as a plugin dependency.
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
        $parserClassFilePath  = base_path(self::PARSER_CLASS_PATH);
        $productConfigPath    = base_path(self::PRODUCT_CONFIG_PATH);

        if (!file_exists($parserClassFilePath)) {
            error_log('[ColorClassifier] CommerceMlParser not found at: ' . $parserClassFilePath);
            return [];
        }

        if (!file_exists($productConfigPath)) {
            error_log('[ColorClassifier] Product config not found at: ' . $productConfigPath);
            return [];
        }

        if (!class_exists(self::PARSER_FULLY_QUALIFIED_CLASS)) {
            require_once $parserClassFilePath;
        }

        $productConfig = require $productConfigPath;
        $parserClass   = self::PARSER_FULLY_QUALIFIED_CLASS;

        try {
            $parser  = new $parserClass($productConfig);
            $catalog = $parser->getCatalog();
        } catch (\Throwable $exception) {
            error_log('[ColorClassifier] CommerceMlParser error: ' . $exception->getMessage());
            return [];
        }

        return $this->flattenCatalogToOffersWithImages($catalog);
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
            $sProductName  = NailoLabTransforms::applyNameReplacements($product['name'] ?? '');
            $sProductSlug  = $product['slug'] ?? NailoLabTransforms::buildProductSlug($sProductName);
            $productOffers = $product['offers'] ?? [];

            foreach ($productOffers as $offer) {
                if (NailoLabTransforms::isExcludedOffer($offer['id'] ?? '')) {
                    continue;
                }

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
