<?php

namespace Logingrupa\ColorClassifier\Classes;

use Illuminate\Support\Facades\DB;
use Logingrupa\ColorClassifier\Models\ColorFamily;
use System\Models\Parameter;

/**
 * CollectionFeedSync - mirrors the 1C collection tags into families and memberships.
 *
 * The command and the Families page button share this one service. It reads the
 * tags from the warm parser cache, asks CollectionFeedPlanner for the plan and,
 * unless it is a dry run, applies the plan in one transaction. It writes only
 * the sync-owned columns (in_feed, shade_code, feed_value_name); hand_state is
 * never touched.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class CollectionFeedSync
{
    /** System Parameter holding the offer count of the last applied run. */
    public const LAST_OFFER_COUNT_PARAMETER = 'logingrupa.colorclassifier::collection_sync.offer_count';

    private const FAMILY_TABLE = 'logingrupa_colorclassifier_color_families';

    private const MEMBERSHIP_TABLE = 'logingrupa_colorclassifier_family_memberships';

    private const INSERT_CHUNK_SIZE = 500;

    private OfferDataProvider $obOfferDataProvider;

    private FamilyMembershipQuery $obMembershipQuery;

    public function __construct(?OfferDataProvider $obOfferDataProvider = null, ?FamilyMembershipQuery $obMembershipQuery = null)
    {
        $this->obOfferDataProvider = $obOfferDataProvider ?? new OfferDataProvider();
        $this->obMembershipQuery = $obMembershipQuery ?? new FamilyMembershipQuery();
    }

    /**
     * @param bool $blDryRun Plan only, write nothing.
     * @param bool $blAllowEmpty Allow a collection to lose its last 1C member.
     *
     * @return CollectionFeedPlan The plan that was applied, or the aborted plan with its reason.
     */
    public function run(bool $blDryRun, bool $blAllowEmpty): CollectionFeedPlan
    {
        $arTags = $this->obOfferDataProvider->getCollectionTags();
        $obPlan = (new CollectionFeedPlanner($this->obOfferDataProvider->getCollectionCharacteristicId()))->plan(
            $arTags,
            $this->obMembershipQuery->loadFamilyRows(),
            $this->obMembershipQuery->loadMembershipRows(),
            $this->readLastOfferCount(),
            $blAllowEmpty
        );

        if ($blDryRun || $obPlan->isAborted()) {
            return $obPlan;
        }

        DB::transaction(function () use ($obPlan): void {
            $this->apply($obPlan);
        });

        Parameter::set(self::LAST_OFFER_COUNT_PARAMETER, $arTags['offer_count']);

        return $obPlan;
    }

    private function readLastOfferCount(): ?int
    {
        $mCount = Parameter::get(self::LAST_OFFER_COUNT_PARAMETER);

        return $mCount === null ? null : (int) $mCount;
    }

    private function apply(CollectionFeedPlan $obPlan): void
    {
        foreach ($obPlan->arFamilyDrafts as $arDraft) {
            ColorFamily::create($arDraft);
        }

        $this->applyRenames($obPlan->arFamilyRenames);
        $this->applyMembershipSets($obPlan->arMembershipSets);
        $this->applyMembershipUnsets($obPlan->arMembershipUnsets);
        $this->applyMembershipDeletes($obPlan->arMembershipDeletes);
        $this->applyShadeUpdates($obPlan->arShadeUpdates);

        if ($obPlan->countWrites() > 0) {
            ColorLabPayloadCache::flushAfterCommit();
        }
    }

    /**
     * @param array<int, array{value_id: string, feed_value_name: string}> $arRenames
     */
    private function applyRenames(array $arRenames): void
    {
        foreach ($arRenames as $arRename) {
            DB::table(self::FAMILY_TABLE)
                ->where('feed_value_id', $arRename['value_id'])
                ->update(['feed_value_name' => $arRename['feed_value_name'], 'updated_at' => now()]);
        }
    }

    /**
     * @param array<int, array{value_id: string, offer_id: string, shade_code: string|null, row_id: int|null}> $arSets
     */
    private function applyMembershipSets(array $arSets): void
    {
        $arFamilyIdByValueId = DB::table(self::FAMILY_TABLE)->where('kind', FamilyKind::COLLECTION)->pluck('id', 'feed_value_id')->all();
        $arInserts = [];

        foreach ($arSets as $arSet) {
            if ($arSet['row_id'] !== null) {
                DB::table(self::MEMBERSHIP_TABLE)
                    ->where('id', $arSet['row_id'])
                    ->update(['in_feed' => true, 'shade_code' => $arSet['shade_code'], 'updated_at' => now()]);
                continue;
            }

            $arInserts[] = [
                'family_id'  => $arFamilyIdByValueId[$arSet['value_id']],
                'offer_id'   => $arSet['offer_id'],
                'in_feed'    => true,
                'hand_state' => null,
                'shade_code' => $arSet['shade_code'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($arInserts, self::INSERT_CHUNK_SIZE) as $arChunk) {
            DB::table(self::MEMBERSHIP_TABLE)->insert($arChunk);
        }
    }

    /**
     * @param array<int, array{row_id: int}> $arUnsets
     */
    private function applyMembershipUnsets(array $arUnsets): void
    {
        if ($arUnsets !== []) {
            DB::table(self::MEMBERSHIP_TABLE)
                ->whereIn('id', array_column($arUnsets, 'row_id'))
                ->update(['in_feed' => false, 'updated_at' => now()]);
        }
    }

    /**
     * @param array<int, array{row_id: int}> $arDeletes
     */
    private function applyMembershipDeletes(array $arDeletes): void
    {
        if ($arDeletes !== []) {
            DB::table(self::MEMBERSHIP_TABLE)->whereIn('id', array_column($arDeletes, 'row_id'))->delete();
        }
    }

    /**
     * @param array<int, array{row_id: int, shade_code: string|null}> $arShadeUpdates
     */
    private function applyShadeUpdates(array $arShadeUpdates): void
    {
        foreach ($arShadeUpdates as $arShadeUpdate) {
            DB::table(self::MEMBERSHIP_TABLE)
                ->where('id', $arShadeUpdate['row_id'])
                ->update(['shade_code' => $arShadeUpdate['shade_code'], 'updated_at' => now()]);
        }
    }
}
