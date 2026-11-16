<?php

namespace Logingrupa\ColorClassifier\Models;

use Model;
use October\Rain\Exception\ApplicationException;
use October\Rain\Exception\ValidationException;
use Logingrupa\ColorClassifier\Classes\ColorLabPayloadCache;

/**
 * ColorFamily Model - one editable color family: names, synonyms, hex and order.
 *
 * Feeds families.json and the Color Lab family list. The family key and slug
 * are fixed after create because shop catalogs and ?color= URLs depend on
 * them, and a family is retired with is_active instead of being deleted
 * because shop sync removes rows whose slug leaves families.json.
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
    protected $jsonable = ['names', 'synonyms'];

    /** @var array<int, string> Attributes that are mass assignable. */
    protected $fillable = [
        'family',
        'slug',
        'hex',
        'names',
        'synonyms',
        'sort_order',
        'is_active',
    ];

    /** @var array<string, string> Attributes cast to native types. */
    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @var array<string, mixed> Validation rules. */
    public $rules = [
        'family'   => ['required', 'max:64', 'unique'],
        'slug'     => ['required', 'max:64', 'regex:/^[a-z0-9-]+$/', 'unique'],
        'hex'      => ['required', 'regex:/^#[0-9a-f]{6}$/'],
        'names.en' => 'required',
    ];

    /** @var array<string, string> Validation messages. */
    public $customMessages = [
        'slug.regex' => 'The slug may only contain lowercase letters, digits and hyphens.',
        'hex.regex'  => 'The hex color must look like #d32f2f.',
    ];

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
            ColorLabPayloadCache::flush();
        });
    }

    /**
     * The key and slug inputs are read-only once the family exists.
     *
     * @param object $obFields Form field definitions.
     * @param string|null $sContext Form context.
     *
     * @return void
     */
    public function filterFields($obFields, $sContext = null)
    {
        if ($sContext === 'update') {
            $obFields->family->disabled = true;
            $obFields->slug->disabled = true;
        }
    }

    /**
     * @return void
     */
    public function beforeValidate()
    {
        $this->family = trim((string) $this->family);
        $this->slug = trim((string) $this->slug);
        $this->hex = strtolower(trim((string) $this->hex));
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
        ColorLabPayloadCache::flush();
    }

    /**
     * @return array<int, string> Every family key in Families page order.
     */
    public static function listFamilyNamesInOrder(): array
    {
        return static::query()->orderBy('sort_order')->orderBy('id')->pluck('family')->all();
    }

    /**
     * @return array<int, string> Active family keys in Families page order.
     */
    public static function listActiveFamilyNames(): array
    {
        return static::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->pluck('family')->all();
    }
}
