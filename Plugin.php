<?php

namespace Logingrupa\ColorClassifier;

use Logingrupa\ColorClassifier\Classes\EffectiveColorResolver;
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
     * Registers the 1C collection sync and the new offer color commands.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerConsoleCommand('colorclassifier.sync_collections', \Logingrupa\ColorClassifier\Console\SyncCollections::class);
        $this->registerConsoleCommand('colorclassifier.process_new_offers', \Logingrupa\ColorClassifier\Console\ProcessNewOffers::class);
    }

    /**
     * Gives new offers their color 10 minutes past the hour and keeps the collection
     * families in step with 1C 20 minutes past, so both read the catalog warmed at :00.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    public function registerSchedule($schedule): void
    {
        $schedule->command('colorclassifier:process-new-offers')->hourlyAt(10)->withoutOverlapping();
        $schedule->command('colorclassifier:sync-collections')->hourlyAt(20)->withoutOverlapping();
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
                'sideMenu'    => [
                    'entries' => [
                        'label'       => 'Color entries',
                        'url'         => \Backend::url('logingrupa/colorclassifier/colorentries'),
                        'icon'        => 'icon-list',
                        'permissions' => ['logingrupa.colorclassifier.manage'],
                    ],
                    'board' => [
                        'label'       => 'Family board',
                        'url'         => \Backend::url('logingrupa/colorclassifier/familyboard'),
                        'icon'        => 'icon-th-large',
                        'permissions' => ['logingrupa.colorclassifier.manage'],
                    ],
                    'families' => [
                        'label'       => 'Families',
                        'url'         => \Backend::url('logingrupa/colorclassifier/colorfamilies'),
                        'icon'        => 'icon-paint-brush',
                        'permissions' => ['logingrupa.colorclassifier.manage'],
                    ],
                ],
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
            'effective_family'  => [$this, 'evalEffectiveFamilyColumn'],
            'lock_state'        => [$this, 'evalLockStateColumn'],
            'image_thumbnail'   => [$this, 'evalImageThumbnailColumn'],
            'cropped_preview'   => [$this, 'evalCroppedPreviewColumn'],
            'confidence_badge'  => [$this, 'evalConfidenceBadgeColumn'],
            'product_link'      => [$this, 'evalProductLinkColumn'],
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
        $arEffective = EffectiveColorResolver::resolve($record);

        if (empty($arEffective['hex'])) {
            return '<span class="text-muted">—</span>';
        }

        $hexColor = e($arEffective['hex']);
        $oklchLabel = '';

        if ($arEffective['lightness'] !== null) {
            $lightness = round((float) $arEffective['lightness'], 3);
            $chroma = round((float) ($arEffective['chroma'] ?? 0), 3);
            $hue = round((float) ($arEffective['hue'] ?? 0), 1);
            $oklchLabel = "<div style=\"font-size:10px;color:#888;margin-top:2px;\">oklch({$lightness} {$chroma} {$hue})</div>";
        }

        $autoHexLabel = '';

        if ($arEffective['hex_overridden'] && !empty($arEffective['auto_hex'])) {
            $autoHex = e($arEffective['auto_hex']);
            $autoHexLabel = "<div style=\"font-size:10px;color:#888;text-decoration:line-through;\">{$autoHex}</div>";
        }

        return "<div style=\"display:inline-flex;align-items:center;gap:8px;\">"
            . "<span style=\"display:inline-block;width:28px;height:28px;border-radius:4px;border:1px solid rgba(0,0,0,.15);background:{$hexColor};\"></span>"
            . "<div><code style=\"font-size:12px;\">{$hexColor}</code>{$autoHexLabel}{$oklchLabel}</div>"
            . "</div>";
    }

    /**
     * Renders the effective family with a manual marker and the struck-through classifier family.
     *
     * @param mixed $value The taxonomy value (unused, the resolver reads the record).
     * @param object $column Column definition.
     * @param \Logingrupa\ColorClassifier\Models\ColorEntry $record The model record.
     * @return string
     */
    public function evalEffectiveFamilyColumn($value, $column, $record): string
    {
        $arEffective = EffectiveColorResolver::resolve($record);

        if (empty($arEffective['family'])) {
            return '<span class="text-muted">—</span>';
        }

        $sMarkup = '<span>' . e($arEffective['family']) . '</span>';

        if ($arEffective['manual']) {
            $sMarkup .= ' <span style="display:inline-block;padding:0 6px;border-radius:8px;font-size:10px;font-weight:600;background:#6f42c1;color:#fff;">manual</span>';
        }

        if ($arEffective['family_overridden'] && !empty($arEffective['auto_family'])) {
            $sMarkup .= '<div style="font-size:11px;opacity:.6;text-decoration:line-through;">auto: ' . e($arEffective['auto_family']) . '</div>';
        } elseif (is_array($record->taxonomy) && !empty($record->taxonomy['secondary_family'])) {
            $sMarkup .= ' <span style="opacity:.5;font-size:.85em;">/ ' . e($record->taxonomy['secondary_family']) . '</span>';
        }

        return $sMarkup;
    }

    /**
     * Renders a lock icon for rows Process All skips.
     *
     * @param mixed $value The is_locked value.
     * @param object $column Column definition.
     * @param \Logingrupa\ColorClassifier\Models\ColorEntry $record The model record.
     * @return string
     */
    public function evalLockStateColumn($value, $column, $record): string
    {
        if (!$value) {
            return '<span class="text-muted">—</span>';
        }

        return '<i class="icon-lock" title="Locked: Process All skips it"></i>';
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
        $secondaryHtml = '';

        if ($fieldName === 'family' && !empty($taxonomy['secondary_family'])) {
            $secondaryValue = e(ucfirst($taxonomy['secondary_family']));
            $secondaryHtml = " <span style=\"opacity:.5;font-size:.85em;\">/ {$secondaryValue}</span>";
        }

        return "<span>{$fieldValue}{$secondaryHtml}</span>";
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

    /**
     * Renders a product name as a clickable link to the frontend product page.
     *
     * Falls back to plain text if no detail_url is stored.
     *
     * @param mixed $value The product_name value.
     * @param object $column Column definition.
     * @param \Logingrupa\ColorClassifier\Models\ColorEntry $record The model record.
     * @return string
     */
    public function evalProductLinkColumn($value, $column, $record): string
    {
        $productName = e($value ?: '—');

        if (empty($record->detail_url)) {
            return $productName;
        }

        $detailUrl = e($record->detail_url);

        return "<a href=\"{$detailUrl}\" target=\"_blank\" "
            . "style=\"color:#4da7e8;text-decoration:none;\" "
            . "title=\"View on frontend\">{$productName} ↗</a>";
    }
}
