<?php

use PHPUnit\Framework\TestCase;
use Logingrupa\ColorClassifier\Classes\ColorNamer;

/**
 * Unit tests for ColorNamer.
 *
 * Covers color name lookup requirements:
 *   CC-11 — findNearestColorName returns a name containing expected color word for known colors
 *   CC-12 — findNearestColorName returns 'Black' for #000000
 *   CC-13 — findNearestColorName returns 'White' for #FFFFFF
 *   CC-14 — Result is always a non-empty string
 */
class ColorNamerTest extends TestCase
{
    /**
     * findNearestColorName for pure red should return a name containing 'Red'.
     */
    public function test_findNearestColorName_pure_red_returns_name_containing_red(): void
    {
        $colorName = ColorNamer::findNearestColorName('#FF0000');

        $this->assertStringContainsStringIgnoringCase('red', $colorName);
    }

    /**
     * findNearestColorName for pure blue should return a name containing 'Blue'.
     */
    public function test_findNearestColorName_pure_blue_returns_name_containing_blue(): void
    {
        $colorName = ColorNamer::findNearestColorName('#0000FF');

        $this->assertStringContainsStringIgnoringCase('blue', $colorName);
    }

    /**
     * findNearestColorName for black should return 'Black'.
     */
    public function test_findNearestColorName_black_returns_black(): void
    {
        $colorName = ColorNamer::findNearestColorName('#000000');

        $this->assertSame('Black', $colorName);
    }

    /**
     * findNearestColorName for white should return 'White'.
     */
    public function test_findNearestColorName_white_returns_white(): void
    {
        $colorName = ColorNamer::findNearestColorName('#FFFFFF');

        $this->assertSame('White', $colorName);
    }

    /**
     * findNearestColorName result is always a non-empty string.
     */
    public function test_findNearestColorName_always_returns_non_empty_string(): void
    {
        $hexColors = ['#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#FF00FF', '#808080'];

        foreach ($hexColors as $hexColor) {
            $colorName = ColorNamer::findNearestColorName($hexColor);

            $this->assertIsString($colorName);
            $this->assertNotEmpty($colorName);
        }
    }
}
