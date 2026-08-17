<?php

use Illuminate\Support\Facades\Route;
use Logingrupa\ColorClassifier\Classes\ColorLabMapper;
use Logingrupa\ColorClassifier\Classes\EtagRevalidation;
use Logingrupa\ColorClassifier\Classes\FamilyExportBuilder;
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
        'last_updated_at' => (string) ColorEntry::max('updated_at'),
        'entries'         => $arColorData,
        'taxonomy'        => ColorLabMapper::mapTaxonomy(),
    ]);
})->middleware('web');

/*
 * API route - color family taxonomy metadata for server-to-server consumption.
 *
 * Keyed by family slug (the STABLE URL contract) so an external store can
 * build localized color search and catalog filters: localized names,
 * per-locale synonyms and a canonical swatch hex per family. Payload key
 * ORDER is the pill order consumers render: the hand-curated taxonomy
 * order. Same ETag / If-None-Match conventions as offers.json.
 */
Route::get('/api/color-lab/families.json', function () {
    $obFamilyExportBuilder = new FamilyExportBuilder();
    $arPayload = $obFamilyExportBuilder->build();

    return EtagRevalidation::respond($arPayload, (string) request()->header('If-None-Match', ''));
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

    return EtagRevalidation::respond($arPayload, (string) request()->header('If-None-Match', ''));
})->middleware('web');
