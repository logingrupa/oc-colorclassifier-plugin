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

    /** @var float OKLCH chroma below which a color is strongly neutral (no chromatic identity). */
    private const CHROMA_ACHROMATIC_THRESHOLD = 0.04;

    /** @var float OKLCH chroma below which a color is low-moderate (nude zone for warm hues). */
    private const CHROMA_LOW_MODERATE_THRESHOLD = 0.08;

    /**
     * Classify an RGB color into all gel polish taxonomy dimensions.
     *
     * Uses OKLCH chroma as the primary gate for family classification:
     * - Chroma < 0.04: achromatic (Grey/White/Black, or Nude if warm hue)
     * - Chroma 0.04-0.08: low-moderate (Nude primary + chromatic secondary)
     * - Chroma > 0.08: full chromatic (Pink vs Rose split by lightness)
     *
     * @param int $red   Red channel value (0–255).
     * @param int $green Green channel value (0–255).
     * @param int $blue  Blue channel value (0–255).
     *
     * @return array{family: string, secondary_family: string|null, undertone: string, depth: string, saturation: string, finish: null, opacity: string, confidence_score: float}
     */
    public static function classify(int $red, int $green, int $blue): array
    {
        $hslValues  = ColorConverter::rgbToHsl($red, $green, $blue);
        $hue        = $hslValues['hue'];
        $saturation = $hslValues['saturation'];
        $lightness  = $hslValues['lightness'];

        $oklchValues         = ColorConverter::rgbToOklch($red, $green, $blue);
        $perceptualLightness = $oklchValues['lightness'] * 100;
        $oklchChroma         = $oklchValues['chroma'];

        $arThresholds = self::loadThresholds();

        $familyResult = self::determineFamilyWithSecondary($hue, $saturation, $lightness, $oklchChroma, $perceptualLightness, $arThresholds);

        return [
            'family'           => $familyResult['primary'],
            'secondary_family' => $familyResult['secondary'],
            'undertone'        => self::determineUndertone($hue, $saturation),
            'depth'            => self::determineDepth($perceptualLightness, $arThresholds),
            'saturation'       => self::determineSaturation($saturation, $arThresholds),
            'finish'           => null,
            'opacity'          => 'Opaque',
            'confidence_score' => self::calculateConfidence($saturation, $lightness),
        ];
    }

    /**
     * Determine primary and secondary color family using OKLCH chroma-first logic.
     *
     * Three zones based on OKLCH chroma:
     * 1. Chroma < 0.04: achromatic → Grey/White/Black (warm hue → Nude primary)
     * 2. Chroma 0.04-0.08: low-moderate → Nude primary + chromatic secondary
     * 3. Chroma > 0.08: fully chromatic → standard hue classification, Pink/Rose split
     *
     * @param float              $hue                 HSL hue in [0, 360].
     * @param float              $saturation          HSL saturation in [0, 100].
     * @param float              $lightness           HSL lightness in [0, 100].
     * @param float              $oklchChroma         OKLCH chroma (0–0.4+).
     * @param float              $perceptualLightness OKLCH lightness * 100.
     * @param array<string,float> $arThresholds        Loaded classification thresholds.
     *
     * @return array{primary: string, secondary: string|null}
     */
    private static function determineFamilyWithSecondary(
        float $hue,
        float $saturation,
        float $lightness,
        float $oklchChroma,
        float $perceptualLightness,
        array $arThresholds
    ): array {
        // Zone 1: Very low chroma — achromatic
        if ($oklchChroma < self::CHROMA_ACHROMATIC_THRESHOLD) {
            return self::classifyAchromaticZone($hue, $lightness, $perceptualLightness, $arThresholds);
        }

        // Zone 2: Low-moderate chroma — nude territory for warm hues
        if ($oklchChroma < self::CHROMA_LOW_MODERATE_THRESHOLD) {
            return self::classifyLowModerateChromaZone($hue, $saturation, $lightness, $perceptualLightness, $arThresholds);
        }

        // Zone 3: Full chroma — standard chromatic with Pink/Rose split
        $chromaticFamily = self::classifyChromatic($hue, $saturation, $lightness);

        return ['primary' => $chromaticFamily, 'secondary' => null];
    }

    /**
     * Classify achromatic zone (chroma < 0.04).
     *
     * Pure greys with no chromatic identity. Warm hues with very slight
     * tint get Nude as primary with no secondary.
     *
     * @param float              $hue                 HSL hue in [0, 360].
     * @param float              $lightness           HSL lightness in [0, 100].
     * @param float              $perceptualLightness OKLCH lightness * 100.
     * @param array<string,float> $arThresholds        Loaded thresholds.
     *
     * @return array{primary: string, secondary: string|null}
     */
    private static function classifyAchromaticZone(float $hue, float $lightness, float $perceptualLightness, array $arThresholds): array
    {
        if ($perceptualLightness > $arThresholds['white_lightness']) {
            return ['primary' => 'White', 'secondary' => null];
        }

        if ($perceptualLightness < $arThresholds['black_lightness']) {
            return ['primary' => 'Black', 'secondary' => null];
        }

        $isWarmHue = ($hue <= 60) || ($hue >= 330);

        if ($isWarmHue && $lightness > 40 && $lightness < 80) {
            return ['primary' => 'Nude', 'secondary' => null];
        }

        return ['primary' => 'Grey', 'secondary' => null];
    }

    /**
     * Classify low-moderate chroma zone (0.04 – 0.08).
     *
     * This is the skin-tone/nude territory. Warm hues become Nude with
     * a chromatic secondary family. Cool hues become Grey with a chromatic secondary.
     *
     * @param float              $hue                 HSL hue in [0, 360].
     * @param float              $saturation          HSL saturation in [0, 100].
     * @param float              $lightness           HSL lightness in [0, 100].
     * @param float              $perceptualLightness OKLCH lightness * 100.
     * @param array<string,float> $arThresholds        Loaded thresholds.
     *
     * @return array{primary: string, secondary: string|null}
     */
    private static function classifyLowModerateChromaZone(
        float $hue,
        float $saturation,
        float $lightness,
        float $perceptualLightness,
        array $arThresholds
    ): array {
        if ($perceptualLightness > $arThresholds['white_lightness']) {
            return ['primary' => 'White', 'secondary' => null];
        }

        if ($perceptualLightness < $arThresholds['black_lightness']) {
            return ['primary' => 'Black', 'secondary' => null];
        }

        $chromaticFamily = self::classifyChromatic($hue, $saturation, $lightness);

        // Skin-adjacent hues: pink/red zone, peach/coral zone, beige/brown zone
        $isSkinAdjacentHue = ($hue <= 60) || ($hue >= 310);

        if ($isSkinAdjacentHue) {
            // Determine specific nude type based on the chromatic family
            if (in_array($chromaticFamily, ['Pink', 'Rose', 'Red', 'Magenta'])) {
                return ['primary' => 'Nude', 'secondary' => 'Pink'];
            }

            if (in_array($chromaticFamily, ['Peach', 'Coral', 'Orange'])) {
                return ['primary' => 'Nude', 'secondary' => 'Peach'];
            }

            if (in_array($chromaticFamily, ['Brown', 'Gold', 'Yellow'])) {
                return ['primary' => 'Nude', 'secondary' => 'Beige'];
            }

            return ['primary' => 'Nude', 'secondary' => $chromaticFamily];
        }

        // Cool/neutral hues at low chroma: Grey with chromatic hint
        return ['primary' => 'Grey', 'secondary' => $chromaticFamily];
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
