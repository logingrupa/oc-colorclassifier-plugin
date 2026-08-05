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

    /**
     * Classify a hex color and return the family name.
     *
     * @param string $hexColor Hex color string.
     *
     * @return string Assigned primary family.
     */
    private function familyOf(string $hexColor): string
    {
        $rgbValues = ColorConverter::hexToRgb($hexColor);

        return ColorClassifier::classify($rgbValues['red'], $rgbValues['green'], $rgbValues['blue'])['family'];
    }

    public function test_rose_water_pale_pink_classifies_as_pink_not_white(): void
    {
        // #F7D8E8 "Rose Water": perceptual lightness 91.25 is above the 90%
        // white threshold, but chroma 0.0401 is a clearly visible pink tint.
        $this->assertSame('Pink', $this->familyOf('#F7D8E8'));
    }

    public function test_pale_lavender_classifies_as_violet_zone_not_white(): void
    {
        // #E6E0F8: chroma 0.0329, visibly lavender despite lightness 91.86.
        $this->assertContains($this->familyOf('#E6E0F8'), ['Indigo', 'Violet', 'Purple']);
    }

    public function test_genuine_whites_still_classify_as_white(): void
    {
        // All measure chroma below 0.02 - real whites with a faint cast.
        $this->assertSame('White', $this->familyOf('#F5F5F5'), 'neutral near-white');
        $this->assertSame('White', $this->familyOf('#FFFFF0'), 'ivory');
        $this->assertSame('White', $this->familyOf('#FAF0E6'), 'linen');
        $this->assertSame('White', $this->familyOf('#FFFAFA'), 'snow');
    }
}
