<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * EffectiveColorResolver - the one place that merges classifier output with
 * manual overrides.
 *
 * Every consumer (offers.json, Color Lab, backend list, family board) reads
 * the effective family, hex, OKLCH and confidence from here. The classifier
 * columns are never rewritten by an override, so "classifier said X, admin
 * said Y" stays visible through the auto_* keys.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class EffectiveColorResolver
{
    /** @var float Effective confidence below this marks an offer for review. */
    public const LOW_CONFIDENCE_THRESHOLD = 0.55;

    /** @var float Confidence reported for an offer whose family was set by hand. */
    private const MANUAL_FAMILY_CONFIDENCE = 1.0;

    /**
     * Resolve the effective values of a ColorEntry-like row.
     *
     * A hex override that is not #rrggbb and empty strings count as absent:
     * the write path validates, and a bad stored value must never break an export.
     *
     * @param object $obEntry ColorEntry or a plain object with the same columns.
     *
     * @return array{family: string|null, auto_family: string|null, family_overridden: bool, hex: string|null, auto_hex: string|null, hex_overridden: bool, lightness: float|int|string|null, chroma: float|int|string|null, hue: float|int|string|null, confidence: float|null, auto_confidence: float|null, manual: bool}
     */
    public static function resolve(object $obEntry): array
    {
        $sFamilyOverride = self::readNonEmptyString($obEntry->family_override ?? null);
        $sHexOverride    = self::readHexOverride($obEntry->hex_override ?? null);
        $arTaxonomy      = $obEntry->taxonomy ?? null;
        $sAutoFamily     = is_array($arTaxonomy) ? self::readNonEmptyString($arTaxonomy['family'] ?? null) : null;
        $sAutoHex        = self::readNonEmptyString($obEntry->hex_color ?? null);
        $flAutoConfidence = self::readConfidence($obEntry->confidence_score ?? null);
        $arOklch         = self::resolveOklch($sHexOverride, $obEntry->oklch_values ?? null);

        return [
            'family'            => $sFamilyOverride ?? $sAutoFamily,
            'auto_family'       => $sAutoFamily,
            'family_overridden' => $sFamilyOverride !== null,
            'hex'               => $sHexOverride ?? $sAutoHex,
            'auto_hex'          => $sAutoHex,
            'hex_overridden'    => $sHexOverride !== null,
            'lightness'         => $arOklch['lightness'] ?? null,
            'chroma'            => $arOklch['chroma'] ?? null,
            'hue'               => $arOklch['hue'] ?? null,
            'confidence'        => $sFamilyOverride !== null ? self::MANUAL_FAMILY_CONFIDENCE : $flAutoConfidence,
            'auto_confidence'   => $flAutoConfidence,
            'manual'            => $sFamilyOverride !== null || $sHexOverride !== null,
        ];
    }

    /**
     * OKLCH of the hex override when set, else the stored classifier values.
     *
     * @param string|null $sHexOverride Valid #rrggbb override or null.
     * @param mixed $mStoredOklch Stored oklch_values column.
     *
     * @return array<string, mixed>
     */
    private static function resolveOklch(?string $sHexOverride, mixed $mStoredOklch): array
    {
        if ($sHexOverride !== null) {
            $arRgb = ColorConverter::hexToRgb($sHexOverride);

            return ColorConverter::rgbToOklch($arRgb['red'], $arRgb['green'], $arRgb['blue']);
        }

        return is_array($mStoredOklch) ? $mStoredOklch : [];
    }

    /**
     * @param mixed $mValue Raw column value.
     *
     * @return string|null The value when it is a non-empty string, else null.
     */
    private static function readNonEmptyString(mixed $mValue): ?string
    {
        return is_string($mValue) && $mValue !== '' ? $mValue : null;
    }

    /**
     * @param mixed $mValue Raw hex_override column value.
     *
     * @return string|null The value when it is #rrggbb, else null.
     */
    private static function readHexOverride(mixed $mValue): ?string
    {
        if (is_string($mValue) && preg_match('/^#[0-9a-fA-F]{6}$/', $mValue) === 1) {
            return $mValue;
        }

        return null;
    }

    /**
     * Eloquent's decimal cast hands over strings.
     *
     * @param mixed $mValue Raw confidence_score column value.
     *
     * @return float|null
     */
    private static function readConfidence(mixed $mValue): ?float
    {
        return is_numeric($mValue) ? (float) $mValue : null;
    }
}
