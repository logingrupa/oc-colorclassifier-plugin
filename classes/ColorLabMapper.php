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
     * what the classifier originally said. The cropped image blob is never
     * part of the shape, so callers select the columns without it.
     *
     * @param ColorEntry $obEntry Entry to map.
     *
     * @return array<string, mixed>
     */
    public static function mapEntry(ColorEntry $obEntry): array
    {
        $arEffective = EffectiveColorResolver::resolve($obEntry);

        return [
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
            'taxonomy'        => self::mapTaxonomyValues((array) $obEntry->taxonomy, $arEffective),
            'confidenceScore' => (float) $arEffective['confidence'],
            'manual'          => $arEffective['manual'],
            'autoFamily'      => (string) $arEffective['auto_family'],
            'imageUrl'        => $obEntry->image_url,
            'detailUrl'       => $obEntry->detail_url,
        ];
    }

    /**
     * Map the entries the public Color Lab shows: only offers the catalog lists,
     * under the names and link the catalog gives them now.
     *
     * @param iterable<ColorEntry>   $obEntries        Stored entries.
     * @param ColorLabOfferDirectory $obOfferDirectory Current catalog offers.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function mapPublicEntries(iterable $obEntries, ColorLabOfferDirectory $obOfferDirectory): array
    {
        $arMappedEntries = [];

        foreach ($obOfferDirectory->filterListed($obEntries) as $obEntry) {
            $arMappedEntries[] = self::applyCurrentLabels(
                self::mapEntry($obEntry),
                $obOfferDirectory->resolveLabels((string) $obEntry->offer_id)
            );
        }

        return $arMappedEntries;
    }

    /**
     * Replace the stored product name, variation name and detail URL of a mapped entry.
     *
     * @param array<string, mixed>                                                      $arMappedEntry   Shape of mapEntry().
     * @param array{productName: string, variationName: string, detailUrl: string}|null $arCurrentLabels Null keeps the stored values.
     *
     * @return array<string, mixed>
     */
    public static function applyCurrentLabels(array $arMappedEntry, ?array $arCurrentLabels): array
    {
        return $arCurrentLabels === null ? $arMappedEntry : array_merge($arMappedEntry, $arCurrentLabels);
    }

    /**
     * Classifier taxonomy with the effective family. An overridden family drops
     * the classifier's secondary family, which described the old family.
     *
     * @param array<string, mixed> $arTaxonomy Stored classifier taxonomy.
     * @param array{family: string|null, auto_family: string|null} $arEffective Resolved effective values.
     *
     * @return array<string, mixed>
     */
    public static function mapTaxonomyValues(array $arTaxonomy, array $arEffective): array
    {
        $arTaxonomy['family'] = $arEffective['family'];

        if ($arEffective['family'] !== $arEffective['auto_family']) {
            $arTaxonomy['secondary_family'] = null;
        }

        return $arTaxonomy;
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
