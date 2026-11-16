<?php

namespace Logingrupa\ColorClassifier\Models;

use Model;
use Logingrupa\ColorClassifier\Classes\ColorLabPayloadCache;
use Logingrupa\ColorClassifier\Classes\EffectiveColorResolver;

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
        'family_override',
        'hex_override',
        'is_locked',
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
        'family_override',
        'hex_override',
        'is_locked',
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
        'is_locked'        => 'boolean',
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
     * Rows Process All skips.
     *
     * @param \October\Rain\Database\Builder $obQuery
     * @return \October\Rain\Database\Builder
     */
    public function scopeLocked($obQuery)
    {
        return $obQuery->where('is_locked', true);
    }

    /**
     * Rows with a family or hex override.
     *
     * @param \October\Rain\Database\Builder $obQuery
     * @return \October\Rain\Database\Builder
     */
    public function scopeManual($obQuery)
    {
        return $obQuery->where(function ($obNested) {
            $obNested->whereNotNull('family_override')->orWhereNotNull('hex_override');
        });
    }

    /**
     * Rows whose effective confidence is below the review threshold. A family
     * override reports confidence 1.0, so those rows never match.
     *
     * @param \October\Rain\Database\Builder $obQuery
     * @return \October\Rain\Database\Builder
     */
    public function scopeLowConfidence($obQuery)
    {
        return $obQuery
            ->whereNull('family_override')
            ->where('confidence_score', '<', EffectiveColorResolver::LOW_CONFIDENCE_THRESHOLD);
    }

    /**
     * Rows whose effective family is one of the given family keys.
     *
     * @param \October\Rain\Database\Builder $obQuery
     * @param array<int, string> $arFamilyNames Family keys.
     * @return \October\Rain\Database\Builder
     */
    public function scopeEffectiveFamilyIn($obQuery, array $arFamilyNames)
    {
        return $obQuery->where(function ($obNested) use ($arFamilyNames) {
            $obNested->whereIn('family_override', $arFamilyNames)
                ->orWhere(function ($obAuto) use ($arFamilyNames) {
                    $obAuto->whereNull('family_override')->whereIn('taxonomy->family', $arFamilyNames);
                });
        });
    }

    /**
     * Adapter for the family filter: reads the picked families off the filter scope.
     *
     * @param \October\Rain\Database\Builder $obQuery
     * @param object $obFilterScope Backend filter scope holding the picked family keys.
     * @return \October\Rain\Database\Builder
     */
    public function scopeEffectiveFamilyFilter($obQuery, $obFilterScope)
    {
        return $this->scopeEffectiveFamilyIn($obQuery, array_values((array) $obFilterScope->value));
    }

    /**
     * Dropdown options for the family override field: active families, plus
     * the stored override when that family was switched off since.
     *
     * @return array<string, string>
     */
    public function getFamilyOverrideOptions(): array
    {
        $arFamilyNames = ColorFamily::listActiveFamilyNames();

        if ($this->family_override && !in_array($this->family_override, $arFamilyNames, true)) {
            $arFamilyNames[] = $this->family_override;
        }

        return array_combine($arFamilyNames, $arFamilyNames);
    }

    /**
     * Options for the family filter: every family in Families page order.
     *
     * @return array<string, string>
     */
    public function getFamilyFilterOptions(): array
    {
        $arFamilyNames = ColorFamily::listFamilyNamesInOrder();

        return array_combine($arFamilyNames, $arFamilyNames);
    }

    /**
     * afterSave drops the cached Color Lab payloads derived from this table.
     *
     * @return void
     */
    public function afterSave()
    {
        ColorLabPayloadCache::flushAfterCommit();
    }

    /**
     * afterDelete drops the cached Color Lab payloads derived from this table.
     *
     * @return void
     */
    public function afterDelete()
    {
        ColorLabPayloadCache::flushAfterCommit();
    }
}
