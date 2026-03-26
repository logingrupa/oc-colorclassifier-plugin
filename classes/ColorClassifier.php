<?php

namespace Logingrupa\ColorClassifier\Classes;

use Logingrupa\ColorClassifier\Models\Settings;

/**
 * ColorClassifier — Taxonomy classification from RGB color values.
 *
 * Converts RGB colors to HSL and OKLCH via ColorConverter, then classifies
 * them into gel polish taxonomy dimensions: color family, undertone, depth,
 * saturation, finish (always null — cannot detect from image), and opacity
 * (defaults to Opaque).
 *
 * Classification thresholds are read from the Settings model at runtime.
 * Fallback constants are used when the database is unavailable (e.g. tests).
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class ColorClassifier
{
    /** @var float Fallback achromatic saturation threshold when Settings model is unavailable. */
    private const FALLBACK_ACHROMATIC_SATURATION_THRESHOLD = 5.0;

    /** @var float Saturation threshold for near-achromatic zone (not configurable — internal heuristic). */
    private const NEAR_ACHROMATIC_SATURATION_THRESHOLD = 15.0;

    /** @var float Fallback white lightness threshold when Settings model is unavailable. */
    private const FALLBACK_WHITE_LIGHTNESS_THRESHOLD = 90.0;

    /** @var float Fallback black lightness threshold when Settings model is unavailable. */
    private const FALLBACK_BLACK_LIGHTNESS_THRESHOLD = 10.0;

    /** @var float Fallback very light depth threshold when Settings model is unavailable. */
    private const FALLBACK_VERY_LIGHT_DEPTH_THRESHOLD = 80.0;

    /** @var float Fallback light depth threshold when Settings model is unavailable. */
    private const FALLBACK_LIGHT_DEPTH_THRESHOLD = 65.0;

    /** @var float Fallback medium depth threshold when Settings model is unavailable. */
    private const FALLBACK_MEDIUM_DEPTH_THRESHOLD = 40.0;

    /** @var float Fallback dark depth threshold when Settings model is unavailable. */
    private const FALLBACK_DARK_DEPTH_THRESHOLD = 20.0;

    /** @var float Fallback neon saturation threshold when Settings model is unavailable. */
    private const FALLBACK_NEON_SATURATION_THRESHOLD = 85.0;

    /** @var float Fallback vivid saturation threshold when Settings model is unavailable. */
    private const FALLBACK_VIVID_SATURATION_THRESHOLD = 65.0;

    /** @var float Fallback medium saturation threshold when Settings model is unavailable. */
    private const FALLBACK_MEDIUM_SATURATION_THRESHOLD = 40.0;

    /** @var float Fallback soft saturation threshold when Settings model is unavailable. */
    private const FALLBACK_SOFT_SATURATION_THRESHOLD = 20.0;

    /**
     * Retrieve a setting value from the Settings model with a safe fallback.
     *
     * Wraps Settings::get() in a try/catch so that classes running without a
     * database connection (e.g. standalone PHPUnit tests) receive the fallback
     * constant value instead of throwing an exception.
     *
     * @param string $sKey            Settings key to read.
     * @param mixed  $fallbackDefault Value to return when Settings is unavailable.
     *
     * @return mixed The stored setting value, or $fallbackDefault on error.
     */
    private static function getSettingValue(string $sKey, mixed $fallbackDefault): mixed
    {
        try {
            return Settings::get($sKey, $fallbackDefault);
        } catch (\Throwable $exception) {
            return $fallbackDefault;
        }
    }

    /**
     * Load all configurable classification thresholds from Settings in one call.
     *
     * Centralises threshold retrieval so each classify() invocation reads all
     * thresholds once and passes them through to the private classification methods.
     *
     * @return array<string, float> Map of threshold key to float value.
     */
    private static function loadThresholds(): array
    {
        return [
            'achromatic_saturation' => (float) self::getSettingValue('achromatic_saturation_threshold', self::FALLBACK_ACHROMATIC_SATURATION_THRESHOLD),
            'white_lightness'       => (float) self::getSettingValue('white_lightness_threshold',        self::FALLBACK_WHITE_LIGHTNESS_THRESHOLD),
            'black_lightness'       => (float) self::getSettingValue('black_lightness_threshold',        self::FALLBACK_BLACK_LIGHTNESS_THRESHOLD),
            'very_light_depth'      => (float) self::getSettingValue('very_light_depth_threshold',       self::FALLBACK_VERY_LIGHT_DEPTH_THRESHOLD),
            'light_depth'           => (float) self::getSettingValue('light_depth_threshold',            self::FALLBACK_LIGHT_DEPTH_THRESHOLD),
            'medium_depth'          => (float) self::getSettingValue('medium_depth_threshold',           self::FALLBACK_MEDIUM_DEPTH_THRESHOLD),
            'dark_depth'            => (float) self::getSettingValue('dark_depth_threshold',             self::FALLBACK_DARK_DEPTH_THRESHOLD),
            'neon_saturation'       => (float) self::getSettingValue('neon_saturation_threshold',        self::FALLBACK_NEON_SATURATION_THRESHOLD),
            'vivid_saturation'      => (float) self::getSettingValue('vivid_saturation_threshold',       self::FALLBACK_VIVID_SATURATION_THRESHOLD),
            'medium_saturation'     => (float) self::getSettingValue('medium_saturation_threshold',      self::FALLBACK_MEDIUM_SATURATION_THRESHOLD),
            'soft_saturation'       => (float) self::getSettingValue('soft_saturation_threshold',        self::FALLBACK_SOFT_SATURATION_THRESHOLD),
        ];
    }

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

        $oklchValues         = ColorConverter::rgbToOklch($red, $green, $blue);
        $perceptualLightness = $oklchValues['lightness'] * 100;

        $arThresholds = self::loadThresholds();

        return [
            'family'           => self::determineColorFamily($hue, $saturation, $lightness, $arThresholds),
            'undertone'        => self::determineUndertone($hue, $saturation),
            'depth'            => self::determineDepth($perceptualLightness, $arThresholds),
            'saturation'       => self::determineSaturation($saturation, $arThresholds),
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
     * @param float              $hue          Hue in [0, 360].
     * @param float              $saturation   Saturation in [0, 100].
     * @param float              $lightness    Lightness in [0, 100].
     * @param array<string,float> $arThresholds Loaded classification thresholds.
     *
     * @return string Color family name from Taxonomy::$colorFamilies.
     */
    public static function determineColorFamily(float $hue, float $saturation, float $lightness, array $arThresholds): string
    {
        // Pure achromatic: no color at all → White / Grey / Black
        if ($saturation < $arThresholds['achromatic_saturation']) {
            return self::classifyPureAchromatic($lightness, $arThresholds);
        }

        // Near-achromatic with warm hue: could be Nude/Beige (skin tones)
        if ($saturation < self::NEAR_ACHROMATIC_SATURATION_THRESHOLD) {
            return self::classifyNearAchromatic($hue, $lightness, $arThresholds);
        }

        return self::classifyChromatic($hue, $saturation, $lightness);
    }

    /**
     * Classify pure achromatic colors (saturation below achromatic threshold) — true greys.
     *
     * @param float              $lightness    Lightness in [0, 100].
     * @param array<string,float> $arThresholds Loaded classification thresholds.
     *
     * @return string One of: 'White', 'Grey', 'Black'.
     */
    private static function classifyPureAchromatic(float $lightness, array $arThresholds): string
    {
        if ($lightness > $arThresholds['white_lightness']) {
            return 'White';
        }

        if ($lightness < $arThresholds['black_lightness']) {
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
     * @param float              $hue          Hue in [0, 360].
     * @param float              $lightness    Lightness in [0, 100].
     * @param array<string,float> $arThresholds Loaded classification thresholds.
     *
     * @return string One of: 'White', 'Beige', 'Nude', 'Brown', 'Grey', 'Black'.
     */
    private static function classifyNearAchromatic(float $hue, float $lightness, array $arThresholds): string
    {
        if ($lightness > $arThresholds['white_lightness']) {
            return 'White';
        }

        if ($lightness < $arThresholds['black_lightness']) {
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
     * Determine depth (lightness level) from the perceptual lightness percentage.
     *
     * @param float              $lightness    Perceptual lightness in [0, 100].
     * @param array<string,float> $arThresholds Loaded classification thresholds.
     *
     * @return string One of: 'Very Light', 'Light', 'Medium', 'Dark', 'Very Dark'.
     */
    public static function determineDepth(float $lightness, array $arThresholds): string
    {
        if ($lightness > $arThresholds['very_light_depth']) {
            return 'Very Light';
        }

        if ($lightness > $arThresholds['light_depth']) {
            return 'Light';
        }

        if ($lightness > $arThresholds['medium_depth']) {
            return 'Medium';
        }

        if ($lightness > $arThresholds['dark_depth']) {
            return 'Dark';
        }

        return 'Very Dark';
    }

    /**
     * Determine saturation level from the saturation percentage.
     *
     * @param float              $saturationValue Saturation in [0, 100].
     * @param array<string,float> $arThresholds    Loaded classification thresholds.
     *
     * @return string One of: 'Muted', 'Soft', 'Medium', 'Vivid', 'Neon'.
     */
    public static function determineSaturation(float $saturationValue, array $arThresholds): string
    {
        if ($saturationValue >= $arThresholds['neon_saturation']) {
            return 'Neon';
        }

        if ($saturationValue >= $arThresholds['vivid_saturation']) {
            return 'Vivid';
        }

        if ($saturationValue >= $arThresholds['medium_saturation']) {
            return 'Medium';
        }

        if ($saturationValue >= $arThresholds['soft_saturation']) {
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

        $lightnessDeviation = abs($lightness - 50.0) / 50.0;
        $lightnessFactor    = 1.0 - ($lightnessDeviation * 0.5);

        $confidenceScore = $saturationNormalized * $lightnessFactor;

        return round(max(0.0, min(1.0, $confidenceScore)), 2);
    }
}
