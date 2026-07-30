<?php

use Illuminate\Support\Facades\Route;
use Logingrupa\ColorClassifier\Classes\ColorLabMapper;
use Logingrupa\ColorClassifier\Classes\SheetExportBuilder;
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
        $arColorData[] = ColorLabMapper::mapEntry($obEntry, true);
    }

    return view('logingrupa.colorclassifier::color-lab', [
        'colorLabEntriesJson'  => json_encode($arColorData),
        'colorLabEntryCount'   => count($arColorData),
        'colorLabTaxonomyJson' => json_encode(ColorLabMapper::mapTaxonomy()),
        'colorLabPageTitle'    => 'Color Lab',
        'colorLabPlotlyCdn'    => 'https://cdn.plot.ly/plotly-2.35.2.min.js',
        'colorLabProductUrl'   => '/products/detail/:slug',
    ]);
})->middleware('web');

/*
 * API route - returns color data as JSON for external consumers.
 */
Route::get('/api/color-lab/data', function () {
    $arColorEntries = ColorEntry::all();

    $arColorData = [];
    foreach ($arColorEntries as $obEntry) {
        $arColorData[] = ColorLabMapper::mapEntry($obEntry);
    }

    return response()->json([
        'entries'  => $arColorData,
        'taxonomy' => ColorLabMapper::mapTaxonomy(),
    ]);
})->middleware('web');

/*
 * API route - compact offer color map for server-to-server consumption.
 *
 * Keyed by offer UUID so an external store importing the same 1C catalog
 * can join on its offer external_id. Supports ETag / If-None-Match
 * revalidation with a one hour public cache lifetime.
 */
Route::get('/api/color-lab/offers.json', function () {
    $obExportBuilder = new SheetExportBuilder();
    $arPayload = $obExportBuilder->build(ColorEntry::all());

    $sEtag = '"' . $arPayload['version'] . '"';
    $arHeaders = [
        'ETag'          => $sEtag,
        'Cache-Control' => 'public, max-age=3600',
    ];

    if (request()->header('If-None-Match') === $sEtag) {
        return response('', 304, $arHeaders);
    }

    return response()->json($arPayload, 200, $arHeaders);
})->middleware('web');
