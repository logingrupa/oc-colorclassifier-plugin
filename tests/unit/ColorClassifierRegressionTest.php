<?php

use PHPUnit\Framework\TestCase;
use Logingrupa\ColorClassifier\Classes\ColorClassifier;
use Logingrupa\ColorClassifier\Classes\ColorConverter;

/**
 * Regression test for the #A5BACD (Light Steel Blue, "Lumi Sky") family bug.
 *
 * The OKLCH chroma of this color (0.0357) sits below the hardcoded achromatic
 * gate (0.04) even though its HSL saturation (28.57%) is far above the
 * configured Achromatic Saturation Threshold (5%). The family gate must
 * consult the saturation threshold so this color classifies as Blue, not Grey.
 */
class ColorClassifierRegressionTest extends TestCase
{
    private const LUMI_SKY_RED   = 165;
    private const LUMI_SKY_GREEN = 186;
    private const LUMI_SKY_BLUE  = 205;

    public function test_a5bacd_intermediate_conversions_match_expected_values(): void
    {
        $hslValues = ColorConverter::rgbToHsl(self::LUMI_SKY_RED, self::LUMI_SKY_GREEN, self::LUMI_SKY_BLUE);

        $this->assertEqualsWithDelta(208.5, $hslValues['hue'], 0.5);
        $this->assertEqualsWithDelta(28.57, $hslValues['saturation'], 0.5);
        $this->assertEqualsWithDelta(72.55, $hslValues['lightness'], 0.5);

        $oklchValues = ColorConverter::rgbToOklch(self::LUMI_SKY_RED, self::LUMI_SKY_GREEN, self::LUMI_SKY_BLUE);

        $this->assertEqualsWithDelta(0.779, $oklchValues['lightness'], 0.005);
        $this->assertEqualsWithDelta(0.036, $oklchValues['chroma'], 0.005);
        $this->assertEqualsWithDelta(245.6, $oklchValues['hue'], 1.0);
    }

    public function test_a5bacd_classifies_as_blue_not_grey(): void
    {
        $classification = ColorClassifier::classify(self::LUMI_SKY_RED, self::LUMI_SKY_GREEN, self::LUMI_SKY_BLUE);

        $this->assertSame('Blue', $classification['family'], 'Saturation 28.57% is above the 5% achromatic threshold - must not be Grey');
        $this->assertNull($classification['secondary_family']);
        $this->assertSame('Cool', $classification['undertone']);
        $this->assertSame('Light', $classification['depth']);
        $this->assertSame('Soft', $classification['saturation']);
    }
}
