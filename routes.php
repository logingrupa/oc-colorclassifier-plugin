<?php

use Illuminate\Support\Facades\Route;
use Logingrupa\ColorClassifier\Classes\Taxonomy;
use Logingrupa\ColorClassifier\Models\ColorEntry;

/*
 * Frontend route for the Color Lab page.
 *
 * Renders a standalone page using the plugin's own Blade view,
 * independent of any CMS theme. The component is still available
 * for theme integration via [colorLab].
 */
Route::get('/tools/color-lab', function () {
    $arColorEntries = ColorEntry::all();

    $arColorData = [];
    foreach ($arColorEntries as $obEntry) {
        $arColorData[] = [
            'id'               => $obEntry->id,
            'productName'      => $obEntry->product_name,
            'variationName'    => $obEntry->variation_name,
            'hexColor'         => $obEntry->hex_color,
            'colorName'        => $obEntry->color_name,
            'oklch'            => $obEntry->oklch_values,
            'paletteColors'    => $obEntry->palette_colors,
            'taxonomy'         => $obEntry->taxonomy,
            'confidenceScore'  => (float) $obEntry->confidence_score,
            'imageUrl'         => $obEntry->image_url,
            'detailUrl'        => $obEntry->detail_url,
            'croppedImageData' => $obEntry->cropped_image_data,
        ];
    }

    $arTaxonomy = [
        'families'    => Taxonomy::$colorFamilies,
        'undertones'  => Taxonomy::$undertones,
        'depths'      => Taxonomy::$depths,
        'saturations' => Taxonomy::$saturations,
        'finishes'    => Taxonomy::$finishes,
        'opacities'   => Taxonomy::$opacities,
    ];

    return view('logingrupa.colorclassifier::color-lab', [
        'colorLabEntriesJson'  => json_encode($arColorData),
        'colorLabEntryCount'   => count($arColorData),
        'colorLabTaxonomyJson' => json_encode($arTaxonomy),
        'colorLabPageTitle'    => 'Color Lab',
        'colorLabPlotlyCdn'    => 'https://cdn.plot.ly/plotly-2.35.2.min.js',
        'colorLabProductUrl'   => '/products/detail/:slug',
    ]);
})->middleware('web');

/*
 * API route — returns color data as JSON for external consumers.
 */
Route::get('/api/color-lab/data', function () {
    $arColorEntries = ColorEntry::all();

    $arColorData = [];
    foreach ($arColorEntries as $obEntry) {
        $arColorData[] = [
            'id'               => $obEntry->id,
            'productName'      => $obEntry->product_name,
            'variationName'    => $obEntry->variation_name,
            'hexColor'         => $obEntry->hex_color,
            'colorName'        => $obEntry->color_name,
            'oklch'            => $obEntry->oklch_values,
            'paletteColors'    => $obEntry->palette_colors,
            'taxonomy'         => $obEntry->taxonomy,
            'confidenceScore'  => (float) $obEntry->confidence_score,
            'imageUrl'         => $obEntry->image_url,
            'detailUrl'        => $obEntry->detail_url,
        ];
    }

    return response()->json([
        'entries'  => $arColorData,
        'taxonomy' => [
            'families'    => Taxonomy::$colorFamilies,
            'undertones'  => Taxonomy::$undertones,
            'depths'      => Taxonomy::$depths,
            'saturations' => Taxonomy::$saturations,
            'finishes'    => Taxonomy::$finishes,
            'opacities'   => Taxonomy::$opacities,
        ],
    ]);
})->middleware('web');
