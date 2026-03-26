<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * ColorNamer — Nearest color name lookup for gel polish colors.
 *
 * Maintains a curated lookup table of ~140 color names with their hex values
 * and finds the closest match to any given color using Euclidean distance
 * in RGB color space.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class ColorNamer
{
    /**
     * Color name lookup table mapping hex values to human-readable names.
     *
     * Includes comprehensive gel polish colors: basic colors, nail-specific
     * names, and cosmetic industry standard names.
     *
     * @var array<string, string>
     */
    private static array $colorNameLookup = [
        // Reds
        '#FF0000' => 'Red',
        '#DC143C' => 'Crimson',
        '#B22222' => 'Firebrick',
        '#8B0000' => 'Dark Red',
        '#C41E3A' => 'Cardinal Red',
        '#AA0000' => 'Deep Red',
        '#FF2400' => 'Scarlet',
        '#E34234' => 'Vermilion',
        '#CC0000' => 'Classic Red',
        '#990000' => 'Burgundy Red',

        // Burgundy / Wine
        '#722F37' => 'Wine',
        '#800020' => 'Burgundy',
        '#4A0E2E' => 'Dark Burgundy',
        '#7B1113' => 'Garnet',
        '#6D2B3D' => 'Berry Wine',
        '#5C2028' => 'Deep Wine',
        '#6F2232' => 'Merlot',
        '#883344' => 'Claret',

        // Pinks
        '#FF69B4' => 'Hot Pink',
        '#FFB6C1' => 'Light Pink',
        '#FFC0CB' => 'Pink',
        '#FF91A4' => 'Salmon Pink',
        '#E75480' => 'Deep Pink',
        '#F08080' => 'Light Coral',
        '#FF007F' => 'Rose',
        '#DB7093' => 'Pale Violet Red',
        '#C71585' => 'Medium Violet Red',
        '#FFAEC9' => 'Baby Pink',
        '#F4A7B9' => 'Blush Pink',
        '#D4748A' => 'Dusty Rose',
        '#E8A0B4' => 'Flamingo Pink',
        '#B56576' => 'Mauve Pink',

        // Coral / Peach
        '#FF7F50' => 'Coral',
        '#FF6B6B' => 'Coral Red',
        '#FF4500' => 'Orange Red',
        '#FA8072' => 'Salmon',
        '#FFCBA4' => 'Peach',
        '#FFDAB9' => 'Peach Puff',
        '#FF9A76' => 'Peach Orange',
        '#F4A460' => 'Sandy Brown',
        '#E8956D' => 'Terra Cotta',
        '#E77B54' => 'Burnt Sienna Light',

        // Oranges
        '#FFA500' => 'Orange',
        '#FF8C00' => 'Dark Orange',
        '#FF6600' => 'Safety Orange',
        '#E55B00' => 'Burnt Orange',
        '#FF4500' => 'Deep Orange',
        '#D2691E' => 'Chocolate Orange',

        // Yellows / Gold
        '#FFFF00' => 'Yellow',
        '#FFD700' => 'Gold',
        '#DAA520' => 'Goldenrod',
        '#B8860B' => 'Dark Goldenrod',
        '#F0E68C' => 'Khaki',
        '#FAFAD2' => 'Light Goldenrod',
        '#EEE8AA' => 'Pale Goldenrod',
        '#FFF44F' => 'Lemon Yellow',
        '#FFBF00' => 'Amber',
        '#E6C35C' => 'Champagne Gold',

        // Greens
        '#008000' => 'Green',
        '#00FF00' => 'Lime',
        '#228B22' => 'Forest Green',
        '#006400' => 'Dark Green',
        '#32CD32' => 'Lime Green',
        '#90EE90' => 'Light Green',
        '#98FB98' => 'Pale Green',
        '#3CB371' => 'Medium Sea Green',
        '#2E8B57' => 'Sea Green',
        '#008080' => 'Teal',
        '#20B2AA' => 'Light Sea Green',
        '#4DB870' => 'Emerald',
        '#50C878' => 'Emerald Green',
        '#4CAF50' => 'Material Green',
        '#1B5E20' => 'Bottle Green',
        '#556B2F' => 'Olive Green',
        '#808000' => 'Olive',

        // Teals / Cyans
        '#40E0D0' => 'Turquoise',
        '#48D1CC' => 'Medium Turquoise',
        '#00CED1' => 'Dark Turquoise',
        '#00FFFF' => 'Cyan',
        '#E0FFFF' => 'Light Cyan',
        '#5F9EA0' => 'Cadet Blue',
        '#007BA7' => 'Cerulean',
        '#00B2EE' => 'Deep Sky Blue',

        // Blues
        '#0000FF' => 'Blue',
        '#0000CD' => 'Medium Blue',
        '#00008B' => 'Dark Blue',
        '#000080' => 'Navy',
        '#4169E1' => 'Royal Blue',
        '#6495ED' => 'Cornflower Blue',
        '#87CEEB' => 'Sky Blue',
        '#87CEFA' => 'Light Sky Blue',
        '#4682B4' => 'Steel Blue',
        '#1E90FF' => 'Dodger Blue',
        '#191970' => 'Midnight Blue',
        '#002366' => 'Royal Navy',
        '#B0C4DE' => 'Light Steel Blue',
        '#5072A7' => 'Denim Blue',

        // Purples / Violets
        '#800080' => 'Purple',
        '#8B00FF' => 'Violet',
        '#9400D3' => 'Dark Violet',
        '#6A0DAD' => 'Deep Purple',
        '#DA70D6' => 'Orchid',
        '#DDA0DD' => 'Plum',
        '#EE82EE' => 'Violet Light',
        '#FF00FF' => 'Magenta',
        '#BA55D3' => 'Medium Orchid',
        '#9370DB' => 'Medium Purple',
        '#7B68EE' => 'Medium Slate Blue',
        '#6B3A8A' => 'Indigo Purple',
        '#4B0082' => 'Indigo',
        '#E6CCFF' => 'Lavender Mist',
        '#D8BFD8' => 'Thistle',
        '#CC99CC' => 'Lilac',
        '#B57EDC' => 'Lavender',
        '#C0A0D0' => 'Soft Lavender',

        // Browns / Nudes / Taupes
        '#8B4513' => 'Saddle Brown',
        '#A52A2A' => 'Brown',
        '#654321' => 'Dark Brown',
        '#D2691E' => 'Chocolate',
        '#CD853F' => 'Peru',
        '#DEB887' => 'Burlywood',
        '#F5DEB3' => 'Wheat',
        '#D4A574' => 'Caramel',
        '#6B3A2A' => 'Espresso',
        '#C4956A' => 'Warm Caramel',
        '#BC8A5F' => 'Toffee',
        '#A07850' => 'Hazelnut',
        '#967117' => 'Muddy Brown',
        '#C19A6B' => 'Sand Brown',
        '#B8860B' => 'Warm Bronze',

        // Nudes / Beige
        '#FFDEAD' => 'Navajo White',
        '#FFE4B5' => 'Moccasin',
        '#F5F5DC' => 'Beige',
        '#FAF0E6' => 'Linen',
        '#FFF8DC' => 'Cornsilk',
        '#E8D5B7' => 'Nude Beige',
        '#D4B896' => 'Nude',
        '#C9A888' => 'Nude Blush',
        '#E8C9A0' => 'Warm Sand',
        '#DDC0A0' => 'Soft Sand',
        '#D2A679' => 'Almond',
        '#C4956A' => 'Praline',
        '#F5E6D3' => 'Ivory Nude',

        // Whites / Light
        '#FFFFFF' => 'White',
        '#FFFFF0' => 'Ivory',
        '#FFFAF0' => 'Floral White',
        '#F8F8FF' => 'Ghost White',
        '#F5F5F5' => 'White Smoke',
        '#FFF5EE' => 'Seashell',
        '#E8E0D8' => 'Pearl White',
        '#F0EAE0' => 'Cream White',

        // Blacks / Dark
        '#000000' => 'Black',
        '#1C1C1C' => 'Rich Black',
        '#2F2F2F' => 'Very Dark Gray',
        '#3D3D3D' => 'Charcoal',
        '#4A4A4A' => 'Dark Charcoal',
        '#696969' => 'Dim Gray',
        '#808080' => 'Gray',
        '#A9A9A9' => 'Dark Gray',
        '#C0C0C0' => 'Silver',
        '#D3D3D3' => 'Light Gray',

        // Metallics / Specials
        '#FFD700' => 'Gold',
        '#C0C0C0' => 'Silver',
        '#B87333' => 'Copper',
        '#E8C88A' => 'Rose Gold Light',
        '#C77D6A' => 'Rose Gold',
        '#B5813C' => 'Bronze',
        '#E8E8E8' => 'Platinum',
    ];

    /**
     * Find the nearest color name for a given hex color.
     *
     * Compares the input color against all entries in the lookup table
     * using Euclidean distance in RGB color space and returns the name
     * of the closest match.
     *
     * @param string $hexColor Hex color string, e.g. '#FF0000' or '#ff0000'.
     *
     * @return string Human-readable color name.
     */
    public static function findNearestColorName(string $hexColor): string
    {
        $inputRgb = self::hexToRgbArray(strtoupper($hexColor));

        $nearestName     = 'Unknown';
        $minimumDistance = PHP_FLOAT_MAX;

        foreach (self::$colorNameLookup as $lookupHex => $colorName) {
            $lookupRgb = self::hexToRgbArray($lookupHex);
            $distance  = self::calculateColorDistance($inputRgb, $lookupRgb);

            if ($distance < $minimumDistance) {
                $minimumDistance = $distance;
                $nearestName     = $colorName;
            }
        }

        return $nearestName;
    }

    /**
     * Calculate Euclidean distance between two RGB color arrays.
     *
     * @param array{red: int, green: int, blue: int} $rgbFirst  First color.
     * @param array{red: int, green: int, blue: int} $rgbSecond Second color.
     *
     * @return float Euclidean distance in RGB space (0 = identical, ~441 = max).
     */
    public static function calculateColorDistance(array $rgbFirst, array $rgbSecond): float
    {
        $redDifference   = $rgbFirst['red']   - $rgbSecond['red'];
        $greenDifference = $rgbFirst['green'] - $rgbSecond['green'];
        $blueDifference  = $rgbFirst['blue']  - $rgbSecond['blue'];

        return sqrt(
            ($redDifference   ** 2) +
            ($greenDifference ** 2) +
            ($blueDifference  ** 2)
        );
    }

    /**
     * Parse a hex color string into an RGB array.
     *
     * @param string $hexColor Uppercase hex string like '#FF0000'.
     *
     * @return array{red: int, green: int, blue: int}
     */
    private static function hexToRgbArray(string $hexColor): array
    {
        $stripped = ltrim($hexColor, '#');

        return [
            'red'   => hexdec(substr($stripped, 0, 2)),
            'green' => hexdec(substr($stripped, 2, 2)),
            'blue'  => hexdec(substr($stripped, 4, 2)),
        ];
    }
}
