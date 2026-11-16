<?php

namespace Logingrupa\ColorClassifier\Classes;

use Illuminate\Support\Facades\DB;
use Logingrupa\ColorClassifier\Models\ColorEntry;
use Logingrupa\ColorClassifier\Models\ColorFamily;
use October\Rain\Exception\ValidationException;

/**
 * ColorEntryOverrideService - the one write path for manual overrides and locks.
 *
 * The Color entries list, its edit form and the Family board all call this
 * class, so the rules stay identical: a no-op override is stored as null, any
 * override locks the row, only active families are valid targets, and
 * unlocking keeps the overrides. Classifier columns are never written here.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class ColorEntryOverrideService
{
    /** @var int Most rows one bulk action may touch. */
    private const MAX_BULK_ENTRIES = 1000;

    /** @var OverrideNormalizer */
    private OverrideNormalizer $obNormalizer;

    /** @var array<int, string>|null Active family keys, loaded on first use. */
    private ?array $arActiveFamilyNames = null;

    /**
     * @param OverrideNormalizer|null $obNormalizer Rule set, injectable for tests.
     */
    public function __construct(?OverrideNormalizer $obNormalizer = null)
    {
        $this->obNormalizer = $obNormalizer ?? new OverrideNormalizer();
    }

    /**
     * Set override and lock on a loaded row without saving it.
     *
     * @param ColorEntry $obEntry Row to change.
     * @param string|null $sFamily Requested family override.
     * @param string|null $sHex Requested hex override.
     * @param bool $blLockRequested Lock switch as posted.
     *
     * @return void
     *
     * @throws ValidationException When the family is not active or the hex is malformed.
     */
    public function applyToEntry(ColorEntry $obEntry, ?string $sFamily, ?string $sHex, bool $blLockRequested): void
    {
        $arTaxonomy = $obEntry->taxonomy;
        $sAutoFamily = is_array($arTaxonomy) ? ($arTaxonomy['family'] ?? null) : null;

        try {
            $sFamilyOverride = $this->obNormalizer->normalizeFamilyOverride($sFamily, $sAutoFamily, $obEntry->family_override, $this->getActiveFamilyNames());
        } catch (\InvalidArgumentException $obException) {
            throw new ValidationException(['family_override' => $obException->getMessage()]);
        }

        try {
            $sHexOverride = $this->obNormalizer->normalizeHexOverride($sHex, $obEntry->hex_color);
        } catch (\InvalidArgumentException $obException) {
            throw new ValidationException(['hex_override' => $obException->getMessage()]);
        }

        $obEntry->family_override = $sFamilyOverride;
        $obEntry->hex_override = $sHexOverride;
        $obEntry->is_locked = $this->obNormalizer->resolveLock($blLockRequested, $sFamilyOverride, $sHexOverride);
    }

    /**
     * Save one row's override and lock.
     *
     * @return ColorEntry The saved row.
     */
    public function saveEntry(int $iEntryId, ?string $sFamily, ?string $sHex, bool $blLocked): ColorEntry
    {
        return $this->updateEntries([$iEntryId], function (ColorEntry $obEntry) use ($sFamily, $sHex, $blLocked): void {
            $this->applyToEntry($obEntry, $sFamily, $sHex, $blLocked);
        })[0];
    }

    /**
     * Move rows to a family: sets the family override and always locks.
     * Each row keeps its hex override.
     *
     * @param array<int, int|string> $arEntryIds Row ids.
     * @param string $sFamily Target family key.
     *
     * @return array<int, ColorEntry>
     */
    public function moveEntries(array $arEntryIds, string $sFamily): array
    {
        return $this->updateEntries($arEntryIds, function (ColorEntry $obEntry) use ($sFamily): void {
            $this->applyToEntry($obEntry, $sFamily, $obEntry->hex_override, true);
        });
    }

    /**
     * Lock rows so Process All skips them.
     *
     * @param array<int, int|string> $arEntryIds Row ids.
     *
     * @return array<int, ColorEntry>
     */
    public function lockEntries(array $arEntryIds): array
    {
        return $this->updateEntries($arEntryIds, static function (ColorEntry $obEntry): void {
            $obEntry->is_locked = true;
        });
    }

    /**
     * Unlock rows and keep their overrides.
     *
     * @param array<int, int|string> $arEntryIds Row ids.
     *
     * @return array<int, ColorEntry>
     */
    public function unlockEntries(array $arEntryIds): array
    {
        return $this->updateEntries($arEntryIds, static function (ColorEntry $obEntry): void {
            $obEntry->is_locked = false;
        });
    }

    /**
     * Clear both overrides and unlock.
     *
     * @param array<int, int|string> $arEntryIds Row ids.
     *
     * @return array<int, ColorEntry>
     */
    public function resetEntries(array $arEntryIds): array
    {
        return $this->updateEntries($arEntryIds, static function (ColorEntry $obEntry): void {
            $obEntry->family_override = null;
            $obEntry->hex_override = null;
            $obEntry->is_locked = false;
        });
    }

    /**
     * Validate ids, load the rows and save every change in one transaction.
     * ColorEntry::afterSave flushes the Color Lab payload cache once the transaction commits.
     *
     * @param array<int, int|string> $arEntryIds Row ids.
     * @param \Closure $fnChange Mutates one row.
     *
     * @return array<int, ColorEntry>
     *
     * @throws ValidationException When the ids are invalid or a row does not exist.
     */
    private function updateEntries(array $arEntryIds, \Closure $fnChange): array
    {
        $arValidIds = $this->validateEntryIds($arEntryIds);
        $arEntries = ColorEntry::withoutImageData()->whereIn('id', $arValidIds)->get()->all();

        if (count($arEntries) !== count($arValidIds)) {
            throw new ValidationException(['checked' => 'Some selected color entries no longer exist.']);
        }

        DB::transaction(static function () use ($arEntries, $fnChange): void {
            foreach ($arEntries as $obEntry) {
                $fnChange($obEntry);
                $obEntry->save();
            }
        });

        return $arEntries;
    }

    /**
     * @param array<int, int|string> $arEntryIds Raw ids from the request.
     *
     * @return array<int, int> Unique positive integer ids.
     *
     * @throws ValidationException When the list is empty, too long or holds a non-id.
     */
    private function validateEntryIds(array $arEntryIds): array
    {
        if ($arEntryIds === [] || count($arEntryIds) > self::MAX_BULK_ENTRIES) {
            throw new ValidationException(['checked' => 'Select between 1 and ' . self::MAX_BULK_ENTRIES . ' color entries.']);
        }

        $arValidIds = [];

        foreach ($arEntryIds as $mEntryId) {
            if (filter_var($mEntryId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new ValidationException(['checked' => 'Color entry ids must be positive integers.']);
            }

            $arValidIds[(int) $mEntryId] = (int) $mEntryId;
        }

        return array_values($arValidIds);
    }

    /**
     * @return array<int, string> Active family keys, loaded once per instance.
     */
    private function getActiveFamilyNames(): array
    {
        return $this->arActiveFamilyNames ??= ColorFamily::listActiveFamilyNames();
    }
}
