<?php

namespace Logingrupa\ColorClassifier\Components;

use Cms\Classes\ComponentBase;
use Logingrupa\ColorClassifier\Classes\Taxonomy;
use Logingrupa\ColorClassifier\Models\ColorEntry;

/**
 * ColorLab Component — OKLCH Color Space Explorer.
 *
 * Provides a dual-view interactive color visualization:
 *   View A: 2D taxonomy matrix (color family columns × depth rows).
 *   View B: 3D Plotly scatter plot in OKLCH cylindrical coordinates.
 *
 * Drop this component onto any page in any theme. It loads its own
 * CSS, JS, and Plotly CDN dependency. Data comes from the ColorEntry model.
 *
 * Usage in a CMS page:
 *   [colorLab]
 *   ==
 *   {% component 'colorLab' %}
 *
 * @package Logingrupa\ColorClassifier\Components
 */
class ColorLab extends ComponentBase
{
    /**
     * Returns component registration details.
     *
     * @return array<string, string>
     */
    public function componentDetails(): array
    {
        return [
            'name'        => 'Color Lab',
            'description' => 'OKLCH Color Space Explorer — dual-view color visualization with taxonomy filters',
        ];
    }

    /**
     * Defines configurable component properties.
     *
     * @return array<string, array<string, mixed>>
     */
    public function defineProperties(): array
    {
        return [
            'pageTitle' => [
                'title'       => 'Page Title',
                'description' => 'Title displayed in the Color Lab header',
                'default'     => 'Color Lab',
                'type'        => 'string',
            ],
            'plotlyCdn' => [
                'title'       => 'Plotly CDN URL',
                'description' => 'CDN URL for Plotly.js (leave blank to disable 3D view)',
                'default'     => 'https://cdn.plot.ly/plotly-2.35.2.min.js',
                'type'        => 'string',
            ],
            'productDetailUrl' => [
                'title'       => 'Product Detail URL Pattern',
                'description' => 'URL pattern for product links. Use :slug as placeholder.',
                'default'     => '/products/detail/:slug',
                'type'        => 'string',
            ],
        ];
    }

    /**
     * Runs when the component is rendered on a page.
     *
     * Loads all color entries from the database, transforms them into
     * a JSON-serializable format, and injects component CSS/JS assets.
     *
     * @return void
     */
    public function onRun(): void
    {
        $this->loadColorData();
        $this->loadAssets();
    }

    /**
     * AJAX handler — returns color data as JSON for lazy-loading scenarios.
     *
     * @return array<string, mixed>
     */
    public function onLoadColorData(): array
    {
        return [
            'entries'  => $this->buildColorEntriesArray(),
            'taxonomy' => $this->buildTaxonomyArray(),
        ];
    }

    /**
     * Loads color entries and taxonomy data into page variables.
     *
     * @return void
     */
    private function loadColorData(): void
    {
        $arColorData = $this->buildColorEntriesArray();

        $this->page['colorLabEntriesJson']  = json_encode($arColorData);
        $this->page['colorLabEntryCount']   = count($arColorData);
        $this->page['colorLabTaxonomyJson'] = json_encode($this->buildTaxonomyArray());
        $this->page['colorLabPageTitle']    = $this->property('pageTitle');
        $this->page['colorLabPlotlyCdn']    = $this->property('plotlyCdn');
        $this->page['colorLabProductUrl']   = $this->property('productDetailUrl');
    }

    /**
     * Loads component CSS and JS assets.
     *
     * @return void
     */
    private function loadAssets(): void
    {
        $this->addCss('/plugins/logingrupa/colorclassifier/assets/css/color-lab.css');
        $this->addJs('/plugins/logingrupa/colorclassifier/assets/js/color-lab.js');
    }

    /**
     * Transforms all ColorEntry records into a JSON-serializable array.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildColorEntriesArray(): array
    {
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

        return $arColorData;
    }

    /**
     * Builds the taxonomy options array from Taxonomy constants.
     *
     * @return array<string, array<int, string>>
     */
    private function buildTaxonomyArray(): array
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
