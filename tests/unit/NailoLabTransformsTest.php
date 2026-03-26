<?php

use PHPUnit\Framework\TestCase;
use Logingrupa\ColorClassifier\Classes\NailoLabTransforms;

/**
 * Unit tests for NailoLabTransforms.
 *
 * Covers all hardcoded product name replacements, offer exclusion logic,
 * slug generation, and detail URL construction.
 */
class NailoLabTransformsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // applyNameReplacements — brand prefix
    // -------------------------------------------------------------------------

    /**
     * 'NAI_S cosmetics' prefix should be replaced with 'NailoLab'.
     */
    public function test_applyNameReplacements_replaces_nais_cosmetics_brand_prefix(): void
    {
        $result = NailoLabTransforms::applyNameReplacements('NAI_S cosmetics Gel Polish UV/LED');

        $this->assertSame('NailoLab Gel Polish UV/LED', $result);
    }

    /**
     * 'NAI_S' prefix alone should be replaced with 'NailoLab'.
     */
    public function test_applyNameReplacements_replaces_nais_brand_prefix(): void
    {
        $result = NailoLabTransforms::applyNameReplacements('NAI_S Quick Builder NUDE BASE');

        $this->assertSame('NailoLab Sculpture Nude Base', $result);
    }

    // -------------------------------------------------------------------------
    // applyNameReplacements — product line renames
    // -------------------------------------------------------------------------

    /**
     * 'Quick Builder Masque SPARKLE BASE' should become 'Fusion Glow Sculpture BASE'.
     */
    public function test_applyNameReplacements_renames_quick_builder_masque_sparkle_base(): void
    {
        $result = NailoLabTransforms::applyNameReplacements('Quick Builder Masque SPARKLE BASE');

        $this->assertSame('Fusion Glow Sculpture BASE', $result);
    }

    /**
     * Double-space variant 'Quick Builder Masque SPARKLE  BASE' should also be renamed.
     */
    public function test_applyNameReplacements_renames_quick_builder_masque_sparkle_base_double_space(): void
    {
        $result = NailoLabTransforms::applyNameReplacements('Quick Builder Masque SPARKLE  BASE');

        $this->assertSame('Fusion Glow Sculpture BASE', $result);
    }

    /**
     * 'Quick Builder NUDE BASE' should become 'Sculpture Nude Base'.
     */
    public function test_applyNameReplacements_renames_quick_builder_nude_base(): void
    {
        $result = NailoLabTransforms::applyNameReplacements('Quick Builder NUDE BASE');

        $this->assertSame('Sculpture Nude Base', $result);
    }

    /**
     * 'Quick Sparkle Shine TOP' should become 'Starlight Sparkly TOP'.
     */
    public function test_applyNameReplacements_renames_quick_sparkle_shine_top(): void
    {
        $result = NailoLabTransforms::applyNameReplacements('Quick Sparkle Shine TOP');

        $this->assertSame('Starlight Sparkly TOP', $result);
    }

    /**
     * 'WANTED Q10 UV/LED Builder Gel' should become 'Builder GEL Q10 UV/LED'.
     */
    public function test_applyNameReplacements_renames_wanted_q10_builder_gel(): void
    {
        $result = NailoLabTransforms::applyNameReplacements('WANTED Q10 UV/LED Builder Gel');

        $this->assertSame('Builder GEL Q10 UV/LED', $result);
    }

    /**
     * 'WANTED Q5 UV/LED Builder Gel' should become 'Builder GEL Q5 UV/LED'.
     */
    public function test_applyNameReplacements_renames_wanted_q5_builder_gel(): void
    {
        $result = NailoLabTransforms::applyNameReplacements('WANTED Q5 UV/LED Builder Gel');

        $this->assertSame('Builder GEL Q5 UV/LED', $result);
    }

    /**
     * 'Acrygel DUO' should become 'Polygel DUO'.
     */
    public function test_applyNameReplacements_renames_acrygel_duo_to_polygel_duo(): void
    {
        $result = NailoLabTransforms::applyNameReplacements('Acrygel DUO');

        $this->assertSame('Polygel DUO', $result);
    }

    /**
     * 'MILKY Builder Base' should become 'MILKY Base'.
     */
    public function test_applyNameReplacements_renames_milky_builder_base(): void
    {
        $result = NailoLabTransforms::applyNameReplacements('MILKY Builder Base');

        $this->assertSame('MILKY Base', $result);
    }

    /**
     * 'Chrome Liquid Foil Art' should become 'Mirror CHROME Liquid Foil Art'.
     */
    public function test_applyNameReplacements_renames_chrome_liquid_to_mirror_chrome_liquid(): void
    {
        $result = NailoLabTransforms::applyNameReplacements('Chrome Liquid Foil Art');

        $this->assertSame('Mirror CHROME Liquid Foil Art', $result);
    }

    /**
     * 'GelX Color 001' should become 'One step Color 001'.
     */
    public function test_applyNameReplacements_renames_gelx_to_one_step(): void
    {
        $result = NailoLabTransforms::applyNameReplacements('GelX Color 001');

        $this->assertSame('One step Color 001', $result);
    }

    // -------------------------------------------------------------------------
    // applyNameReplacements — chained replacements
    // -------------------------------------------------------------------------

    /**
     * A name containing both brand prefix and product line rename should apply both.
     * 'NAI_S Quick Builder Masque SPARKLE BASE' -> 'NailoLab Fusion Glow Sculpture BASE'.
     */
    public function test_applyNameReplacements_chains_brand_prefix_and_product_rename(): void
    {
        $result = NailoLabTransforms::applyNameReplacements('NAI_S Quick Builder Masque SPARKLE BASE');

        $this->assertSame('NailoLab Fusion Glow Sculpture BASE', $result);
    }

    // -------------------------------------------------------------------------
    // applyNameReplacements — no-op for unknown names
    // -------------------------------------------------------------------------

    /**
     * Names without any matching pattern should be returned unchanged.
     */
    public function test_applyNameReplacements_returns_unchanged_name_for_no_match(): void
    {
        $result = NailoLabTransforms::applyNameReplacements('Already Clean Name');

        $this->assertSame('Already Clean Name', $result);
    }

    /**
     * applyNameReplacements is case-insensitive.
     */
    public function test_applyNameReplacements_is_case_insensitive(): void
    {
        $result = NailoLabTransforms::applyNameReplacements('nai_s cosmetics Gel Polish UV/LED');

        $this->assertSame('NailoLab Gel Polish UV/LED', $result);
    }

    // -------------------------------------------------------------------------
    // isExcludedOffer
    // -------------------------------------------------------------------------

    /**
     * The first known excluded offer ID should return true.
     */
    public function test_isExcludedOffer_returns_true_for_first_known_excluded_id(): void
    {
        $isExcluded = NailoLabTransforms::isExcludedOffer(
            'c3b880c5-7480-11e3-806d-00138f293d96#6e486a03-680a-11e5-8275-fcaa1458082c'
        );

        $this->assertTrue($isExcluded);
    }

    /**
     * The second known excluded offer ID should return true.
     */
    public function test_isExcludedOffer_returns_true_for_second_known_excluded_id(): void
    {
        $isExcluded = NailoLabTransforms::isExcludedOffer(
            'c3b880c5-7480-11e3-806d-00138f293d96#b4f2a9c9-807a-11e4-99c7-001d7d9675f2'
        );

        $this->assertTrue($isExcluded);
    }

    /**
     * An unknown offer ID should return false.
     */
    public function test_isExcludedOffer_returns_false_for_unknown_id(): void
    {
        $isExcluded = NailoLabTransforms::isExcludedOffer(
            'some-random-uuid#some-variant-uuid'
        );

        $this->assertFalse($isExcluded);
    }

    // -------------------------------------------------------------------------
    // buildProductSlug
    // -------------------------------------------------------------------------

    /**
     * buildProductSlug produces lowercase hyphenated slug from a product name.
     */
    public function test_buildProductSlug_produces_lowercase_hyphenated_slug(): void
    {
        $slug = NailoLabTransforms::buildProductSlug('NailoLab Gel Polish UV/LED');

        $this->assertSame('nailolab-gel-polish-uv-led', $slug);
    }

    /**
     * buildProductSlug handles names with multiple spaces.
     */
    public function test_buildProductSlug_collapses_multiple_spaces(): void
    {
        $slug = NailoLabTransforms::buildProductSlug('Fusion Glow  Sculpture BASE');

        $this->assertSame('fusion-glow-sculpture-base', $slug);
    }

    // -------------------------------------------------------------------------
    // buildVariantSlug
    // -------------------------------------------------------------------------

    /**
     * buildVariantSlug handles numeric prefix and spaces correctly.
     */
    public function test_buildVariantSlug_handles_numeric_prefix_and_spaces(): void
    {
        $slug = NailoLabTransforms::buildVariantSlug('2604 Sparkling Wine');

        $this->assertSame('2604-sparkling-wine', $slug);
    }

    /**
     * buildVariantSlug produces lowercase slug for plain text.
     */
    public function test_buildVariantSlug_produces_lowercase_slug(): void
    {
        $slug = NailoLabTransforms::buildVariantSlug('CORAL SUNSET');

        $this->assertSame('coral-sunset', $slug);
    }

    // -------------------------------------------------------------------------
    // buildDetailUrl
    // -------------------------------------------------------------------------

    /**
     * buildDetailUrl returns the expected deep-link detail URL format.
     */
    public function test_buildDetailUrl_returns_expected_format(): void
    {
        $url = NailoLabTransforms::buildDetailUrl('gel-polish-uv-led', '2604-sparkling-wine');

        $this->assertSame(
            '/products/detail/gel-polish-uv-led#v-2604-sparkling-wine--sheet',
            $url
        );
    }

    /**
     * buildDetailUrl with different slugs produces correct deep-link.
     */
    public function test_buildDetailUrl_varies_with_different_slugs(): void
    {
        $url = NailoLabTransforms::buildDetailUrl('polygel-duo', '8ml');

        $this->assertSame('/products/detail/polygel-duo#v-8ml--sheet', $url);
    }
}
