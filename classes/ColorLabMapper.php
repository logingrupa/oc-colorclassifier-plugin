<?php

namespace Logingrupa\ColorClassifier\Classes;

use Logingrupa\ColorClassifier\Models\ColorEntry;

/**
 * ColorLabMapper - shared ColorEntry and taxonomy mapping for Color Lab routes.
 *
 * Single source for the ColorEntry-to-array shape consumed by the
 * /tools/color-lab page and the /api/color-lab/data endpoint, and for
 * the taxonomy group listing both expose.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class ColorLabMapper
{
    /**
     * Map a ColorEntry to the Color Lab array shape.
     *
     * The page view additionally needs the cropped image data; the JSON
     * API endpoint omits it to keep the payload small.
     *
     * @param ColorEntry $obEntry Entry to map.
     * @param bool $blIncludeCroppedImageData Append croppedImageData (page view only).
     *
     * @return array<string, mixed>
     */
    public static function mapEntry(ColorEntry $obEntry, bool $blIncludeCroppedImageData = false): array
    {
        $arEntryData = [
            'id'              => $obEntry->id,
            'productName'     => $obEntry->product_name,
            'variationName'   => $obEntry->variation_name,
            'hexColor'        => $obEntry->hex_color,
            'colorName'       => $obEntry->color_name,
            'oklch'           => $obEntry->oklch_values,
            'paletteColors'   => $obEntry->palette_colors,
            'taxonomy'        => $obEntry->taxonomy,
            'confidenceScore' => (float) $obEntry->confidence_score,
            'imageUrl'        => $obEntry->image_url,
            'detailUrl'       => $obEntry->detail_url,
        ];

        if ($blIncludeCroppedImageData) {
            $arEntryData['croppedImageData'] = $obEntry->cropped_image_data;
        }

        return $arEntryData;
    }

    /**
     * Taxonomy group arrays exposed to Color Lab consumers.
     *
     * @return array<string, array<int, string>>
     */
    public static function mapTaxonomy(): array
    {
        return [
            'families'    => Taxonomy::$colorFamilies,
            'undertones'  => Taxonomy::$undertones,
            'depths'      => Taxonomy::$depths,
            'saturations' => Taxonomy::$saturations,
            'finishes'    => Taxonomy::$finishes,
            'opacities'   => Taxonomy::$opacities,
        ];
    }
}
