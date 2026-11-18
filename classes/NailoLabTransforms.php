<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * NailoLabTransforms — NailoLab slug and detail URL helpers.
 *
 * A pure-static utility class that holds:
 *   - Slug generation helpers (product and variant slugs)
 *   - Detail URL builder
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class NailoLabTransforms
{
    /**
     * Build a URL-safe lowercase hyphenated slug from a product name.
     *
     * @param string $sProductName Product name as the catalog carries it.
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
