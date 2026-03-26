<?php

namespace Logingrupa\ColorClassifier\Exports;

use Backend\Models\ExportModel;
use Logingrupa\ColorClassifier\Models\ColorEntry;

/**
 * ColorEntryExport — Export model for color classification entries.
 *
 * Provides CSV/JSON export of all ColorEntry records via OctoberCMS's
 * built-in ImportExportController behavior. JSON fields are encoded to
 * strings for CSV compatibility.
 *
 * @package Logingrupa\ColorClassifier\Exports
 */
class ColorEntryExport extends ExportModel
{
    /** @var string Database table name (matches ColorEntry model). */
    protected $table = 'logingrupa_colorclassifier_color_entries';

    /**
     * Fetch all color entries and map them to export rows.
     *
     * JSON fields (oklch_values, palette_colors, taxonomy) are encoded
     * as JSON strings so they are legible in CSV format.
     *
     * @param array<int, string>  $columns    Columns to include (passed by behavior).
     * @param string|null         $sessionKey Import session key (unused in export).
     *
     * @return array<int, array<string, mixed>> Rows ready for export.
     */
    public function exportData($columns, $sessionKey = null): array
    {
        $allColorEntries = ColorEntry::all();

        return $allColorEntries->map(function (ColorEntry $colorEntry) {
            return [
                'offer_id'         => $colorEntry->offer_id,
                'product_name'     => $colorEntry->product_name,
                'variation_name'   => $colorEntry->variation_name,
                'image_url'        => $colorEntry->image_url,
                'hex_color'        => $colorEntry->hex_color,
                'oklch_values'     => json_encode($colorEntry->oklch_values),
                'palette_colors'   => json_encode($colorEntry->palette_colors),
                'color_name'       => $colorEntry->color_name,
                'taxonomy'         => json_encode($colorEntry->taxonomy),
                'confidence_score' => $colorEntry->confidence_score,
                'processed_at'     => $colorEntry->processed_at
                    ? $colorEntry->processed_at->toDateTimeString()
                    : null,
                'created_at'       => $colorEntry->created_at
                    ? $colorEntry->created_at->toDateTimeString()
                    : null,
                'updated_at'       => $colorEntry->updated_at
                    ? $colorEntry->updated_at->toDateTimeString()
                    : null,
            ];
        })->values()->all();
    }
}
