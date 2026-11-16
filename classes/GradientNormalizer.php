<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * GradientNormalizer - pure rules for the optional 2 or 3 stop family gradient.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class GradientNormalizer
{
    private const MIN_STOP_COUNT = 2;

    private const MAX_STOP_COUNT = 3;

    private const STOP_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    /**
     * @param mixed $mGradient Null, an empty list or a list of #rrggbb strings.
     *
     * @return array<int, string>|null Lowercase stops, null when no gradient is set.
     *
     * @throws \InvalidArgumentException When the value is not a list of 2 or 3 strict hex stops.
     */
    public function normalize(mixed $mGradient): ?array
    {
        if ($mGradient === null || $mGradient === []) {
            return null;
        }

        if (!is_array($mGradient) || !array_is_list($mGradient)) {
            throw new \InvalidArgumentException('A gradient is a list of color stops');
        }

        if (count($mGradient) < self::MIN_STOP_COUNT || count($mGradient) > self::MAX_STOP_COUNT) {
            throw new \InvalidArgumentException('A gradient needs 2 or 3 color stops, got ' . count($mGradient));
        }

        foreach ($mGradient as $mStop) {
            if (!is_string($mStop) || preg_match(self::STOP_PATTERN, $mStop) !== 1) {
                throw new \InvalidArgumentException('Every gradient stop must look like #d32f2f');
            }
        }

        return array_map('strtolower', $mGradient);
    }
}
