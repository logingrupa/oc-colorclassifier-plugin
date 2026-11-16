<?php

use Logingrupa\ColorClassifier\Classes\FamilyMembershipIndex;
use PHPUnit\Framework\TestCase;

/**
 * IDX-01: the export index lists the primary first, then active effective
 * memberships in display order, and reads the shade code from membership rows.
 */
class FamilyMembershipIndexTest extends TestCase
{
    private const OFFER = 'p1#offer-1';

    /**
     * @return array<int, array<string, mixed>>
     */
    private function makeFamilies(): array
    {
        return [
            ['id' => 1, 'family' => 'Red', 'is_active' => true, 'sort_order' => 1],
            ['id' => 2, 'family' => 'Pink', 'is_active' => true, 'sort_order' => 2],
            ['id' => 3, 'family' => 'NEON', 'is_active' => true, 'sort_order' => 3],
            ['id' => 4, 'family' => 'DISCO', 'is_active' => true, 'sort_order' => 3],
            ['id' => 5, 'family' => 'Draft', 'is_active' => false, 'sort_order' => 4],
        ];
    }

    /**
     * @param array<string, mixed> $arOverrides
     *
     * @return array<string, mixed>
     */
    private function makeMembership(int $iFamilyId, array $arOverrides = []): array
    {
        return array_merge([
            'family_id'  => $iFamilyId,
            'offer_id'   => self::OFFER,
            'in_feed'    => true,
            'hand_state' => null,
            'shade_code' => null,
        ], $arOverrides);
    }

    public function test_families_list_primary_first_then_members_in_display_order(): void
    {
        $obIndex = new FamilyMembershipIndex($this->makeFamilies(), [
            $this->makeMembership(4),
            $this->makeMembership(3),
            $this->makeMembership(2, ['in_feed' => false, 'hand_state' => 'added']),
        ]);

        $this->assertSame(['Red', 'Pink', 'NEON', 'DISCO'], $obIndex->listFamilyNames(self::OFFER, 'Red'));
    }

    public function test_primary_is_never_repeated_and_inactive_families_are_left_out(): void
    {
        $obIndex = new FamilyMembershipIndex($this->makeFamilies(), [
            $this->makeMembership(2, ['in_feed' => false, 'hand_state' => 'added']),
            $this->makeMembership(5),
        ]);

        $this->assertSame(['Pink'], $obIndex->listFamilyNames(self::OFFER, 'Pink'));
    }

    public function test_removed_and_unfed_rows_are_not_members(): void
    {
        $obIndex = new FamilyMembershipIndex($this->makeFamilies(), [
            $this->makeMembership(3, ['hand_state' => 'removed']),
            $this->makeMembership(4, ['in_feed' => false]),
        ]);

        $this->assertSame(['Red'], $obIndex->listFamilyNames(self::OFFER, 'Red'));
    }

    public function test_offer_without_rows_lists_only_its_primary_and_has_no_shade_code(): void
    {
        $obIndex = new FamilyMembershipIndex($this->makeFamilies(), [$this->makeMembership(3)]);

        $this->assertSame(['Blue'], $obIndex->listFamilyNames('p1#other', 'Blue'));
        $this->assertNull($obIndex->findShadeCode('p1#other'));
    }

    public function test_shade_code_is_the_first_non_null_across_the_offer_rows(): void
    {
        $obIndex = new FamilyMembershipIndex($this->makeFamilies(), [
            $this->makeMembership(3),
            $this->makeMembership(4, ['shade_code' => 'DISCO 30']),
            $this->makeMembership(2, ['shade_code' => 'DISCO 31', 'in_feed' => false, 'hand_state' => 'removed']),
        ]);

        $this->assertSame('DISCO 30', $obIndex->findShadeCode(self::OFFER));
    }

    public function test_empty_index_lists_only_the_primary(): void
    {
        $obIndex = new FamilyMembershipIndex();

        $this->assertSame(['Red'], $obIndex->listFamilyNames(self::OFFER, 'Red'));
        $this->assertNull($obIndex->findShadeCode(self::OFFER));
    }
}
