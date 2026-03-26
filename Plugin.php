<?php

namespace Logingrupa\ColorClassifier;

use System\Classes\PluginBase;

/**
 * ColorClassifier Plugin — Gel polish color extraction and taxonomy classification.
 *
 * Registers backend navigation, permissions, and plugin metadata for
 * the Logingrupa.ColorClassifier OctoberCMS plugin.
 *
 * @package Logingrupa\ColorClassifier
 */
class Plugin extends PluginBase
{
    /**
     * Register view namespace and any boot-time logic.
     *
     * @return void
     */
    public function boot(): void
    {
        $viewsPath = plugins_path('logingrupa/colorclassifier/views');

        app('view')->addNamespace('logingrupa.colorclassifier', $viewsPath);
    }

    /**
     * Returns information about this plugin.
     *
     * @return array<string, string>
     */
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Color Classifier',
            'description' => 'Gel polish color extraction and taxonomy classification',
            'author'      => 'Logingrupa',
            'icon'        => 'icon-eyedropper',
        ];
    }

    /**
     * Registers frontend components provided by this plugin.
     *
     * @return array<string, string>
     */
    public function registerComponents(): array
    {
        return [
            \Logingrupa\ColorClassifier\Components\ColorLab::class => 'colorLab',
        ];
    }

    /**
     * Registers backend navigation items for this plugin.
     *
     * @return array<string, mixed>
     */
    public function registerNavigation(): array
    {
        return [
            'colorclassifier' => [
                'label'       => 'Color Classifier',
                'url'         => \Backend::url('logingrupa/colorclassifier/colorentries'),
                'icon'        => 'icon-eyedropper',
                'permissions' => ['logingrupa.colorclassifier.manage'],
                'order'       => 500,
            ],
        ];
    }

    /**
     * Registers backend permissions for this plugin.
     *
     * @return array<string, mixed>
     */
    public function registerPermissions(): array
    {
        return [
            'logingrupa.colorclassifier.manage' => [
                'tab'   => 'Color Classifier',
                'label' => 'Manage color classifier entries',
            ],
        ];
    }

    /**
     * Registers custom list column types for the backend list widget.
     *
     * @return array<string, callable>
     */
    public function registerListColumnTypes(): array
    {
        return [
            'color_swatch'      => [$this, 'evalColorSwatchColumn'],
            'palette_gradient'  => [$this, 'evalPaletteGradientColumn'],
            'taxonomy_value'    => [$this, 'evalTaxonomyValueColumn'],
            'image_thumbnail'   => [$this, 'evalImageThumbnailColumn'],
            'cropped_preview'   => [$this, 'evalCroppedPreviewColumn'],
            'confidence_badge'  => [$this, 'evalConfidenceBadgeColumn'],
        ];
    }

    /**
     * Renders a color swatch box with hex label.
     *
     * @param mixed $value The hex_color value.
     * @param object $column Column definition.
     * @param \Logingrupa\ColorClassifier\Models\ColorEntry $record The model record.
     * @return string
     */
    public function evalColorSwatchColumn($value, $column, $record): string
    {
        if (empty($value)) {
            return '<span class="text-muted">—</span>';
        }

        $hexColor = e($value);
        $oklchValues = $record->oklch_values;
        $oklchLabel = '';

        if (is_array($oklchValues)) {
            $lightness = round($oklchValues['lightness'] ?? 0, 3);
            $chroma = round($oklchValues['chroma'] ?? 0, 3);
            $hue = round($oklchValues['hue'] ?? 0, 1);
            $oklchLabel = "<div style=\"font-size:10px;color:#888;margin-top:2px;\">oklch({$lightness} {$chroma} {$hue})</div>";
        }

        $textColor = ($oklchValues['lightness'] ?? 0.5) > 0.6 ? '#000' : '#fff';

        return "<div style=\"display:inline-flex;align-items:center;gap:8px;\">"
            . "<span style=\"display:inline-block;width:28px;height:28px;border-radius:4px;border:1px solid rgba(0,0,0,.15);background:{$hexColor};\"></span>"
            . "<div><code style=\"font-size:12px;\">{$hexColor}</code>{$oklchLabel}</div>"
            . "</div>";
    }

    /**
     * Renders a horizontal gradient strip from the 5-color palette.
     *
     * @param mixed $value The palette_colors JSON array.
     * @param object $column Column definition.
     * @param \Logingrupa\ColorClassifier\Models\ColorEntry $record The model record.
     * @return string
     */
    public function evalPaletteGradientColumn($value, $column, $record): string
    {
        $paletteColors = $record->palette_colors;

        if (!is_array($paletteColors) || count($paletteColors) === 0) {
            return '<span class="text-muted">—</span>';
        }

        $escapedColors = array_map('e', $paletteColors);
        $gradientStops = implode(', ', $escapedColors);

        $swatches = '';
        foreach ($escapedColors as $color) {
            $swatches .= "<span style=\"display:inline-block;width:16px;height:16px;border-radius:2px;border:1px solid rgba(0,0,0,.1);background:{$color};\"></span>";
        }

        return "<div style=\"display:flex;flex-direction:column;gap:4px;\">"
            . "<div style=\"width:120px;height:12px;border-radius:3px;border:1px solid rgba(0,0,0,.1);background:linear-gradient(90deg, {$gradientStops});\"></div>"
            . "<div style=\"display:flex;gap:2px;\">{$swatches}</div>"
            . "</div>";
    }

    /**
     * Renders a taxonomy field value from the JSON taxonomy column.
     *
     * @param mixed $value The taxonomy JSON value.
     * @param object $column Column definition with config['taxonomyField'].
     * @param \Logingrupa\ColorClassifier\Models\ColorEntry $record The model record.
     * @return string
     */
    public function evalTaxonomyValueColumn($value, $column, $record): string
    {
        $taxonomy = $record->taxonomy;

        // Determine field from nested config or from column name (taxonomy_family → family)
        $nestedConfig = $column->config['config'] ?? [];
        $fieldName = $nestedConfig['taxonomyField']
            ?? $column->config['taxonomyField']
            ?? str_replace('taxonomy_', '', $column->columnName);

        if (!is_array($taxonomy) || empty($fieldName) || empty($taxonomy[$fieldName])) {
            return '<span class="text-muted">—</span>';
        }

        $fieldValue = e(ucfirst($taxonomy[$fieldName]));

        return "<span>{$fieldValue}</span>";
    }

    /**
     * Renders a small image thumbnail from a URL.
     *
     * @param mixed $value The image URL.
     * @param object $column Column definition.
     * @param \Logingrupa\ColorClassifier\Models\ColorEntry $record The model record.
     * @return string
     */
    public function evalImageThumbnailColumn($value, $column, $record): string
    {
        if (empty($value)) {
            return '<span class="text-muted">—</span>';
        }

        $imageUrl = e($value);

        return "<img src=\"{$imageUrl}\" alt=\"\" "
            . "style=\"width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid rgba(0,0,0,.1);\" "
            . "loading=\"lazy\" />";
    }

    /**
     * Renders the 50x50 cropped center image from base64 data.
     *
     * @param mixed $value The cropped_image_data base64 string.
     * @param object $column Column definition.
     * @param \Logingrupa\ColorClassifier\Models\ColorEntry $record The model record.
     * @return string
     */
    public function evalCroppedPreviewColumn($value, $column, $record): string
    {
        if (empty($value)) {
            return '<span class="text-muted">—</span>';
        }

        return "<img src=\"{$value}\" alt=\"Cropped center\" "
            . "style=\"width:36px;height:36px;border-radius:4px;border:1px solid rgba(0,0,0,.15);image-rendering:pixelated;\" />";
    }

    /**
     * Renders a confidence score as a colored percentage badge.
     *
     * @param mixed $value The confidence_score decimal.
     * @param object $column Column definition.
     * @param \Logingrupa\ColorClassifier\Models\ColorEntry $record The model record.
     * @return string
     */
    public function evalConfidenceBadgeColumn($value, $column, $record): string
    {
        if ($value === null) {
            return '<span class="text-muted">—</span>';
        }

        $percentage = round((float) $value * 100);
        $badgeColor = $percentage >= 80 ? '#28a745' : ($percentage >= 50 ? '#ffc107' : '#dc3545');
        $textColor = $percentage >= 80 ? '#fff' : ($percentage >= 50 ? '#000' : '#fff');

        return "<span style=\"display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;"
            . "background:{$badgeColor};color:{$textColor};\">{$percentage}%</span>";
    }
}
