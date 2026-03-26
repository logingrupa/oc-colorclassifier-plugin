<?php

namespace Logingrupa\ColorClassifier\Models;

use Model;

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
     * Attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'offer_id',
        'product_name',
        'variation_name',
        'image_url',
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
}
