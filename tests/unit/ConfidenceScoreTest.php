<?php

use PHPUnit\Framework\TestCase;
use Logingrupa\ColorClassifier\Classes\ColorClassifier;
use Logingrupa\ColorClassifier\Classes\ColorConverter;

/**
 * Tests for the family-certainty confidence score.
 *
 * Confidence means "how certain is the assigned family label":
 * - hue margin from the nearest family boundary
 * - saturation clearly achromatic OR clearly chromatic (ambiguity band scores low)
 * - mild penalty near white/black where hue readings are noisy
 */
class ConfidenceScoreTest extends TestCase
{
    /** @var array<string, float> Fallback thresholds mirroring the Settings defaults. */
    private const DEFAULT_THRESHOLDS = ['achromatic_saturation' => 5.0];

    /**
     * Classify a hex color and return the full result array.
     *
     * @param string $hexColor Hex color string, e.g. '#A5BACD'.
     *
     * @return array{family: string, confidence_score: float}
     */
    private function classifyHex(string $hexColor): array
    {
        $rgbValues = ColorConverter::hexToRgb($hexColor);

        return ColorClassifier::classify($rgbValues['red'], $rgbValues['green'], $rgbValues['blue']);
    }

    public function test_pure_grey_scores_high_confidence(): void
    {
        $classification = $this->classifyHex('#808080');

        $this->assertSame('Grey', $classification['family']);
        $this->assertGreaterThanOrEqual(0.9, $classification['confidence_score']);
    }

    public function test_vivid_mid_band_red_scores_high_confidence(): void
    {
        $classification = $this->classifyHex('#FF0000');

        $this->assertSame('Red', $classification['family']);
        $this->assertGreaterThanOrEqual(0.9, $classification['confidence_score']);
    }

    public function test_lumi_sky_scores_moderate_because_hue_is_near_cyan_boundary(): void
    {
        $classification = $this->classifyHex('#A5BACD');

        $this->assertSame('Blue', $classification['family']);
        $this->assertGreaterThan(0.25, $classification['confidence_score']);
        $this->assertLessThan(0.55, $classification['confidence_score']);
    }

    public function test_boundary_adjacent_cyan_scores_lower_than_solid_band_blue(): void
    {
        // #AED2CC hue ~170 sits 5 degrees from the Green/Cyan boundary at 165.
        // #B7C6DF hue ~217 sits 17.5 degrees inside the Blue band.
        $boundaryAdjacentCyan = $this->classifyHex('#AED2CC');
        $solidBandBlue        = $this->classifyHex('#B7C6DF');

        $this->assertSame('Cyan', $boundaryAdjacentCyan['family']);
        $this->assertSame('Blue', $solidBandBlue['family']);
        $this->assertLessThan(
            $solidBandBlue['confidence_score'],
            $boundaryAdjacentCyan['confidence_score'],
            'A color near a family hue boundary must score lower than one deep inside a band'
        );
    }

    public function test_grey_with_hint_ambiguity_band_scores_low(): void
    {
        // Saturation 10% sits between the 5% achromatic threshold and the 15%
        // near-achromatic constant: the genuinely ambiguous Grey-with-hint zone.
        $confidenceScore = ColorClassifier::calculateConfidence(220.0, 10.0, 50.0, self::DEFAULT_THRESHOLDS);

        $this->assertLessThanOrEqual(0.5, $confidenceScore);
    }

    public function test_confidence_stays_within_valid_range_across_color_sweep(): void
    {
        foreach ([0, 64, 128, 192, 255] as $red) {
            foreach ([0, 64, 128, 192, 255] as $green) {
                foreach ([0, 64, 128, 192, 255] as $blue) {
                    $classification = ColorClassifier::classify($red, $green, $blue);

                    $this->assertGreaterThanOrEqual(0.0, $classification['confidence_score']);
                    $this->assertLessThanOrEqual(1.0, $classification['confidence_score']);
                }
            }
        }
    }

    public function test_out_of_range_hue_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ColorClassifier::calculateConfidence(400.0, 50.0, 50.0, self::DEFAULT_THRESHOLDS);
    }

    public function test_out_of_range_saturation_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ColorClassifier::calculateConfidence(200.0, 150.0, 50.0, self::DEFAULT_THRESHOLDS);
    }
}
