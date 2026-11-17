<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * GradientNormalizer - pure rules for the optional family gradient: 2 to 8
 * color stops, a type and an angle in whole degrees.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class GradientNormalizer
{
    /** @var array<int, string> Gradient types, as stored and exported. */
    public const TYPES = ['linear', 'radial', 'conic'];

    private const MIN_STOP_COUNT = 2;

    private const MAX_STOP_COUNT = 8;

    private const MAX_ANGLE = 359;

    private const STOP_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    private const ANGLE_PATTERN = '/^\d{1,3}$/';

    /**
     * @param mixed $mStops Null, an empty list or a list of #rrggbb strings.
     * @param mixed $mType linear, radial or conic.
     * @param mixed $mAngle Whole degrees 0-359 as an int or a digit string. Radial ignores it.
     *
     * @return array{type: string, angle: int, stops: array<int, string>}|null Lowercase stops, null when no stops are set.
     *
     * @throws \InvalidArgumentException When stops are set and the stops, type or angle break the rules.
     */
    public function normalize(mixed $mStops, mixed $mType, mixed $mAngle): ?array
    {
        if ($mStops === null || $mStops === []) {
            return null;
        }

        return [
            'type'  => $this->normalizeType($mType),
            'angle' => $this->normalizeAngle($mAngle),
            'stops' => $this->normalizeStops($mStops),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStops(mixed $mStops): array
    {
        if (!is_array($mStops) || !array_is_list($mStops)) {
            throw new \InvalidArgumentException('A gradient is a list of color stops');
        }

        if (count($mStops) < self::MIN_STOP_COUNT || count($mStops) > self::MAX_STOP_COUNT) {
            throw new \InvalidArgumentException('A gradient needs 2 to 8 color stops, got ' . count($mStops));
        }

        foreach ($mStops as $mStop) {
            if (!is_string($mStop) || preg_match(self::STOP_PATTERN, $mStop) !== 1) {
                throw new \InvalidArgumentException('Every gradient stop must look like #d32f2f');
            }
        }

        return array_map('strtolower', $mStops);
    }

    private function normalizeType(mixed $mType): string
    {
        if (!is_string($mType) || !in_array($mType, self::TYPES, true)) {
            throw new \InvalidArgumentException('A gradient type is linear, radial or conic');
        }

        return $mType;
    }

    private function normalizeAngle(mixed $mAngle): int
    {
        if (is_string($mAngle) && preg_match(self::ANGLE_PATTERN, $mAngle) === 1) {
            $mAngle = (int) $mAngle;
        }

        if (!is_int($mAngle) || $mAngle < 0 || $mAngle > self::MAX_ANGLE) {
            throw new \InvalidArgumentException('A gradient angle is a whole number of degrees from 0 to 359');
        }

        return $mAngle;
    }
}
