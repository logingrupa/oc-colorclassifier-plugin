<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * ColorClassifier — Taxonomy classification from RGB color values.
 *
 * Converts RGB colors to HSL and OKLCH via ColorConverter, then classifies
 * them into gel polish taxonomy dimensions: color family, undertone, depth,
 * saturation, finish (always null — cannot detect from image), and opacity
 * (defaults to Opaque).
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class ColorClassifier
{
    /** @var float Saturation threshold below which a color is pure achromatic (grey/white/black). */
    private const ACHROMATIC_SATURATION_THRESHOLD = 5.0;

    /** @var float Saturation threshold for near-achromatic zone (could be nude/beige if warm-hued). */
    private const NEAR_ACHROMATIC_SATURATION_THRESHOLD = 15.0;

    /** @var float Lightness threshold above which an achromatic color is White. */
    private const WHITE_LIGHTNESS_THRESHOLD = 90.0;

    /** @var float Lightness threshold below which an achromatic color is Black. */
    private const BLACK_LIGHTNESS_THRESHOLD = 10.0;

    /** @var float Lightness threshold above which depth is Very Light. */
    private const VERY_LIGHT_DEPTH_THRESHOLD = 80.0;

    /** @var float Lightness threshold above which depth is Light. */
    private const LIGHT_DEPTH_THRESHOLD = 65.0;

    /** @var float Lightness threshold above which depth is Medium. */
    private const MEDIUM_DEPTH_THRESHOLD = 40.0;

    /** @var float Lightness threshold above which depth is Dark. */
    private const DARK_DEPTH_THRESHOLD = 20.0;

    /** @var float Saturation threshold above which a color is Neon. */
    private const NEON_SATURATION_THRESHOLD = 85.0;

    /** @var float Saturation threshold above which a color is Vivid. */
    private const VIVID_SATURATION_THRESHOLD = 65.0;

    /** @var float Saturation threshold above which a color is Medium saturation. */
    private const MEDIUM_SATURATION_THRESHOLD = 40.0;

    /** @var float Saturation threshold above which a color is Soft. */
    private const SOFT_SATURATION_THRESHOLD = 20.0;

    /**
     * Classify an RGB color into all gel polish taxonomy dimensions.
     *
     * @param int $red   Red channel value (0–255).
     * @param int $green Green channel value (0–255).
     * @param int $blue  Blue channel value (0–255).
     *
     * @return array{family: string, undertone: string, depth: string, saturation: string, finish: null, opacity: string, confidence_score: float}
     */
    public static function classify(int $red, int $green, int $blue): array
    {
        $hslValues  = ColorConverter::rgbToHsl($red, $green, $blue);
        $hue        = $hslValues['hue'];
        $saturation = $hslValues['saturation'];
        $lightness  = $hslValues['lightness'];

        $oklchValues          = ColorConverter::rgbToOklch($red, $green, $blue);
        $perceptualLightness  = $oklchValues['lightness'] * 100;

        return [
            'family'           => self::determineColorFamily($hue, $saturation, $lightness),
            'undertone'        => self::determineUndertone($hue, $saturation),
            'depth'            => self::determineDepth($perceptualLightness),
            'saturation'       => self::determineSaturation($saturation),
            'finish'           => null,
            'opacity'          => 'Opaque',
            'confidence_score' => self::calculateConfidence($saturation, $lightness),
        ];
    }

    /**
     * Determine the color family from HSL hue, saturation, and lightness.
     *
     * For achromatic colors (very low saturation), classifies by lightness only.
     * For chromatic colors, maps hue ranges to the 22 color families.
     *
     * @param float $hue        Hue in [0, 360].
     * @param float $saturation Saturation in [0, 100].
     * @param float $lightness  Lightness in [0, 100].
     *
     * @return string Color family name from Taxonomy::$colorFamilies.
     */
    public static function determineColorFamily(float $hue, float $saturation, float $lightness): string
    {
        // Pure achromatic: no color at all → White / Grey / Black
        if ($saturation < self::ACHROMATIC_SATURATION_THRESHOLD) {
            return self::classifyPureAchromatic($lightness);
        }

        // Near-achromatic with warm hue: could be Nude/Beige (skin tones)
        if ($saturation < self::NEAR_ACHROMATIC_SATURATION_THRESHOLD) {
            return self::classifyNearAchromatic($hue, $lightness);
        }

        return self::classifyChromatic($hue, $saturation, $lightness);
    }

    /**
     * Classify pure achromatic colors (saturation < 5%) — true greys.
     *
     * @param float $lightness Lightness in [0, 100].
     *
     * @return string One of: 'White', 'Grey', 'Black'.
     */
    private static function classifyPureAchromatic(float $lightness): string
    {
        if ($lightness > self::WHITE_LIGHTNESS_THRESHOLD) {
            return 'White';
        }

        if ($lightness < self::BLACK_LIGHTNESS_THRESHOLD) {
            return 'Black';
        }

        return 'Grey';
    }

    /**
     * Classify near-achromatic colors (saturation 5-15%).
     *
     * Warm hues (0-60° and 330-360°) at this low saturation are skin-tone
     * colors: Nude (medium lightness) or Beige (light). Cool or neutral
     * hues remain Grey.
     *
     * @param float $hue       Hue in [0, 360].
     * @param float $lightness Lightness in [0, 100].
     *
     * @return string One of: 'White', 'Beige', 'Nude', 'Brown', 'Grey', 'Black'.
     */
    private static function classifyNearAchromatic(float $hue, float $lightness): string
    {
        if ($lightness > self::WHITE_LIGHTNESS_THRESHOLD) {
            return 'White';
        }

        if ($lightness < self::BLACK_LIGHTNESS_THRESHOLD) {
            return 'Black';
        }

        $isWarmHue = ($hue <= 60) || ($hue >= 330);

        if (!$isWarmHue) {
            return 'Grey';
        }

        if ($lightness > 70) {
            return 'Beige';
        }

        if ($lightness > 40) {
            return 'Nude';
        }

        return 'Brown';
    }

    /**
     * Classify chromatic colors by hue range into one of the 22 color families.
     *
     * @param float $hue        Hue in [0, 360].
     * @param float $saturation Saturation in [0, 100].
     * @param float $lightness  Lightness in [0, 100].
     *
     * @return string Color family name.
     */
    private static function classifyChromatic(float $hue, float $saturation, float $lightness): string
    {
        // Red: 0–15 and 345–360 (very light variants become Pink)
        if ($hue <= 15 || $hue > 345) {
            if ($lightness > 75) {
                return 'Pink';
            }
            return 'Red';
        }

        // Orange / Coral / Peach: 15–45
        if ($hue <= 45) {
            if ($lightness > 75) {
                return 'Peach';
            }
            if ($lightness > 55) {
                return 'Coral';
            }
            return 'Orange';
        }

        // Yellow / Gold: 45–65
        if ($hue <= 65) {
            if ($saturation < 60) {
                return 'Gold';
            }
            return 'Yellow';
        }

        // Green / Olive: 65–165
        if ($hue <= 165) {
            if ($hue >= 150) {
                return 'Teal';
            }
            return 'Green';
        }

        // Cyan / Teal: 165–200
        if ($hue <= 200) {
            return 'Cyan';
        }

        // Blue / Navy: 200–250
        if ($hue <= 250) {
            if ($lightness < 30) {
                return 'Navy';
            }
            return 'Blue';
        }

        // Indigo: 250–270
        if ($hue <= 270) {
            return 'Indigo';
        }

        // Violet / Purple: 270–295
        if ($hue <= 295) {
            if ($lightness < 35) {
                return 'Purple';
            }
            return 'Violet';
        }

        // Magenta / Pink / Rose: 295–345
        if ($hue <= 315) {
            if ($lightness > 70) {
                return 'Pink';
            }
            return 'Magenta';
        }

        if ($hue <= 330) {
            if ($lightness > 65) {
                return 'Pink';
            }
            return 'Rose';
        }

        // 330–345
        if ($lightness > 75) {
            return 'Pink';
        }

        return 'Rose';
    }

    /**
     * Determine the color undertone (warm, cool, or neutral) from HSL values.
     *
     * Warm: reds, oranges, yellows (hue 0–60 or 300–360).
     * Cool: blues, greens (hue 170–280).
     * Neutral: all other hues, or low-saturation colors.
     *
     * @param float $hue        Hue in [0, 360].
     * @param float $saturation Saturation in [0, 100].
     *
     * @return string One of: 'Warm', 'Cool', 'Neutral'.
     */
    public static function determineUndertone(float $hue, float $saturation): string
    {
        if ($saturation < 15) {
            return 'Neutral';
        }

        if ($hue <= 60 || $hue >= 300) {
            return 'Warm';
        }

        if ($hue >= 170 && $hue <= 280) {
            return 'Cool';
        }

        return 'Neutral';
    }

    /**
     * Determine depth (lightness level) from the lightness percentage.
     *
     * @param float $lightness Lightness in [0, 100].
     *
     * @return string One of: 'Very Light', 'Light', 'Medium', 'Dark', 'Very Dark'.
     */
    public static function determineDepth(float $lightness): string
    {
        if ($lightness > self::VERY_LIGHT_DEPTH_THRESHOLD) {
            return 'Very Light';
        }

        if ($lightness > self::LIGHT_DEPTH_THRESHOLD) {
            return 'Light';
        }

        if ($lightness > self::MEDIUM_DEPTH_THRESHOLD) {
            return 'Medium';
        }

        if ($lightness > self::DARK_DEPTH_THRESHOLD) {
            return 'Dark';
        }

        return 'Very Dark';
    }

    /**
     * Determine saturation level from the saturation percentage.
     *
     * @param float $saturationValue Saturation in [0, 100].
     *
     * @return string One of: 'Muted', 'Soft', 'Medium', 'Vivid', 'Neon'.
     */
    public static function determineSaturation(float $saturationValue): string
    {
        if ($saturationValue >= self::NEON_SATURATION_THRESHOLD) {
            return 'Neon';
        }

        if ($saturationValue >= self::VIVID_SATURATION_THRESHOLD) {
            return 'Vivid';
        }

        if ($saturationValue >= self::MEDIUM_SATURATION_THRESHOLD) {
            return 'Medium';
        }

        if ($saturationValue >= self::SOFT_SATURATION_THRESHOLD) {
            return 'Soft';
        }

        return 'Muted';
    }

    /**
     * Calculate a classification confidence score from saturation and lightness.
     *
     * Well-saturated, mid-lightness colors produce high confidence.
     * Near-black, near-white, and near-gray colors produce low confidence
     * because achromatic hues are ambiguous.
     *
     * @param float $saturation Saturation in [0, 100].
     * @param float $lightness  Lightness in [0, 100].
     *
     * @return float Confidence score in [0.00, 1.00].
     */
    public static function calculateConfidence(float $saturation, float $lightness): float
    {
        $saturationNormalized = $saturation / 100.0;

        $lightnessDeviation   = abs($lightness - 50.0) / 50.0;
        $lightnessFactor      = 1.0 - ($lightnessDeviation * 0.5);

        $confidenceScore = $saturationNormalized * $lightnessFactor;

        return round(max(0.0, min(1.0, $confidenceScore)), 2);
    }
}
