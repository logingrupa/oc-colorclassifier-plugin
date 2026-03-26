<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * ColorConverter — Pure PHP color space conversion utilities.
 *
 * Converts between RGB, HSL, OKLCH, and hex color representations.
 * All methods are static and use only native PHP math — no external
 * dependencies required.
 *
 * OKLCH conversion follows Bjorn Ottosson's OKLab specification:
 * https://bottosson.github.io/posts/oklab/
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class ColorConverter
{
    /**
     * Convert RGB integer values to a CSS hex color string.
     *
     * @param int $red   Red channel value (0–255).
     * @param int $green Green channel value (0–255).
     * @param int $blue  Blue channel value (0–255).
     *
     * @return string Uppercase hex string, e.g. '#FF0000'.
     */
    public static function rgbToHex(int $red, int $green, int $blue): string
    {
        return sprintf('#%02X%02X%02X', $red, $green, $blue);
    }

    /**
     * Convert RGB integer values to HSL (Hue, Saturation, Lightness).
     *
     * @param int $red   Red channel value (0–255).
     * @param int $green Green channel value (0–255).
     * @param int $blue  Blue channel value (0–255).
     *
     * @return array{hue: float, saturation: float, lightness: float}
     *   hue in [0, 360], saturation in [0, 100], lightness in [0, 100].
     */
    public static function rgbToHsl(int $red, int $green, int $blue): array
    {
        $redNormalized   = $red   / 255.0;
        $greenNormalized = $green / 255.0;
        $blueNormalized  = $blue  / 255.0;

        $maximumChannel = max($redNormalized, $greenNormalized, $blueNormalized);
        $minimumChannel = min($redNormalized, $greenNormalized, $blueNormalized);
        $channelRange   = $maximumChannel - $minimumChannel;

        $lightness = ($maximumChannel + $minimumChannel) / 2.0;

        if ($channelRange === 0.0) {
            return [
                'hue'        => 0.0,
                'saturation' => 0.0,
                'lightness'  => round($lightness * 100, 2),
            ];
        }

        $saturation = $channelRange / (1.0 - abs(2.0 * $lightness - 1.0));

        if ($maximumChannel === $redNormalized) {
            $hueSegment = fmod(($greenNormalized - $blueNormalized) / $channelRange, 6.0);
        } elseif ($maximumChannel === $greenNormalized) {
            $hueSegment = (($blueNormalized - $redNormalized) / $channelRange) + 2.0;
        } else {
            $hueSegment = (($redNormalized - $greenNormalized) / $channelRange) + 4.0;
        }

        $hue = $hueSegment * 60.0;

        if ($hue < 0.0) {
            $hue += 360.0;
        }

        return [
            'hue'        => round($hue, 2),
            'saturation' => round($saturation * 100, 2),
            'lightness'  => round($lightness * 100, 2),
        ];
    }

    /**
     * Convert RGB integer values to OKLCH (Oklab Lightness-Chroma-Hue).
     *
     * Pipeline: sRGB → linear RGB (gamma decode) → XYZ → OKLab → OKLCH.
     * Uses the standard sRGB-to-OKLab matrix from Bjorn Ottosson's specification.
     *
     * @param int $red   Red channel value (0–255).
     * @param int $green Green channel value (0–255).
     * @param int $blue  Blue channel value (0–255).
     *
     * @return array{lightness: float, chroma: float, hue: float}
     *   lightness in [0, 1], chroma in [0, 0.4], hue in [0, 360].
     */
    public static function rgbToOklch(int $red, int $green, int $blue): array
    {
        $linearRed   = self::srgbChannelToLinear($red   / 255.0);
        $linearGreen = self::srgbChannelToLinear($green / 255.0);
        $linearBlue  = self::srgbChannelToLinear($blue  / 255.0);

        // Linear sRGB → LMS via M1 matrix (Ottosson's specification)
        $lmsLongWavelength  = (0.4122214708 * $linearRed) + (0.5363325363 * $linearGreen) + (0.0514459929 * $linearBlue);
        $lmsMediumWavelength = (0.2119034982 * $linearRed) + (0.6806995451 * $linearGreen) + (0.1073969566 * $linearBlue);
        $lmsShortWavelength  = (0.0883024619 * $linearRed) + (0.2817188376 * $linearGreen) + (0.6299787005 * $linearBlue);

        // Cube-root non-linearity (PHP has no cbrt() — use sign-safe pow approach)
        $lmsCubeRootLong   = self::cubeRoot($lmsLongWavelength);
        $lmsCubeRootMedium = self::cubeRoot($lmsMediumWavelength);
        $lmsCubeRootShort  = self::cubeRoot($lmsShortWavelength);

        // LMS_cuberoot → OKLab via M2 matrix (Ottosson's specification)
        $oklabLightness  = (0.2104542553 * $lmsCubeRootLong) + (0.7936177850 * $lmsCubeRootMedium) - (0.0040720468 * $lmsCubeRootShort);
        $oklabGreenRed   = (1.9779984951 * $lmsCubeRootLong) - (2.4285922050 * $lmsCubeRootMedium) + (0.4505937099 * $lmsCubeRootShort);
        $oklabBlueYellow = (0.0259040371 * $lmsCubeRootLong) + (0.7827717662 * $lmsCubeRootMedium) - (0.8086757660 * $lmsCubeRootShort);

        // OKLab → OKLCH (polar conversion)
        $chroma = sqrt(($oklabGreenRed ** 2) + ($oklabBlueYellow ** 2));
        $hueRadians = atan2($oklabBlueYellow, $oklabGreenRed);
        $hue = rad2deg($hueRadians);

        if ($hue < 0.0) {
            $hue += 360.0;
        }

        return [
            'lightness' => round($oklabLightness, 4),
            'chroma'    => round($chroma, 4),
            'hue'       => round($hue, 2),
        ];
    }

    /**
     * Convert a CSS hex color string to RGB integer components.
     *
     * Accepts both uppercase and lowercase hex strings with a leading '#'.
     *
     * @param string $hexColor Hex color string, e.g. '#FF0000' or '#ff0000'.
     *
     * @return array{red: int, green: int, blue: int}
     */
    public static function hexToRgb(string $hexColor): array
    {
        $hexStripped = ltrim($hexColor, '#');

        return [
            'red'   => hexdec(substr($hexStripped, 0, 2)),
            'green' => hexdec(substr($hexStripped, 2, 2)),
            'blue'  => hexdec(substr($hexStripped, 4, 2)),
        ];
    }

    /**
     * Compute the real-valued cube root of a number, handling negative inputs.
     *
     * PHP does not have a built-in cbrt() function. pow() on negative values
     * returns NAN, so we apply the sign manually before raising to 1/3.
     *
     * @param float $value Input value (may be negative for LMS values near black).
     *
     * @return float Real cube root of the input value.
     */
    private static function cubeRoot(float $value): float
    {
        if ($value < 0.0) {
            return -((-$value) ** (1.0 / 3.0));
        }

        return $value ** (1.0 / 3.0);
    }

    /**
     * Apply sRGB gamma decode to a single normalized channel value.
     *
     * Converts a gamma-encoded sRGB channel (0.0–1.0) to linear light
     * for use in color space matrix operations.
     *
     * @param float $srgbChannel Gamma-encoded channel value in [0.0, 1.0].
     *
     * @return float Linear light channel value in [0.0, 1.0].
     */
    private static function srgbChannelToLinear(float $srgbChannel): float
    {
        if ($srgbChannel <= 0.04045) {
            return $srgbChannel / 12.92;
        }

        return (($srgbChannel + 0.055) / 1.055) ** 2.4;
    }
}
