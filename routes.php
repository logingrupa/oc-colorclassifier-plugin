<?php

use Illuminate\Support\Facades\Route;
use Logingrupa\ColorClassifier\Classes\ColorLabMapper;
use Logingrupa\ColorClassifier\Classes\ColorLabOfferDirectory;
use Logingrupa\ColorClassifier\Classes\ColorLabPayloadCache;
use Logingrupa\ColorClassifier\Classes\EtagRevalidation;
use Logingrupa\ColorClassifier\Classes\FamilyExportBuilder;
use Logingrupa\ColorClassifier\Classes\FamilyMembershipQuery;
use Logingrupa\ColorClassifier\Classes\SheetExportBuilder;
use Logingrupa\ColorClassifier\Models\ColorEntry;
use Logingrupa\ColorClassifier\Models\ColorFamily;

/*
 * Frontend route for the Color Lab page.
 *
 * Renders a standalone page using the plugin's own Blade view,
 * independent of any CMS theme. The component is still available
 * for theme integration via [colorLab].
 */
Route::get('/tools/color-lab', function () {
    $obOfferDirectory = ColorLabOfferDirectory::load();

    $arPagePayload = ColorLabPayloadCache::remember(ColorLabPayloadCache::KEY_PAGE_ENTRIES, function () use ($obOfferDirectory) {
        $arColorData = ColorLabMapper::mapPublicEntries(ColorEntry::all(), $obOfferDirectory, true);

        // Stored pre-encoded so a cache hit never hydrates 700+ models
        // carrying base64 blobs just to json_encode them again.
        return [
            'entriesJson' => (string) json_encode($arColorData),
            'entryCount'  => count($arColorData),
        ];
    }, $obOfferDirectory->version());

    $obPageView = view('logingrupa.colorclassifier::color-lab', [
        'colorLabEntriesJson'  => $arPagePayload['entriesJson'],
        'colorLabEntryCount'   => $arPagePayload['entryCount'],
        'colorLabTaxonomyJson' => json_encode(ColorLabMapper::mapTaxonomy(ColorFamily::listFamilyNamesInOrder())),
        'colorLabPageTitle'    => 'Color Lab',
        'colorLabPlotlyCdn'    => 'https://cdn.plot.ly/plotly-2.35.2.min.js',
        'colorLabProductUrl'   => '/products/detail/:slug',
    ]);

    return response($obPageView)->header('Cache-Control', 'public, max-age=3600');
})->middleware('web');

/*
 * API route - returns color data as JSON for external consumers.
 */
Route::get('/api/color-lab/data', function () {
    $obOfferDirectory = ColorLabOfferDirectory::load();

    $arDataPayload = ColorLabPayloadCache::remember(ColorLabPayloadCache::KEY_DATA, function () use ($obOfferDirectory) {
        return [
            'last_updated_at' => (string) ColorEntry::max('updated_at'),
            'entries'         => ColorLabMapper::mapPublicEntries(ColorEntry::withoutImageData()->get(), $obOfferDirectory),
            'taxonomy'        => ColorLabMapper::mapTaxonomy(ColorFamily::listFamilyNamesInOrder()),
        ];
    }, $obOfferDirectory->version());

    return response()->json($arDataPayload);
})->middleware('web');

/*
 * API route - color family taxonomy metadata for server-to-server consumption.
 *
 * Keyed by family slug (the STABLE URL contract) so an external store can
 * build localized color search and catalog filters: localized names,
 * per-locale synonyms, a canonical swatch hex, sort_order and active per
 * family. Payload key ORDER is the pill order consumers render: the
 * Families page sort order. Same ETag / If-None-Match conventions as
 * offers.json.
 */
Route::get('/api/color-lab/families.json', function () {
    $arPayload = (new FamilyExportBuilder())->build(ColorFamily::all());

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
    // Payload comes from the cache, so an If-None-Match revalidation costs a
    // cache read and a stamp compare, never a table load.
    $arPayload = ColorLabPayloadCache::remember(ColorLabPayloadCache::KEY_OFFERS, function () {
        return (new SheetExportBuilder())->build(ColorEntry::withoutImageData()->get(), (new FamilyMembershipQuery())->buildIndex());
    });

    return EtagRevalidation::respond($arPayload, (string) request()->header('If-None-Match', ''));
})->middleware('web');
