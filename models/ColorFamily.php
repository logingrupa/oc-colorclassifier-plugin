<?php

namespace Logingrupa\ColorClassifier\Models;

use Model;
use October\Rain\Exception\ApplicationException;
use October\Rain\Exception\ValidationException;
use Logingrupa\ColorClassifier\Classes\CollectionFeedPlanner;
use Logingrupa\ColorClassifier\Classes\CollectionHandSwitch;
use Logingrupa\ColorClassifier\Classes\ColorLabPayloadCache;
use Logingrupa\ColorClassifier\Classes\FamilyKind;
use Logingrupa\ColorClassifier\Classes\GradientNormalizer;

/**
 * ColorFamily Model - one editable color family: names, synonyms, hex and order.
 *
 * Feeds families.json and the Color Lab family list. The family key and slug
 * are fixed after create because shop catalogs and ?color= URLs depend on
 * them, and a family is retired with is_active instead of being deleted
 * because shop sync removes rows whose slug leaves families.json.
 *
 * A row is a color or a collection. Collections come only from the 1C sync
 * (kind and the feed link are fixed at create) and never serve as a primary
 * color, so the color lists below skip them.
 *
 * @package Logingrupa\ColorClassifier\Models
 */
class ColorFamily extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    /** @var string Database table name. */
    protected $table = 'logingrupa_colorclassifier_color_families';

    /** @var array<int, string> Attributes stored as JSON. */
    protected $jsonable = ['names', 'synonyms', 'gradient'];

    /** @var array<string, mixed> Defaults for a new row, so the Create form always makes a color. */
    public $attributes = [
        'kind'           => FamilyKind::COLOR,
        'gradient_type'  => 'linear',
        'gradient_angle' => 90,
    ];

    /** @var array<int, string> Attributes that are mass assignable. */
    protected $fillable = [
        'family',
        'slug',
        'kind',
        'feed_characteristic_id',
        'feed_value_id',
        'feed_value_name',
        'hex',
        'gradient',
        'gradient_type',
        'gradient_angle',
        'names',
        'synonyms',
        'sort_order',
        'is_active',
    ];

    /** @var array<string, string> Attributes cast to native types. */
    protected $casts = [
        'is_active'        => 'boolean',
        'is_hand_disabled' => 'boolean',
        'sort_order'       => 'integer',
        'gradient_angle'   => 'integer',
    ];

    /** @var bool|null Active value the Families form was rendered with, null outside the form. */
    private ?bool $blRenderedActive = null;

    /** @var array<string, mixed> Validation rules. */
    public $rules = [
        'family'         => ['required', 'max:' . CollectionFeedPlanner::KEY_MAX_LENGTH, 'regex:' . CollectionFeedPlanner::KEY_PATTERN, 'unique'],
        'slug'           => ['required', 'max:64', 'regex:/^[a-z0-9-]+$/', 'unique'],
        'kind'           => ['required', 'in:color,collection'],
        'feed_value_id'  => ['nullable', 'max:36', 'unique', 'required_if:kind,collection', 'prohibited_if:kind,color'],
        'hex'            => ['required', 'regex:/^#[0-9a-f]{6}$/'],
        'gradient_type'  => ['required', 'in:linear,radial,conic'],
        'gradient_angle' => ['required', 'integer', 'between:0,359'],
        'names.en'       => 'required',
    ];

    /** @var array<string, string> Validation messages. */
    public $customMessages = [
        'family.regex' => 'The family key must start with a letter and use only English letters, digits, spaces and hyphens.',
        'slug.regex' =>'The slug may only contain lowercase letters, digits and hyphens.',
        'hex.regex'  => 'The hex color must look like #d32f2f.',
    ];

    /**
     * @return array<int, array{stop: string}> The gradient as repeater items, one per stop.
     */
    public function getGradientStopsAttribute(): array
    {
        return array_map(static fn (string $sStop): array => ['stop' => $sStop], (array) $this->gradient);
    }

    /**
     * Maps the repeater items back to the stored list of stops. Validation runs in beforeValidate.
     *
     * @param mixed $mItems Repeater items, each holding a stop. An empty repeater posts an empty string.
     *
     * @return void
     */
    public function setGradientStopsAttribute($mItems): void
    {
        $this->gradient = array_values(array_map(
            static fn (array $arItem): string => (string) ($arItem['stop'] ?? ''),
            array_filter((array) $mItems, 'is_array')
        ));
    }

    /**
     * Cache flush also covers drag-and-drop reordering, which writes
     * sort_order through the query builder and skips afterSave.
     *
     * @return void
     */
    public function __construct(array $arAttributes = [])
    {
        parent::__construct($arAttributes);

        $this->bindEvent('model.setSortableOrder', function () {
            ColorLabPayloadCache::flushAfterCommit();
        });
    }

    /**
     * The key, slug, kind and 1C link inputs are read-only once the family exists.
     *
     * @param object $obFields Form field definitions.
     * @param string|null $sContext Form context.
     *
     * @return void
     */
    public function filterFields($obFields, $sContext = null)
    {
        if ($sContext !== 'update') {
            return;
        }

        foreach (['family', 'slug', 'kind', 'feed_characteristic_id', 'feed_value_id'] as $sFieldName) {
            if (isset($obFields->{$sFieldName})) {
                $obFields->{$sFieldName}->disabled = true;
            }
        }
    }

    /**
     * @return void
     *
     * @throws ValidationException When the gradient breaks the GradientNormalizer rules.
     */
    public function beforeValidate()
    {
        $this->family = trim((string) $this->family);
        $this->slug = trim((string) $this->slug);
        $this->hex = strtolower(trim((string) $this->hex));

        try {
            $arGradient = (new GradientNormalizer())->normalize($this->gradient, $this->gradient_type, $this->gradient_angle);
        } catch (\InvalidArgumentException $obException) {
            throw new ValidationException(['gradient' => $obException->getMessage()]);
        }

        $this->gradient = $arGradient['stops'] ?? null;
    }

    /**
     * The Families form posts the Active value it was rendered with, so a save
     * from a form opened before a sync run keeps the value the sync wrote.
     *
     * @param mixed $mValue Posted "0" or "1".
     *
     * @return void
     */
    public function setIsActiveRenderedAttribute($mValue): void
    {
        $this->blRenderedActive = (bool) $mValue;
    }

    /**
     * A collection switched off through the model is switched off by hand, so the
     * 1C sync keeps it off. The sync writes its own switches past the model.
     *
     * @return void
     */
    public function beforeSave()
    {
        $arSwitch = CollectionHandSwitch::resolve(
            FamilyKind::normalize($this->kind),
            $this->exists ? (bool) $this->getOriginal('is_active') : null,
            (bool) $this->is_active,
            $this->blRenderedActive
        );

        if ($arSwitch === null) {
            return;
        }

        $this->is_active = $arSwitch['is_active'];

        if ($arSwitch['is_hand_disabled'] !== null) {
            $this->is_hand_disabled = $arSwitch['is_hand_disabled'];
        }
    }

    /**
     * New families go to the end of the list.
     *
     * @return void
     */
    public function beforeCreate()
    {
        if (!$this->sort_order) {
            $this->sort_order = (int) static::withoutGlobalScopes()->max('sort_order') + 1;
        }
    }

    /**
     * @return void
     *
     * @throws ValidationException When the family key or slug changed.
     */
    public function beforeUpdate()
    {
        if ($this->isDirty('family') || $this->isDirty('slug')) {
            throw new ValidationException([
                'family' => 'The family key and slug are locked because nc shops and ?color= URLs depend on them.',
            ]);
        }

        if ($this->isDirty('kind') || $this->isDirty('feed_characteristic_id') || $this->isDirty('feed_value_id')) {
            throw new ValidationException([
                'kind' => 'The kind and the 1C link are fixed when the family is created.',
            ]);
        }
    }

    /**
     * @return void
     *
     * @throws ApplicationException Always: families are retired, never deleted.
     */
    public function beforeDelete()
    {
        throw new ApplicationException('Families cannot be deleted. Switch Active off instead.');
    }

    /**
     * @return void
     */
    public function afterSave()
    {
        ColorLabPayloadCache::flushAfterCommit();
    }

    /**
     * @param \October\Rain\Database\Builder $obQuery
     *
     * @return \October\Rain\Database\Builder
     */
    public function scopeColors($obQuery)
    {
        return $obQuery->where('kind', FamilyKind::COLOR);
    }

    /**
     * @param \October\Rain\Database\Builder $obQuery
     *
     * @return \October\Rain\Database\Builder
     */
    public function scopeCollections($obQuery)
    {
        return $obQuery->where('kind', FamilyKind::COLLECTION);
    }

    /**
     * @return array<int, string> Every color key in Families page order.
     */
    public static function listFamilyNamesInOrder(): array
    {
        return static::query()->colors()->orderBy('sort_order')->orderBy('id')->pluck('family')->all();
    }

    /**
     * @return array<int, string> Active color keys in Families page order.
     */
    public static function listActiveFamilyNames(): array
    {
        return static::query()->colors()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->pluck('family')->all();
    }
}
