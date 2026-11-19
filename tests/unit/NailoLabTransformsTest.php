<?php

use PHPUnit\Framework\TestCase;
use Logingrupa\ColorClassifier\Classes\NailoLabTransforms;

/**
 * Unit tests for NailoLabTransforms.
 *
 * Covers slug generation and detail URL construction.
 */
class NailoLabTransformsTest extends TestCase
{
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
     * buildListedOfferDetailUrl builds the URL from an offer directory entry.
     */
    public function test_buildListedOfferDetailUrl_uses_the_product_slug_and_variant_title(): void
    {
        $url = NailoLabTransforms::buildListedOfferDetailUrl([
            'product_slug'  => 'magnetic-gel-polish',
            'variant_title' => 'DISCO 49',
            'product_name'  => 'Magnetic Gel Polish',
        ]);

        $this->assertSame('/products/detail/magnetic-gel-polish#v-disco-49--sheet', $url);
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
