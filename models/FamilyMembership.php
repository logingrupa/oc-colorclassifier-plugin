<?php

namespace Logingrupa\ColorClassifier\Models;

use Model;

/**
 * FamilyMembership Model - one offer in one extra color or collection.
 *
 * in_feed and shade_code belong to the 1C sync, hand_state ('added' or
 * 'removed') to people. offer_id is the full 1C composite id, the same value
 * as color_entries.offer_id, so image-less offers are covered and rows outlive
 * entry deletes.
 *
 * @package Logingrupa\ColorClassifier\Models
 */
class FamilyMembership extends Model
{
    /** @var string Database table name. */
    protected $table = 'logingrupa_colorclassifier_family_memberships';

    /** @var array<int, string> Attributes that are mass assignable. */
    protected $fillable = [
        'family_id',
        'offer_id',
        'in_feed',
        'hand_state',
        'shade_code',
    ];

    /** @var array<string, string> Attributes cast to native types. */
    protected $casts = [
        'family_id' => 'integer',
        'in_feed'   => 'boolean',
    ];

    /** @var array<string, array<int, mixed>> */
    public $belongsTo = [
        'family' => [ColorFamily::class, 'key' => 'family_id'],
    ];
}
