<?php

namespace Logingrupa\ColorClassifier\Models;

use Model;
use Logingrupa\ColorClassifier\Classes\ColorLabPayloadCache;

/**
 * ColorEntry Model — stores extracted and classified color data for each product offer.
 *
 * Represents a single processed offer image with its dominant hex color,
 * OKLCH color space values, color palette, human-readable name, and full taxonomy.
 *
 * @package Logingrupa\ColorClassifier\Models
 */
class ColorEntry extends Model
{
    /** @var string Database table name. */
    protected $table = 'logingrupa_colorclassifier_color_entries';

    /**
     * Every column except cropped_image_data, whose base64 blob costs about
     * 14 KB per row. Paths that never render the image select these instead
     * of loading the whole table.
     *
     * @var array<int, string>
     */
    public const COLUMNS_WITHOUT_IMAGE_DATA = [
        'id',
        'offer_id',
        'product_name',
        'variation_name',
        'image_url',
        'detail_url',
        'hex_color',
        'oklch_values',
        'palette_colors',
        'color_name',
        'taxonomy',
        'confidence_score',
        'processed_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'offer_id',
        'product_name',
        'variation_name',
        'image_url',
        'detail_url',
        'cropped_image_data',
        'hex_color',
        'oklch_values',
        'palette_colors',
        'color_name',
        'taxonomy',
        'confidence_score',
        'processed_at',
    ];

    /**
     * Attributes to cast to native types.
     *
     * JSON columns are automatically decoded to arrays on access
     * and encoded back to JSON on save.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'oklch_values'     => 'array',
        'palette_colors'   => 'array',
        'taxonomy'         => 'array',
        'processed_at'     => 'datetime',
        'confidence_score' => 'decimal:2',
    ];

    /**
     * Query scope selecting every column except the cropped image blob.
     *
     * @param \October\Rain\Database\Builder $obQuery
     * @return \October\Rain\Database\Builder
     */
    public function scopeWithoutImageData($obQuery)
    {
        return $obQuery->select(self::COLUMNS_WITHOUT_IMAGE_DATA);
    }

    /**
     * afterSave drops the cached Color Lab payloads derived from this table.
     *
     * @return void
     */
    public function afterSave()
    {
        ColorLabPayloadCache::flush();
    }

    /**
     * afterDelete drops the cached Color Lab payloads derived from this table.
     *
     * @return void
     */
    public function afterDelete()
    {
        ColorLabPayloadCache::flush();
    }
}
