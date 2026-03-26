<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * Taxonomy — Gel polish color classification taxonomy constants.
 *
 * Provides static arrays for all classification dimensions used
 * when categorizing gel polish colors. These constants define the
 * controlled vocabulary for color families, undertones, depths,
 * saturations, finishes, and opacities.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class Taxonomy
{
    /**
     * Color family names — 22 distinct color families for gel polish.
     *
     * @var array<int, string>
     */
    public static array $colorFamilies = [
        'Red',
        'Orange',
        'Coral',
        'Peach',
        'Yellow',
        'Gold',
        'Green',
        'Teal',
        'Cyan',
        'Blue',
        'Navy',
        'Indigo',
        'Violet',
        'Purple',
        'Magenta',
        'Pink',
        'Rose',
        'Brown',
        'Beige',
        'Nude',
        'White',
        'Black',
    ];

    /**
     * Undertone values — warm, cool, or neutral bias in a color.
     *
     * @var array<int, string>
     */
    public static array $undertones = [
        'Warm',
        'Cool',
        'Neutral',
    ];

    /**
     * Depth values — lightness level from very light to very dark.
     *
     * @var array<int, string>
     */
    public static array $depths = [
        'Very Light',
        'Light',
        'Medium',
        'Dark',
        'Very Dark',
    ];

    /**
     * Saturation values — chroma intensity from muted to neon.
     *
     * @var array<int, string>
     */
    public static array $saturations = [
        'Muted',
        'Soft',
        'Medium',
        'Vivid',
        'Neon',
    ];

    /**
     * Finish values — surface texture/effect of the gel polish.
     *
     * Finish cannot be detected from image color analysis alone;
     * it is stored as null by default and must be set manually.
     *
     * @var array<int, string>
     */
    public static array $finishes = [
        'Cream',
        'Shimmer',
        'Glitter',
        'Metallic',
        'Chrome',
        'Matte',
        'Glossy',
        'Foil',
        'Holographic',
        'Cat Eye',
    ];

    /**
     * Opacity values — translucency level from sheer to fully opaque.
     *
     * @var array<int, string>
     */
    public static array $opacities = [
        'Sheer',
        'Semi-Sheer',
        'Semi-Opaque',
        'Opaque',
    ];
}
