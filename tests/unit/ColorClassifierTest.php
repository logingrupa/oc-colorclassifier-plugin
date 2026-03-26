<?php

use PHPUnit\Framework\TestCase;
use Logingrupa\ColorClassifier\Classes\ColorClassifier;

/**
 * Unit tests for ColorClassifier.
 *
 * Covers taxonomy classification requirements:
 *   CC-06 — classify returns correct family for primary colors
 *   CC-07 — classify returns correct undertone for warm/cool colors
 *   CC-08 — classify returns correct depth for black and white
 *   CC-09 — finish is always null, opacity is always Opaque
 *   CC-10 — confidence_score is always between 0.00 and 1.00
 */
class ColorClassifierTest extends TestCase
{
    /**
     * Pure red (255, 0, 0) should classify as family Red with warm undertone.
     */
    public function test_classify_pure_red_returns_red_family_and_warm_undertone(): void
    {
        $taxonomy = ColorClassifier::classify(255, 0, 0);

        $this->assertSame('Red', $taxonomy['family']);
        $this->assertSame('Warm', $taxonomy['undertone']);
    }

    /**
     * Pure red depth should not be Very Light or Very Dark.
     */
    public function test_classify_pure_red_depth_is_not_extreme(): void
    {
        $taxonomy = ColorClassifier::classify(255, 0, 0);

        $this->assertNotSame('Very Light', $taxonomy['depth']);
        $this->assertNotSame('Very Dark', $taxonomy['depth']);
    }

    /**
     * Pure blue (0, 0, 255) should classify as family Blue with cool undertone.
     */
    public function test_classify_pure_blue_returns_blue_family_and_cool_undertone(): void
    {
        $taxonomy = ColorClassifier::classify(0, 0, 255);

        $this->assertSame('Blue', $taxonomy['family']);
        $this->assertSame('Cool', $taxonomy['undertone']);
    }

    /**
     * Black (0, 0, 0) should classify as family Black with depth Very Dark.
     */
    public function test_classify_black_returns_black_family_and_very_dark_depth(): void
    {
        $taxonomy = ColorClassifier::classify(0, 0, 0);

        $this->assertSame('Black', $taxonomy['family']);
        $this->assertSame('Very Dark', $taxonomy['depth']);
    }

    /**
     * White (255, 255, 255) should classify as family White with depth Very Light.
     */
    public function test_classify_white_returns_white_family_and_very_light_depth(): void
    {
        $taxonomy = ColorClassifier::classify(255, 255, 255);

        $this->assertSame('White', $taxonomy['family']);
        $this->assertSame('Very Light', $taxonomy['depth']);
    }

    /**
     * Light pink (255, 182, 193) should classify as Pink or Rose family.
     */
    public function test_classify_light_pink_returns_pink_or_rose_family(): void
    {
        $taxonomy = ColorClassifier::classify(255, 182, 193);

        $this->assertContains($taxonomy['family'], ['Pink', 'Rose']);
    }

    /**
     * classify should always return finish as null.
     */
    public function test_classify_always_returns_null_finish(): void
    {
        $taxonomy = ColorClassifier::classify(128, 64, 200);

        $this->assertNull($taxonomy['finish']);
    }

    /**
     * classify should always return opacity as Opaque.
     */
    public function test_classify_always_returns_opaque_opacity(): void
    {
        $taxonomy = ColorClassifier::classify(50, 150, 100);

        $this->assertSame('Opaque', $taxonomy['opacity']);
    }

    /**
     * confidence_score should always be between 0.00 and 1.00 inclusive.
     */
    public function test_classify_confidence_score_is_always_valid_range(): void
    {
        $colorValues = [
            [255, 0, 0],
            [0, 0, 0],
            [255, 255, 255],
            [128, 128, 128],
            [100, 200, 50],
        ];

        foreach ($colorValues as [$red, $green, $blue]) {
            $taxonomy = ColorClassifier::classify($red, $green, $blue);

            $this->assertGreaterThanOrEqual(0.00, $taxonomy['confidence_score']);
            $this->assertLessThanOrEqual(1.00, $taxonomy['confidence_score']);
        }
    }
}
