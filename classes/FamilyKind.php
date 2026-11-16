<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * FamilyKind - the two kinds of color_families row.
 *
 * A color can be an offer's primary family; a collection is a 1C tag shown as a
 * pill next to the colors and never a primary.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
final class FamilyKind
{
    public const COLOR = 'color';

    public const COLLECTION = 'collection';

    /**
     * @param mixed $mKind Stored kind, null for rows written before kinds existed.
     *
     * @return string COLLECTION only for the exact value, COLOR otherwise.
     */
    public static function normalize(mixed $mKind): string
    {
        return $mKind === self::COLLECTION ? self::COLLECTION : self::COLOR;
    }
}
