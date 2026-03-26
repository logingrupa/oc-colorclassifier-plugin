<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * NailoLabTransforms — Hardcoded NailoLab product name replacements and offer filters.
 *
 * A pure-static utility class that holds all NailoLab-specific transforms:
 *   - Product name replacements (brand prefix + product line renames)
 *   - Excluded offer IDs (composite product#variant UUIDs)
 *   - Slug generation helpers (product and variant slugs)
 *   - Detail URL builder
 *
 * Keeping these transforms in the plugin makes the ColorClassifier independent
 * of the theme's product-config.php for branding and filtering logic, enabling
 * future data source swaps (e.g. Shopaholic).
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class NailoLabTransforms
{
    /**
     * Case-insensitive find-replace pairs applied to every product name.
     *
     * Order is significant — more specific patterns must appear before shorter
     * ones (e.g. 'NAI_S cosmetics' before 'NAI_S') to prevent partial matches
     * from short patterns consuming characters that belong to longer patterns.
     *
     * @var array<string, string>
     */
    private const NAME_REPLACEMENTS = [
        // Brand prefix — applied to all products; more specific first
        'NAI_S cosmetics'                    => 'NailoLab',
        'NAI_S'                              => 'NailoLab',

        // Product line renames — double-space variant from offers.xml first
        'Quick Builder Masque SPARKLE  BASE' => 'Fusion Glow Sculpture BASE',
        'Quick Builder Masque SPARKLE BASE'  => 'Fusion Glow Sculpture BASE',
        'Quick Builder NUDE BASE'            => 'Sculpture Nude Base',
        'Quick Sparkle Shine TOP'            => 'Starlight Sparkly TOP',
        'WANTED Q10 UV/LED Builder Gel'      => 'Builder GEL Q10 UV/LED',
        'WANTED Q5 UV/LED Builder Gel'       => 'Builder GEL Q5 UV/LED',
        'Acrygel DUO'                        => 'Polygel DUO',
        'MILKY Builder Base'                 => 'MILKY Base',
        'Chrome Liquid'                      => 'Mirror CHROME Liquid',
        'GelX'                               => 'One step',
    ];

    /**
     * Composite offer IDs (productUUID#variantUUID) that must be excluded
     * from the ColorClassifier offer list.
     *
     * Stored as a flipped array (keys = IDs, values = true) to allow O(1) lookup.
     *
     * @var array<string, true>
     */
    private const EXCLUDED_OFFER_IDS = [
        'c3b880c5-7480-11e3-806d-00138f293d96#6e486a03-680a-11e5-8275-fcaa1458082c' => true,
        'c3b880c5-7480-11e3-806d-00138f293d96#b4f2a9c9-807a-11e4-99c7-001d7d9675f2' => true,
    ];

    /**
     * Apply all hardcoded NailoLab product name replacements to a product name.
     *
     * Uses str_ireplace (case-insensitive) so that names from the XML are
     * matched regardless of capitalisation differences between data exports.
     *
     * @param string $sProductName Raw product name from the CommerceML catalog.
     *
     * @return string Transformed product name with NailoLab branding applied.
     */
    public static function applyNameReplacements(string $sProductName): string
    {
        return str_ireplace(
            array_keys(self::NAME_REPLACEMENTS),
            array_values(self::NAME_REPLACEMENTS),
            $sProductName
        );
    }

    /**
     * Determine whether a given offer ID should be excluded from the catalog.
     *
     * @param string $sOfferId Composite offer ID in "productUUID#variantUUID" format.
     *
     * @return bool True if the offer should be excluded, false otherwise.
     */
    public static function isExcludedOffer(string $sOfferId): bool
    {
        return isset(self::EXCLUDED_OFFER_IDS[$sOfferId]);
    }

    /**
     * Build a URL-safe lowercase hyphenated slug from a product name.
     *
     * @param string $sProductName Product name (already transformed by applyNameReplacements).
     *
     * @return string Lowercase hyphenated slug suitable for use in a URL path segment.
     */
    public static function buildProductSlug(string $sProductName): string
    {
        return self::slugify($sProductName);
    }

    /**
     * Build a URL-safe lowercase hyphenated slug from a variant/variation name.
     *
     * @param string $sVariationName Variation name (e.g. "2604 Sparkling Wine").
     *
     * @return string Lowercase hyphenated slug suitable for use in a URL fragment.
     */
    public static function buildVariantSlug(string $sVariationName): string
    {
        return self::slugify($sVariationName);
    }

    /**
     * Build the deep-link detail URL for a specific product variant.
     *
     * The URL opens the product detail page with the color sheet pre-opened
     * for the specified variant, following the pattern used by the theme JS.
     *
     * @param string $sProductSlug URL-safe product slug from buildProductSlug().
     * @param string $sVariantSlug URL-safe variant slug from buildVariantSlug().
     *
     * @return string Full detail page URL with color-sheet fragment anchor.
     */
    public static function buildDetailUrl(string $sProductSlug, string $sVariantSlug): string
    {
        return "/products/detail/{$sProductSlug}#v-{$sVariantSlug}--sheet";
    }

    /**
     * Convert a string to a URL-safe lowercase hyphenated slug.
     *
     * Steps:
     *   1. Lowercase the entire string (mb_strtolower, UTF-8 aware).
     *   2. Replace any sequence of non-alphanumeric characters with a single hyphen.
     *   3. Trim leading and trailing hyphens.
     *
     * @param string $sInput Raw string to slugify.
     *
     * @return string URL-safe lowercase slug.
     */
    private static function slugify(string $sInput): string
    {
        $sLower  = mb_strtolower($sInput, 'UTF-8');
        $sHyph   = preg_replace('/[^a-z0-9]+/', '-', $sLower);
        return trim($sHyph, '-');
    }
}
