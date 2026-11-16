<?php

namespace Logingrupa\ColorClassifier\Classes;

use Illuminate\Support\Facades\DB;
use Logingrupa\ColorClassifier\Models\ColorEntry;
use Logingrupa\ColorClassifier\Models\ColorFamily;
use Logingrupa\ColorClassifier\Models\FamilyMembership;
use October\Rain\Exception\ValidationException;

/**
 * FamilyMembershipService - the one human write path for extra colors and collections.
 *
 * The primary color stays with ColorEntryOverrideService (a move out of the
 * primary calls it). Every other state change goes through
 * MembershipStateNormalizer, so a hand edit equal to 1C is stored as null and
 * the 1C sync keeps owning in_feed. Clone is addMemberships.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class FamilyMembershipService
{
    private ColorEntryOverrideService $obOverrideService;

    private MembershipStateNormalizer $obNormalizer;

    private OfferDataProvider $obOfferDataProvider;

    public function __construct(
        ?ColorEntryOverrideService $obOverrideService = null,
        ?MembershipStateNormalizer $obNormalizer = null,
        ?OfferDataProvider $obOfferDataProvider = null
    ) {
        $this->obOverrideService = $obOverrideService ?? new ColorEntryOverrideService();
        $this->obNormalizer = $obNormalizer ?? new MembershipStateNormalizer();
        $this->obOfferDataProvider = $obOfferDataProvider ?? new OfferDataProvider();
    }

    /**
     * Put entries into an extra color or a collection.
     *
     * @param array<int, int|string> $arEntryIds Color entry ids.
     * @param string $sFamilyKey Target family key.
     *
     * @throws ValidationException When the ids or the key are invalid, the color is inactive or is an entry's primary.
     */
    public function addMemberships(array $arEntryIds, string $sFamilyKey): void
    {
        $obFamily = $this->findFamily($sFamilyKey);
        $arEntries = $this->loadEntries($arEntryIds);
        $this->assertCanAdd($obFamily, $arEntries);
        $arShadeCodes = $this->loadShadeCodes($obFamily, $arEntries);

        DB::transaction(function () use ($obFamily, $arEntries, $arShadeCodes): void {
            $this->addToFamily($obFamily, $arEntries, $arShadeCodes);
            ColorLabPayloadCache::flushAfterCommit();
        });
    }

    /**
     * Take entries out of an extra color or a collection.
     *
     * @param array<int, int|string> $arEntryIds Color entry ids.
     * @param string $sFamilyKey Family key to leave.
     *
     * @throws ValidationException When the ids or the key are invalid or the family is an entry's primary.
     */
    public function removeMemberships(array $arEntryIds, string $sFamilyKey): void
    {
        $obFamily = $this->findFamily($sFamilyKey);
        $arEntries = $this->loadEntries($arEntryIds);

        foreach ($arEntries as $obEntry) {
            if ($this->isPrimaryOf($obFamily, $obEntry)) {
                throw new ValidationException(['family' => $sFamilyKey . ' is the primary color of ' . $obEntry->offer_id . ', move it to another color instead.']);
            }
        }

        DB::transaction(function () use ($obFamily, $arEntries): void {
            $this->removeFromFamily($obFamily, $arEntries);
            ColorLabPayloadCache::flushAfterCommit();
        });
    }

    /**
     * Move entries between two families of the same kind.
     *
     * From the primary color this changes the primary and locks the entry. From an
     * extra group only that membership moves.
     *
     * @param array<int, int|string> $arEntryIds Color entry ids.
     * @param string $sFromKey Family key the entries leave.
     * @param string $sToKey Family key the entries enter.
     *
     * @throws ValidationException When the kinds differ, an entry is not in the source or the target refuses it.
     */
    public function moveMemberships(array $arEntryIds, string $sFromKey, string $sToKey): void
    {
        $obFrom = $this->findFamily($sFromKey);
        $obTo = $this->findFamily($sToKey);

        if ($obFrom->kind !== $obTo->kind || $obFrom->id === $obTo->id) {
            throw new ValidationException(['family' => 'A move needs two different families of the same kind.']);
        }

        $arEntries = $this->loadEntries($arEntryIds);
        $arPrimaryEntries = array_filter($arEntries, fn (ColorEntry $obEntry): bool => $this->isPrimaryOf($obFrom, $obEntry));
        $arExtraEntries = array_diff_key($arEntries, $arPrimaryEntries);

        $this->assertAllInFamily($obFrom, $arExtraEntries);
        $this->assertCanAdd($obTo, $arExtraEntries);
        $arShadeCodes = $this->loadShadeCodes($obTo, $arExtraEntries);

        DB::transaction(function () use ($obTo, $sToKey, $arPrimaryEntries, $arExtraEntries, $obFrom, $arShadeCodes): void {
            if ($arPrimaryEntries !== []) {
                $this->obOverrideService->moveEntries(array_map(static fn (ColorEntry $obEntry): int => $obEntry->id, $arPrimaryEntries), $sToKey);
                $this->deleteRows($obTo, $arPrimaryEntries);
            }

            $this->removeFromFamily($obFrom, $arExtraEntries);
            $this->addToFamily($obTo, $arExtraEntries, $arShadeCodes);
            ColorLabPayloadCache::flushAfterCommit();
        });
    }

    /**
     * Drop the hand edits of collection memberships so 1C decides again.
     *
     * @param array<int, int|string> $arEntryIds Color entry ids.
     * @param string|null $sCollectionKey One collection, or null for every collection.
     *
     * @throws ValidationException When the key is not a collection.
     */
    public function resetToFeed(array $arEntryIds, ?string $sCollectionKey): void
    {
        $arEntries = $this->loadEntries($arEntryIds);
        $arFamilyIds = $this->listCollectionFamilyIds($sCollectionKey);

        DB::transaction(function () use ($arEntries, $arFamilyIds): void {
            $arRows = FamilyMembership::query()
                ->whereIn('family_id', $arFamilyIds)
                ->whereIn('offer_id', $this->listOfferIds($arEntries))
                ->whereNotNull('hand_state')
                ->get();

            foreach ($arRows as $obRow) {
                $this->applyState($obRow, $this->obNormalizer->normalizeReset((bool) $obRow->in_feed));
            }

            ColorLabPayloadCache::flushAfterCommit();
        });
    }

    /**
     * @return array<int, int> Collection family ids, one or all.
     */
    private function listCollectionFamilyIds(?string $sCollectionKey): array
    {
        if ($sCollectionKey === null) {
            return ColorFamily::query()->collections()->pluck('id')->map(static fn ($mId): int => (int) $mId)->all();
        }

        $obFamily = $this->findFamily($sCollectionKey);

        if ($obFamily->kind !== FamilyKind::COLLECTION) {
            throw new ValidationException(['family' => $sCollectionKey . ' is a color, only collections reset to 1C.']);
        }

        return [(int) $obFamily->id];
    }

    /**
     * @param array<int, ColorEntry> $arEntries
     * @param array<string, string|null> $arShadeCodes Shade code by offer id for new collection rows.
     */
    private function addToFamily(ColorFamily $obFamily, array $arEntries, array $arShadeCodes): void
    {
        $arRows = $this->loadRowsByOfferId($obFamily, $arEntries);

        foreach ($arEntries as $obEntry) {
            $obRow = $arRows[$obEntry->offer_id] ?? null;
            $this->applyState(
                $obRow ?? $this->newRow($obFamily, $obEntry, $arShadeCodes[$obEntry->offer_id] ?? null),
                $this->obNormalizer->normalizeAdd((bool) $obRow?->in_feed)
            );
        }
    }

    /**
     * Same tag source as the sync, read before the transaction because a cold parser cache fetches the feed.
     *
     * @param array<int, ColorEntry> $arEntries
     *
     * @return array<string, string|null> Empty for a color, which carries no shade code.
     */
    private function loadShadeCodes(ColorFamily $obFamily, array $arEntries): array
    {
        if ($obFamily->kind !== FamilyKind::COLLECTION) {
            return [];
        }

        $arTagOffers = $this->obOfferDataProvider->getCollectionTags()['offers'];
        $arShadeCodes = [];

        foreach ($arEntries as $obEntry) {
            $arShadeCodes[(string) $obEntry->offer_id] = $arTagOffers[$obEntry->offer_id]['shade_code'] ?? null;
        }

        return $arShadeCodes;
    }

    /**
     * @param array<int, ColorEntry> $arEntries
     */
    private function removeFromFamily(ColorFamily $obFamily, array $arEntries): void
    {
        $arRows = $this->loadRowsByOfferId($obFamily, $arEntries);

        foreach ($arEntries as $obEntry) {
            if (isset($arRows[$obEntry->offer_id])) {
                $this->applyState($arRows[$obEntry->offer_id], $this->obNormalizer->normalizeRemove((bool) $arRows[$obEntry->offer_id]->in_feed));
            }
        }
    }

    /**
     * Remove the rows outright. A color row is never in 1C, so no hand state is worth keeping.
     *
     * @param array<int, ColorEntry> $arEntries
     */
    private function deleteRows(ColorFamily $obFamily, array $arEntries): void
    {
        foreach ($this->loadRowsByOfferId($obFamily, $arEntries) as $obRow) {
            $obRow->delete();
        }
    }

    /**
     * @param array{hand_state: string|null, delete: bool} $arState
     */
    private function applyState(FamilyMembership $obRow, array $arState): void
    {
        if ($arState['delete']) {
            if ($obRow->exists) {
                $obRow->delete();
            }

            return;
        }

        $obRow->hand_state = $arState['hand_state'];
        $obRow->save();
    }

    private function newRow(ColorFamily $obFamily, ColorEntry $obEntry, ?string $sShadeCode): FamilyMembership
    {
        return new FamilyMembership([
            'family_id'  => $obFamily->id,
            'offer_id'   => $obEntry->offer_id,
            'in_feed'    => false,
            'shade_code' => $sShadeCode,
        ]);
    }

    /**
     * @param array<int, ColorEntry> $arEntries
     *
     * @return array<string, FamilyMembership> Existing rows by offer id.
     */
    private function loadRowsByOfferId(ColorFamily $obFamily, array $arEntries): array
    {
        return FamilyMembership::query()
            ->where('family_id', $obFamily->id)
            ->whereIn('offer_id', $this->listOfferIds($arEntries))
            ->get()
            ->keyBy('offer_id')
            ->all();
    }

    /**
     * @param array<int, ColorEntry> $arEntries
     *
     * @return array<int, string>
     */
    private function listOfferIds(array $arEntries): array
    {
        return array_values(array_map(static fn (ColorEntry $obEntry): string => (string) $obEntry->offer_id, $arEntries));
    }

    /**
     * @param array<int, ColorEntry> $arEntries
     *
     * @throws ValidationException When a color is inactive or is the primary of an entry.
     */
    private function assertCanAdd(ColorFamily $obFamily, array $arEntries): void
    {
        if ($obFamily->kind !== FamilyKind::COLOR) {
            return;
        }

        if (!$obFamily->is_active) {
            throw new ValidationException(['family' => $obFamily->family . ' is not active.']);
        }

        foreach ($arEntries as $obEntry) {
            if ($this->isPrimaryOf($obFamily, $obEntry)) {
                throw new ValidationException(['family' => $obFamily->family . ' is already the primary color of ' . $obEntry->offer_id . '.']);
            }
        }
    }

    /**
     * @param array<int, ColorEntry> $arEntries
     *
     * @throws ValidationException When an entry is not an effective member of the family.
     */
    private function assertAllInFamily(ColorFamily $obFamily, array $arEntries): void
    {
        $arRows = $this->loadRowsByOfferId($obFamily, $arEntries);

        foreach ($arEntries as $obEntry) {
            $obRow = $arRows[$obEntry->offer_id] ?? null;

            if ($obRow === null || !$this->obNormalizer->isEffective($obRow->hand_state, (bool) $obRow->in_feed)) {
                throw new ValidationException(['checked' => $obEntry->offer_id . ' is not in ' . $obFamily->family . '.']);
            }
        }
    }

    private function isPrimaryOf(ColorFamily $obFamily, ColorEntry $obEntry): bool
    {
        return $obFamily->kind === FamilyKind::COLOR && EffectiveColorResolver::resolve($obEntry)['family'] === $obFamily->family;
    }

    /**
     * @throws ValidationException When no family has the key.
     */
    private function findFamily(string $sFamilyKey): ColorFamily
    {
        $obFamily = ColorFamily::query()->where('family', $sFamilyKey)->first();

        if ($obFamily === null) {
            throw new ValidationException(['family' => 'Unknown family: ' . $sFamilyKey . '.']);
        }

        return $obFamily;
    }

    /**
     * @param array<int, int|string> $arEntryIds Raw ids from the request.
     *
     * @return array<int, ColorEntry> Entries keyed by id.
     *
     * @throws ValidationException When an id is invalid or a row does not exist.
     */
    private function loadEntries(array $arEntryIds): array
    {
        $arValidIds = $this->obOverrideService->validateEntryIds($arEntryIds);
        $arEntries = ColorEntry::withoutImageData()->whereIn('id', $arValidIds)->get()->keyBy('id')->all();

        if (count($arEntries) !== count($arValidIds)) {
            throw new ValidationException(['checked' => 'Some selected color entries no longer exist.']);
        }

        return $arEntries;
    }
}
