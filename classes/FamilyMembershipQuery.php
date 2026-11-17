<?php

namespace Logingrupa\ColorClassifier\Classes;

use Illuminate\Support\Facades\DB;

/**
 * FamilyMembershipQuery - loads family and membership rows as plain arrays.
 *
 * The sync, the export route and the membership service all read the same two
 * tables through here, so the pure planner and index get one row shape.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class FamilyMembershipQuery
{
    private const FAMILY_TABLE = 'logingrupa_colorclassifier_color_families';

    private const MEMBERSHIP_TABLE = 'logingrupa_colorclassifier_family_memberships';

    /**
     * @return array<int, array<string, mixed>> Every family row of both kinds, in display order.
     */
    public function loadFamilyRows(): array
    {
        return $this->toArrayList(
            DB::table(self::FAMILY_TABLE)
                ->select(['id', 'family', 'slug', 'kind', 'is_active', 'is_hand_disabled', 'sort_order', 'feed_value_id', 'feed_value_name'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
        );
    }

    /**
     * @return array<int, array<string, mixed>> Every membership row.
     */
    public function loadMembershipRows(): array
    {
        return $this->toArrayList(
            DB::table(self::MEMBERSHIP_TABLE)
                ->select(['id', 'family_id', 'offer_id', 'in_feed', 'hand_state', 'shade_code'])
                ->orderBy('id')
                ->get()
        );
    }

    /**
     * @return FamilyMembershipIndex Export index over the current rows.
     */
    public function buildIndex(): FamilyMembershipIndex
    {
        return new FamilyMembershipIndex($this->loadFamilyRows(), $this->loadMembershipRows());
    }

    /**
     * @param iterable<int, object> $obRows Query builder rows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function toArrayList(iterable $obRows): array
    {
        $arRows = [];

        foreach ($obRows as $obRow) {
            $arRows[] = (array) $obRow;
        }

        return $arRows;
    }
}
