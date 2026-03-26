<?php

use PHPUnit\Framework\TestCase;
use Logingrupa\ColorClassifier\Classes\ColorConverter;

/**
 * Unit tests for ColorConverter.
 *
 * Covers all color space conversion requirements:
 *   CC-01 — rgbToHex produces uppercase #RRGGBB strings
 *   CC-02 — rgbToHsl produces correct hue, saturation, lightness values
 *   CC-03 — rgbToOklch produces values in valid OKLCH ranges
 *   CC-04 — hexToRgb parses both uppercase and lowercase hex strings
 *   CC-05 — Round-trip hex->rgb->hex is lossless for known values
 */
class ColorConverterTest extends TestCase
{
    /**
     * rgbToHex should return uppercase #RRGGBB for pure red.
     */
    public function test_rgbToHex_returns_uppercase_hex_for_pure_red(): void
    {
        $hexResult = ColorConverter::rgbToHex(255, 0, 0);

        $this->assertSame('#FF0000', $hexResult);
    }

    /**
     * rgbToHex should return #000000 for black.
     */
    public function test_rgbToHex_returns_black_hex_for_zero_rgb(): void
    {
        $hexResult = ColorConverter::rgbToHex(0, 0, 0);

        $this->assertSame('#000000', $hexResult);
    }

    /**
     * rgbToHex should return #FFFFFF for white.
     */
    public function test_rgbToHex_returns_white_hex_for_max_rgb(): void
    {
        $hexResult = ColorConverter::rgbToHex(255, 255, 255);

        $this->assertSame('#FFFFFF', $hexResult);
    }

    /**
     * rgbToHsl for pure red should return hue near 0°, saturation near 100%, lightness near 50%.
     */
    public function test_rgbToHsl_pure_red_has_expected_hsl_values(): void
    {
        $hslValues = ColorConverter::rgbToHsl(255, 0, 0);

        $this->assertEqualsWithDelta(0.0, $hslValues['hue'], 1.0);
        $this->assertEqualsWithDelta(100.0, $hslValues['saturation'], 1.0);
        $this->assertEqualsWithDelta(50.0, $hslValues['lightness'], 1.0);
    }

    /**
     * rgbToHsl for a medium gray should return saturation near 0%.
     */
    public function test_rgbToHsl_gray_has_near_zero_saturation(): void
    {
        $hslValues = ColorConverter::rgbToHsl(128, 128, 128);

        $this->assertEqualsWithDelta(0.0, $hslValues['saturation'], 1.0);
        $this->assertEqualsWithDelta(50.0, $hslValues['lightness'], 1.0);
    }

    /**
     * rgbToOklch should return lightness within [0, 1].
     */
    public function test_rgbToOklch_lightness_is_within_valid_range(): void
    {
        $oklchValues = ColorConverter::rgbToOklch(100, 150, 200);

        $this->assertGreaterThanOrEqual(0.0, $oklchValues['lightness']);
        $this->assertLessThanOrEqual(1.0, $oklchValues['lightness']);
    }

    /**
     * rgbToOklch should return chroma within [0, 0.4].
     */
    public function test_rgbToOklch_chroma_is_within_valid_range(): void
    {
        $oklchValues = ColorConverter::rgbToOklch(255, 0, 128);

        $this->assertGreaterThanOrEqual(0.0, $oklchValues['chroma']);
        $this->assertLessThanOrEqual(0.4, $oklchValues['chroma']);
    }

    /**
     * rgbToOklch should return hue within [0, 360].
     */
    public function test_rgbToOklch_hue_is_within_valid_range(): void
    {
        $oklchValues = ColorConverter::rgbToOklch(0, 200, 100);

        $this->assertGreaterThanOrEqual(0.0, $oklchValues['hue']);
        $this->assertLessThanOrEqual(360.0, $oklchValues['hue']);
    }

    /**
     * hexToRgb should parse uppercase #FF0000 correctly.
     */
    public function test_hexToRgb_parses_uppercase_hex_red(): void
    {
        $rgbValues = ColorConverter::hexToRgb('#FF0000');

        $this->assertSame(255, $rgbValues['red']);
        $this->assertSame(0, $rgbValues['green']);
        $this->assertSame(0, $rgbValues['blue']);
    }

    /**
     * hexToRgb should parse lowercase #ff0000 correctly.
     */
    public function test_hexToRgb_handles_lowercase_hex(): void
    {
        $rgbValues = ColorConverter::hexToRgb('#ff0000');

        $this->assertSame(255, $rgbValues['red']);
        $this->assertSame(0, $rgbValues['green']);
        $this->assertSame(0, $rgbValues['blue']);
    }

    /**
     * Round-trip conversion: rgbToHex(hexToRgb(hex)) should equal the original hex.
     */
    public function test_round_trip_hex_to_rgb_to_hex_is_lossless(): void
    {
        $originalHex = '#4A90D9';
        $rgbValues   = ColorConverter::hexToRgb($originalHex);
        $resultHex   = ColorConverter::rgbToHex($rgbValues['red'], $rgbValues['green'], $rgbValues['blue']);

        $this->assertSame($originalHex, $resultHex);
    }
}
