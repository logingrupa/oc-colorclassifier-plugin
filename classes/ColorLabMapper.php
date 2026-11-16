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
     * Hex, OKLCH, family and confidence are the effective values (manual
     * overrides applied); manual and autoFamily tell the consumer when and
     * what the classifier originally said. The page view additionally needs
     * the cropped image data; the JSON API endpoint omits it to keep the
     * payload small.
     *
     * @param ColorEntry $obEntry Entry to map.
     * @param bool $blIncludeCroppedImageData Append croppedImageData (page view only).
     *
     * @return array<string, mixed>
     */
    public static function mapEntry(ColorEntry $obEntry, bool $blIncludeCroppedImageData = false): array
    {
        $arEffective = EffectiveColorResolver::resolve($obEntry);

        $arEntryData = [
            'id'              => $obEntry->id,
            'productName'     => $obEntry->product_name,
            'variationName'   => $obEntry->variation_name,
            'hexColor'        => $arEffective['hex'],
            'colorName'       => $obEntry->color_name,
            'oklch'           => [
                'lightness' => $arEffective['lightness'],
                'chroma'    => $arEffective['chroma'],
                'hue'       => $arEffective['hue'],
            ],
            'paletteColors'   => $obEntry->palette_colors,
            'taxonomy'        => array_merge((array) $obEntry->taxonomy, ['family' => $arEffective['family']]),
            'confidenceScore' => (float) $arEffective['confidence'],
            'manual'          => $arEffective['manual'],
            'autoFamily'      => (string) $arEffective['auto_family'],
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
     * @param array<int, string> $arFamilyNames Family keys in Families page order.
     *
     * @return array<string, array<int, string>>
     */
    public static function mapTaxonomy(array $arFamilyNames): array
    {
        return [
            'families'    => $arFamilyNames,
            'undertones'  => Taxonomy::$undertones,
            'depths'      => Taxonomy::$depths,
            'saturations' => Taxonomy::$saturations,
            'finishes'    => Taxonomy::$finishes,
            'opacities'   => Taxonomy::$opacities,
        ];
    }
}
