<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * FamilySwatch - the CSS background a family swatch draws in the backend:
 * the gradient when it is valid, the flat hex otherwise. nc shops build the
 * same CSS from the exported gradient.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class FamilySwatch
{
    /**
     * @param string $sHex Fallback #rrggbb.
     * @param mixed $mStops Stored or posted gradient stops.
     * @param mixed $mType Stored or posted gradient type.
     * @param mixed $mAngle Stored or posted gradient angle.
     *
     * @return string A CSS background value.
     */
    public function background(string $sHex, mixed $mStops, mixed $mType, mixed $mAngle): string
    {
        try {
            $arGradient = (new GradientNormalizer())->normalize($mStops, $mType, $mAngle);
        } catch (\InvalidArgumentException) {
            // A form mid-edit or a row the export would refuse still gets a dot; saving validates.
            return $sHex;
        }

        return $arGradient === null ? $sHex : $this->css($arGradient);
    }

    /**
     * @param array{type: string, angle: int, stops: array<int, string>} $arGradient A normalized gradient.
     *
     * @return string
     */
    private function css(array $arGradient): string
    {
        $sStops = implode(', ', $arGradient['stops']);

        return match ($arGradient['type']) {
            'radial' => 'radial-gradient(circle, ' . $sStops . ')',
            'conic'  => 'conic-gradient(from ' . $arGradient['angle'] . 'deg, ' . $sStops . ')',
            default  => 'linear-gradient(' . $arGradient['angle'] . 'deg, ' . $sStops . ')',
        };
    }
}
