<?php

use Logingrupa\ColorClassifier\Classes\CollectionFeedPlan;
use Logingrupa\ColorClassifier\Classes\CollectionFeedPlanner;
use Logingrupa\ColorClassifier\Classes\CollectionHandSwitch;
use Logingrupa\ColorClassifier\Classes\FamilyKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PLAN-01..05: the pure planner mirrors 1C collection tags into drafts and
 * memberships, switches a collection off when 1C stops carrying it and on when
 * it returns unless it was switched off by hand, is idempotent, aborts before
 * writing on a suspect catalog and never touches hand_state.
 */
class CollectionFeedPlannerTest extends TestCase
{
    private const CHARACTERISTIC_ID = '0a80e428-7204-11f0-8ae8-cc5ef85a3bbc';

    private const DISCO = ['value_id' => 'v-disco', 'value' => 'DISCO'];

    private const NEON = ['value_id' => 'v-neon', 'value' => 'NEON'];

    private CollectionFeedPlanner $obPlanner;

    protected function setUp(): void
    {
        $this->obPlanner = new CollectionFeedPlanner(self::CHARACTERISTIC_ID);
    }

    /**
     * @param array<string, array{shade_code: string|null, collections: array<int, array<string, string>>}> $arOffers
     *
     * @return array<string, mixed>
     */
    private function makeTags(array $arOffers, ?int $iOfferCount = null, bool $blHasKey = true): array
    {
        return ['has_collection_key' => $blHasKey, 'offer_count' => $iOfferCount ?? count($arOffers), 'offers' => $arOffers];
    }

    /**
     * 33 DISCO offers and 3 NEON offers, plus untagged filler up to 60 offers.
     *
     * @return array<string, mixed>
     */
    private function makeSnapshotTags(): array
    {
        $arOffers = [];

        for ($iIndex = 1; $iIndex <= 33; $iIndex++) {
            $arOffers['p#disco-' . $iIndex] = ['shade_code' => 'DISCO ' . $iIndex, 'collections' => [self::DISCO]];
        }

        for ($iIndex = 1; $iIndex <= 3; $iIndex++) {
            $arOffers['p#neon-' . $iIndex] = ['shade_code' => 'NEON ' . (1000 + $iIndex), 'collections' => [self::NEON]];
        }

        for ($iIndex = 1; $iIndex <= 24; $iIndex++) {
            $arOffers['p#plain-' . $iIndex] = ['shade_code' => null, 'collections' => []];
        }

        return $this->makeTags($arOffers);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function makeColorRows(): array
    {
        return [
            ['id' => 1, 'family' => 'Red', 'slug' => 'red', 'kind' => 'color', 'sort_order' => 1, 'feed_value_id' => null, 'feed_value_name' => null],
            ['id' => 2, 'family' => 'Pink', 'slug' => 'pink', 'kind' => 'color', 'sort_order' => 7, 'feed_value_id' => null, 'feed_value_name' => null],
        ];
    }

    /**
     * Apply a plan to in-memory rows the way the sync service will write them.
     *
     * @param array<int, array<string, mixed>> $arFamilies
     * @param array<int, array<string, mixed>> $arMemberships
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function applyPlan(CollectionFeedPlan $obPlan, array $arFamilies, array $arMemberships): array
    {
        $iNextFamilyId = (int) max(array_column($arFamilies, 'id')) + 1;

        foreach ($obPlan->arFamilyDrafts as $arDraft) {
            $arFamilies[] = array_merge($arDraft, ['id' => $iNextFamilyId++, 'is_hand_disabled' => false]);
        }

        foreach ($obPlan->arFamilyRenames as $arRename) {
            foreach ($arFamilies as $iPosition => $arFamily) {
                if (($arFamily['feed_value_id'] ?? null) === $arRename['value_id']) {
                    $arFamilies[$iPosition]['feed_value_name'] = $arRename['feed_value_name'];
                }
            }
        }

        foreach ($obPlan->arFamilySwitches as $arSwitch) {
            foreach ($arFamilies as $iPosition => $arFamily) {
                if ($arFamily['id'] === $arSwitch['row_id']) {
                    $arFamilies[$iPosition]['is_active'] = $arSwitch['is_active'];
                }
            }
        }

        $arFamilyIdByValueId = array_column($arFamilies, 'id', 'feed_value_id');
        $iNextRowId = (int) max(array_merge([0], array_column($arMemberships, 'id'))) + 1;
        $arRowsById = array_column($arMemberships, null, 'id');

        foreach ($obPlan->arMembershipSets as $arSet) {
            $iRowId = $arSet['row_id'] ?? $iNextRowId++;
            $arRowsById[$iRowId] = array_merge(
                $arRowsById[$iRowId] ?? ['id' => $iRowId, 'family_id' => $arFamilyIdByValueId[$arSet['value_id']], 'offer_id' => $arSet['offer_id'], 'hand_state' => null],
                ['in_feed' => true, 'shade_code' => $arSet['shade_code']]
            );
        }

        foreach ($obPlan->arMembershipUnsets as $arUnset) {
            $arRowsById[$arUnset['row_id']]['in_feed'] = false;
        }

        foreach ($obPlan->arShadeUpdates as $arShade) {
            $arRowsById[$arShade['row_id']]['shade_code'] = $arShade['shade_code'];
        }

        foreach ($obPlan->arMembershipDeletes as $arDelete) {
            unset($arRowsById[$arDelete['row_id']]);
        }

        return [$arFamilies, array_values($arRowsById)];
    }

    public function test_snapshot_plans_two_active_collections_and_every_feed_membership(): void
    {
        $obPlan = $this->obPlanner->plan($this->makeSnapshotTags(), $this->makeColorRows(), [], null);

        $this->assertFalse($obPlan->isAborted());
        $this->assertSame(
            ['create' => 2, 'rename' => 0, 'set' => 36, 'unset' => 0, 'delete' => 0, 'shade' => 0, 'on' => 0, 'off' => 0],
            $obPlan->countSummary()
        );
        $this->assertSame(38, $obPlan->countWrites());
        $this->assertSame([], $obPlan->arFamilySwitches);

        [$arDisco, $arNeon] = $obPlan->arFamilyDrafts;
        $this->assertSame('DISCO', $arDisco['family']);
        $this->assertSame('disco', $arDisco['slug']);
        $this->assertSame('collection', $arDisco['kind']);
        $this->assertSame('#9e9e9e', $arDisco['hex']);
        $this->assertTrue($arDisco['is_active']);
        $this->assertTrue($arNeon['is_active']);
        $this->assertSame(8, $arDisco['sort_order']);
        $this->assertSame(9, $arNeon['sort_order']);
        $this->assertSame(['en' => 'DISCO', 'lv' => 'DISCO', 'ru' => 'DISCO', 'lt' => 'DISCO', 'nb-no' => 'DISCO'], $arDisco['names']);
        $this->assertSame(['en' => [], 'lv' => [], 'ru' => [], 'lt' => [], 'nb-no' => []], $arDisco['synonyms']);
        $this->assertSame(self::CHARACTERISTIC_ID, $arDisco['feed_characteristic_id']);
        $this->assertSame('v-disco', $arDisco['feed_value_id']);
        $this->assertSame('DISCO', $arDisco['feed_value_name']);
        $this->assertSame('neon', $arNeon['slug']);

        $arPerValue = array_count_values(array_column($obPlan->arMembershipSets, 'value_id'));
        $this->assertSame(['v-disco' => 33, 'v-neon' => 3], $arPerValue);
        $this->assertSame('DISCO 1', $obPlan->arMembershipSets[0]['shade_code']);
    }

    public function test_planning_again_after_the_apply_writes_nothing(): void
    {
        $arTags = $this->makeSnapshotTags();
        $obFirstPlan = $this->obPlanner->plan($arTags, $this->makeColorRows(), [], null);
        [$arFamilies, $arMemberships] = $this->applyPlan($obFirstPlan, $this->makeColorRows(), []);

        $obSecondPlan = $this->obPlanner->plan($arTags, $arFamilies, $arMemberships, 60);

        $this->assertFalse($obSecondPlan->isAborted());
        $this->assertSame(0, $obSecondPlan->countWrites());
    }

    public function test_collection_switched_off_by_hand_gets_no_new_row_stays_off_and_keeps_syncing_memberships(): void
    {
        $arFamilies = array_merge($this->makeColorRows(), [
            ['id' => 3, 'family' => 'DISCO', 'slug' => 'disco', 'kind' => 'collection', 'sort_order' => 8, 'feed_value_id' => 'v-disco', 'feed_value_name' => 'DISCO', 'is_active' => false, 'is_hand_disabled' => true],
        ]);
        $arTags = $this->makeSnapshotTags();

        $obPlan = $this->obPlanner->plan($arTags, $arFamilies, [], null);

        $this->assertFalse($obPlan->isAborted());
        $this->assertSame([], $obPlan->arFamilySwitches);
        $this->assertCount(1, $obPlan->arFamilyDrafts);
        $this->assertSame('v-neon', $obPlan->arFamilyDrafts[0]['feed_value_id']);
        $this->assertTrue($obPlan->arFamilyDrafts[0]['is_active']);
        $this->assertSame([], $obPlan->arFamilyRenames);
        $this->assertSame(['v-disco' => 33, 'v-neon' => 3], array_count_values(array_column($obPlan->arMembershipSets, 'value_id')));

        [$arAppliedFamilies, $arAppliedMemberships] = $this->applyPlan($obPlan, $arFamilies, []);
        $arSwitchedOffMemberships = array_filter($arAppliedMemberships, static fn (array $arMembership): bool => $arMembership['family_id'] === 3 && $arMembership['in_feed'] === true);

        $this->assertCount(33, $arSwitchedOffMemberships);
        $this->assertSame(0, $this->obPlanner->plan($arTags, $arAppliedFamilies, $arAppliedMemberships, 60)->countWrites());
    }

    public function test_tagged_offer_without_a_shade_code_stays_in_the_feed_on_every_run(): void
    {
        $arTags = $this->makeTags([
            'p#coded'  => ['shade_code' => 'DISCO 1', 'collections' => [self::DISCO]],
            'p#nocode' => ['shade_code' => null, 'collections' => [self::DISCO]],
        ]);
        $obFirstPlan = $this->obPlanner->plan($arTags, $this->makeColorRows(), [], null);
        [$arFamilies, $arMemberships] = $this->applyPlan($obFirstPlan, $this->makeColorRows(), []);

        $obSecondPlan = $this->obPlanner->plan($arTags, $arFamilies, $arMemberships, 2);

        $this->assertSame(2, $obFirstPlan->countSummary()['set']);
        $this->assertSame(0, $obSecondPlan->countWrites());
    }

    /**
     * Red as a color, DISCO as a collection with one feed member and one hand-added member.
     *
     * @param array<string, mixed> $arDiscoState is_active and is_hand_disabled of the DISCO row.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function makeDiscoRows(array $arDiscoState): array
    {
        return [
            [
                ['id' => 1, 'family' => 'Red', 'slug' => 'red', 'kind' => 'color', 'sort_order' => 1, 'feed_value_id' => null, 'feed_value_name' => null, 'is_active' => true],
                array_merge(['id' => 2, 'family' => 'DISCO', 'slug' => 'disco', 'kind' => 'collection', 'sort_order' => 2, 'feed_value_id' => 'v-disco', 'feed_value_name' => 'DISCO', 'is_hand_disabled' => false], $arDiscoState),
            ],
            [
                ['id' => 1, 'family_id' => 2, 'offer_id' => 'p#a', 'in_feed' => true, 'hand_state' => null, 'shade_code' => 'DISCO 1'],
                ['id' => 2, 'family_id' => 2, 'offer_id' => 'p#b', 'in_feed' => true, 'hand_state' => 'added', 'shade_code' => 'DISCO 2'],
            ],
        ];
    }

    /**
     * @return array<string, mixed> Both DISCO offers still in the catalog untagged, while 1C still tags an offer NEON.
     */
    private function makeTagsWithoutDisco(): array
    {
        return $this->makeTags([
            'p#a'    => ['shade_code' => 'DISCO 1', 'collections' => []],
            'p#b'    => ['shade_code' => 'DISCO 2', 'collections' => []],
            'p#neon' => ['shade_code' => 'NEON 1', 'collections' => [self::NEON]],
        ]);
    }

    /**
     * @return array<string, mixed> Both offers tagged DISCO again.
     */
    private function makeTagsWithDisco(): array
    {
        return $this->makeTags([
            'p#a' => ['shade_code' => 'DISCO 1', 'collections' => [self::DISCO]],
            'p#b' => ['shade_code' => 'DISCO 2', 'collections' => [self::DISCO]],
        ]);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function catalogAbortProvider(): array
    {
        return [
            'no collections key' => [['has_collection_key' => false, 'offer_count' => 60, 'offers' => []]],
            'empty catalog'      => [['has_collection_key' => true, 'offer_count' => 0, 'offers' => []]],
        ];
    }

    #[DataProvider('catalogAbortProvider')]
    public function test_unusable_catalog_aborts_without_writes_and_switches_nothing_off(array $arTags): void
    {
        [$arFamilies, $arMemberships] = $this->makeDiscoRows(['is_active' => true, 'is_hand_disabled' => false]);

        $obPlan = $this->obPlanner->plan($arTags, $arFamilies, $arMemberships, null);

        $this->assertTrue($obPlan->isAborted());
        $this->assertSame([], $obPlan->arFamilySwitches);
        $this->assertSame(0, $obPlan->countWrites());
    }

    public function test_catalog_under_half_of_the_last_run_aborts_without_writes(): void
    {
        $arTags = $this->makeSnapshotTags();
        [$arFamilies, $arMemberships] = $this->makeDiscoRows(['is_active' => true, 'is_hand_disabled' => false]);
        $arTruncatedTags = $this->makeTagsWithoutDisco();

        $this->assertTrue($this->obPlanner->plan($arTags, $this->makeColorRows(), [], 121)->isAborted());
        $this->assertFalse($this->obPlanner->plan($arTags, $this->makeColorRows(), [], 120)->isAborted());

        $obTruncatedPlan = $this->obPlanner->plan($arTruncatedTags, $arFamilies, $arMemberships, 7);

        $this->assertTrue($obTruncatedPlan->isAborted());
        $this->assertSame(0, $obTruncatedPlan->countWrites());
    }

    public function test_collection_1c_no_longer_carries_is_switched_off_and_loses_its_feed_members(): void
    {
        [$arFamilies, $arMemberships] = $this->makeDiscoRows(['is_active' => true]);
        $arTags = $this->makeTagsWithoutDisco();

        $obPlan = $this->obPlanner->plan($arTags, $arFamilies, $arMemberships, null);

        $this->assertFalse($obPlan->isAborted());
        $this->assertSame([['row_id' => 2, 'is_active' => false]], $obPlan->arFamilySwitches);
        $this->assertSame([['row_id' => 1]], $obPlan->arMembershipDeletes);
        $this->assertSame([['row_id' => 2]], $obPlan->arMembershipUnsets);
        $this->assertSame(['create' => 1, 'rename' => 0, 'set' => 1, 'unset' => 1, 'delete' => 1, 'shade' => 0, 'on' => 0, 'off' => 1], $obPlan->countSummary());

        [$arAppliedFamilies, $arAppliedMemberships] = $this->applyPlan($obPlan, $arFamilies, $arMemberships);

        $this->assertSame(0, $this->obPlanner->plan($arTags, $arAppliedFamilies, $arAppliedMemberships, 3)->countWrites());
    }

    public function test_collection_switched_back_on_by_hand_while_1c_carries_it_stays_on(): void
    {
        [$arFamilies, $arMemberships] = $this->makeDiscoRows(['is_active' => false, 'is_hand_disabled' => true]);
        $arHandSwitch = CollectionHandSwitch::resolve(FamilyKind::COLLECTION, false, true, false);
        $arFamilies[1]['is_active'] = $arHandSwitch['is_active'];
        $arFamilies[1]['is_hand_disabled'] = $arHandSwitch['is_hand_disabled'] ?? $arFamilies[1]['is_hand_disabled'];

        $obPlan = $this->obPlanner->plan($this->makeTagsWithDisco(), $arFamilies, $arMemberships, null);

        $this->assertFalse($obPlan->isAborted());
        $this->assertSame([], $obPlan->arFamilySwitches);
    }

    public function test_catalog_with_no_collection_tag_aborts_while_active_collections_have_1c_members(): void
    {
        [$arFamilies, $arMemberships] = $this->makeDiscoRows(['is_active' => true]);
        $arTags = $this->makeTags([
            'p#a' => ['shade_code' => 'DISCO 1', 'collections' => []],
            'p#b' => ['shade_code' => 'DISCO 2', 'collections' => []],
        ]);

        $obPlan = $this->obPlanner->plan($arTags, $arFamilies, $arMemberships, 2);

        $this->assertTrue($obPlan->isAborted());
        $this->assertStringContainsString('no catalog offer carries a collection tag while 1 active collections', (string) $obPlan->sAbortReason);
        $this->assertSame(0, $obPlan->countWrites());
    }

    public function test_catalog_with_no_collection_tag_goes_through_once_the_collections_are_switched_off_by_hand(): void
    {
        [$arFamilies, $arMemberships] = $this->makeDiscoRows(['is_active' => false, 'is_hand_disabled' => true]);
        $arTags = $this->makeTags([
            'p#a' => ['shade_code' => 'DISCO 1', 'collections' => []],
            'p#b' => ['shade_code' => 'DISCO 2', 'collections' => []],
        ]);

        $obPlan = $this->obPlanner->plan($arTags, $arFamilies, $arMemberships, 2);

        $this->assertFalse($obPlan->isAborted());
        $this->assertSame([['row_id' => 1]], $obPlan->arMembershipDeletes);
    }

    public function test_collection_value_recreated_in_1c_under_a_new_id_aborts_instead_of_switching_it_off(): void
    {
        [$arFamilies, $arMemberships] = $this->makeDiscoRows(['is_active' => true]);
        $arTags = $this->makeTags([
            'p#a' => ['shade_code' => 'DISCO 1', 'collections' => [['value_id' => 'v-disco-new', 'value' => 'DISCO']]],
            'p#b' => ['shade_code' => 'DISCO 2', 'collections' => [['value_id' => 'v-disco-new', 'value' => 'DISCO']]],
        ]);

        $obPlan = $this->obPlanner->plan($arTags, $arFamilies, $arMemberships, 2);

        $this->assertTrue($obPlan->isAborted());
        $this->assertStringContainsString('1C value "DISCO" (v-disco-new) has the key of collection DISCO', (string) $obPlan->sAbortReason);
        $this->assertSame([], $obPlan->arFamilySwitches);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function switchColumnProvider(): array
    {
        return [
            'is_active'        => ['is_active'],
            'is_hand_disabled' => ['is_hand_disabled'],
        ];
    }

    #[DataProvider('switchColumnProvider')]
    public function test_collection_row_without_a_switch_column_fails_fast(string $sColumn): void
    {
        [$arFamilies, $arMemberships] = $this->makeDiscoRows(['is_active' => false]);
        unset($arFamilies[1][$sColumn]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Collection row 2 has no ' . $sColumn . ' column.');

        $this->obPlanner->plan($this->makeTagsWithDisco(), $arFamilies, $arMemberships, null);
    }

    public function test_collection_the_sync_switched_off_is_switched_on_when_1c_carries_it_again(): void
    {
        [$arFamilies, $arMemberships] = $this->makeDiscoRows(['is_active' => false, 'is_hand_disabled' => false]);
        $arTags = $this->makeTagsWithDisco();

        $obPlan = $this->obPlanner->plan($arTags, $arFamilies, $arMemberships, null);

        $this->assertFalse($obPlan->isAborted());
        $this->assertSame([['row_id' => 2, 'is_active' => true]], $obPlan->arFamilySwitches);
        $this->assertSame([], $obPlan->arFamilyDrafts);
        $this->assertSame(['create' => 0, 'rename' => 0, 'set' => 0, 'unset' => 0, 'delete' => 0, 'shade' => 0, 'on' => 1, 'off' => 0], $obPlan->countSummary());

        [$arAppliedFamilies, $arAppliedMemberships] = $this->applyPlan($obPlan, $arFamilies, $arMemberships);

        $this->assertSame(0, $this->obPlanner->plan($arTags, $arAppliedFamilies, $arAppliedMemberships, 2)->countWrites());
    }

    public function test_collection_the_sync_switched_off_stays_off_while_1c_does_not_carry_it(): void
    {
        [$arFamilies, $arMemberships] = $this->makeDiscoRows(['is_active' => false, 'is_hand_disabled' => false]);
        $arTags = $this->makeTags([
            'p#a' => ['shade_code' => 'DISCO 1', 'collections' => []],
            'p#b' => ['shade_code' => 'DISCO 2', 'collections' => []],
            'p#c' => ['shade_code' => 'NEON 1', 'collections' => [self::NEON]],
        ]);

        $obPlan = $this->obPlanner->plan($arTags, $arFamilies, $arMemberships, null);

        $this->assertFalse($obPlan->isAborted());
        $this->assertSame([], $obPlan->arFamilySwitches);
        $this->assertSame(['create' => 1, 'rename' => 0, 'set' => 1, 'unset' => 1, 'delete' => 1, 'shade' => 0, 'on' => 0, 'off' => 0], $obPlan->countSummary());
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public static function carriedByOneCProvider(): array
    {
        return [
            '1C carries it'         => [true],
            '1C does not carry it'  => [false],
        ];
    }

    #[DataProvider('carriedByOneCProvider')]
    public function test_collection_switched_off_by_hand_is_never_switched_on(bool $blCarried): void
    {
        [$arFamilies, $arMemberships] = $this->makeDiscoRows(['is_active' => false, 'is_hand_disabled' => true]);
        $arOffers = ($blCarried ? $this->makeTagsWithDisco() : $this->makeTagsWithoutDisco())['offers'];
        $arOffers['p#c'] = ['shade_code' => 'DISCO 3', 'collections' => $blCarried ? [self::DISCO] : []];

        $obPlan = $this->obPlanner->plan($this->makeTags($arOffers), $arFamilies, $arMemberships, null);

        $this->assertFalse($obPlan->isAborted());
        $this->assertSame([], $obPlan->arFamilySwitches);

        if ($blCarried) {
            $this->assertSame([['value_id' => 'v-disco', 'offer_id' => 'p#c', 'shade_code' => 'DISCO 3', 'row_id' => null]], $obPlan->arMembershipSets);

            return;
        }

        $this->assertSame([['row_id' => 1]], $obPlan->arMembershipDeletes);
        $this->assertSame([['row_id' => 2]], $obPlan->arMembershipUnsets);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function badKeyProvider(): array
    {
        return [
            'starts with a digit' => ['2024 Drop'],
            'underscore'          => ['Neon_Glow'],
            'non latin'           => ['Neon Glow Zaļš'],
            'over 64 characters'  => [str_repeat('A', 65)],
            'existing family'     => ['red'],
        ];
    }

    #[DataProvider('badKeyProvider')]
    public function test_value_that_cannot_be_a_family_key_is_skipped_and_the_rest_still_syncs(string $sValue): void
    {
        $arTags = $this->makeTags([
            'p#bad'  => ['shade_code' => 'X 1', 'collections' => [['value_id' => 'v-bad', 'value' => $sValue]]],
            'p#good' => ['shade_code' => 'DISCO 1', 'collections' => [self::DISCO]],
        ]);

        $obPlan = $this->obPlanner->plan($arTags, $this->makeColorRows(), [], null);

        $this->assertFalse($obPlan->isAborted());
        $this->assertSame(['DISCO'], array_column($obPlan->arFamilyDrafts, 'family'));
        $this->assertSame([['value_id' => 'v-disco', 'offer_id' => 'p#good', 'shade_code' => 'DISCO 1', 'row_id' => null]], $obPlan->arMembershipSets);
        $this->assertCount(1, $obPlan->arSkippedValues);
        $this->assertSame(['v-bad', $sValue], [$obPlan->arSkippedValues[0]['value_id'], $obPlan->arSkippedValues[0]['name']]);
        $this->assertStringContainsString('Skipped 1: ' . $sValue . ' (', $obPlan->describe('Planned'));
    }

    public function test_skipped_value_is_reported_again_and_writes_nothing_on_the_next_run(): void
    {
        $arTags = $this->makeTags(['p#bad' => ['shade_code' => null, 'collections' => [['value_id' => 'v-bad', 'value' => 'Rudens 2.0']]]]);

        $obFirstPlan = $this->obPlanner->plan($arTags, $this->makeColorRows(), [], null);
        $obSecondPlan = $this->obPlanner->plan($arTags, $this->makeColorRows(), [], 1);

        $this->assertSame(0, $obFirstPlan->countWrites());
        $this->assertSame($obFirstPlan->arSkippedValues, $obSecondPlan->arSkippedValues);
    }

    public function test_slug_collision_gets_a_numeric_suffix(): void
    {
        $arFamilies = array_merge($this->makeColorRows(), [
            ['id' => 3, 'family' => 'Neon Old', 'slug' => 'neon', 'kind' => 'color', 'sort_order' => 3, 'feed_value_id' => null, 'feed_value_name' => null],
        ]);

        $obPlan = $this->obPlanner->plan($this->makeSnapshotTags(), $arFamilies, [], null);

        $this->assertSame(['disco', 'neon-2'], array_column($obPlan->arFamilyDrafts, 'slug'));
    }

    public function test_renamed_value_updates_only_the_feed_name(): void
    {
        $arFamilies = [
            ['id' => 1, 'family' => 'DISCO', 'slug' => 'disco', 'kind' => 'collection', 'sort_order' => 1, 'feed_value_id' => 'v-disco', 'feed_value_name' => 'Disco old', 'is_active' => true, 'is_hand_disabled' => false],
        ];
        $arTags = $this->makeTags(['p#a' => ['shade_code' => 'DISCO 1', 'collections' => [self::DISCO]]]);
        $arMemberships = [['id' => 1, 'family_id' => 1, 'offer_id' => 'p#a', 'in_feed' => true, 'hand_state' => null, 'shade_code' => 'DISCO 1']];

        $obPlan = $this->obPlanner->plan($arTags, $arFamilies, $arMemberships, null);

        $this->assertSame([['value_id' => 'v-disco', 'feed_value_name' => 'DISCO']], $obPlan->arFamilyRenames);
        $this->assertSame(['create' => 0, 'rename' => 1, 'set' => 0, 'unset' => 0, 'delete' => 0, 'shade' => 0, 'on' => 0, 'off' => 0], $obPlan->countSummary());
    }

    public function test_untagged_offer_loses_its_feed_flag_and_hand_rows_survive(): void
    {
        $arFamilies = [
            ['id' => 1, 'family' => 'DISCO', 'slug' => 'disco', 'kind' => 'collection', 'sort_order' => 1, 'feed_value_id' => 'v-disco', 'feed_value_name' => 'DISCO', 'is_active' => true, 'is_hand_disabled' => false],
        ];
        $arMemberships = [
            ['id' => 1, 'family_id' => 1, 'offer_id' => 'p#kept', 'in_feed' => true, 'hand_state' => null, 'shade_code' => 'DISCO 1'],
            ['id' => 2, 'family_id' => 1, 'offer_id' => 'p#untagged', 'in_feed' => true, 'hand_state' => null, 'shade_code' => 'DISCO 2'],
            ['id' => 3, 'family_id' => 1, 'offer_id' => 'p#handremoved', 'in_feed' => true, 'hand_state' => 'removed', 'shade_code' => 'DISCO 3'],
            ['id' => 4, 'family_id' => 1, 'offer_id' => 'p#handadded', 'in_feed' => false, 'hand_state' => 'added', 'shade_code' => 'DISCO 4'],
        ];
        $arTags = $this->makeTags([
            'p#kept'        => ['shade_code' => 'DISCO 1', 'collections' => [self::DISCO]],
            'p#untagged'    => ['shade_code' => 'DISCO 2', 'collections' => []],
            'p#handremoved' => ['shade_code' => 'DISCO 3', 'collections' => []],
            'p#handadded'   => ['shade_code' => 'DISCO 4', 'collections' => []],
        ]);

        $obPlan = $this->obPlanner->plan($arTags, $arFamilies, $arMemberships, null);

        $this->assertSame([['row_id' => 2]], $obPlan->arMembershipDeletes);
        $this->assertSame([['row_id' => 3]], $obPlan->arMembershipUnsets);
        $this->assertSame([], $obPlan->arMembershipSets);
        $this->assertSame([], $obPlan->arShadeUpdates);
    }

    public function test_hand_removed_row_of_a_tagged_offer_turns_in_feed_on_and_keeps_its_row(): void
    {
        $arFamilies = [
            ['id' => 1, 'family' => 'DISCO', 'slug' => 'disco', 'kind' => 'collection', 'sort_order' => 1, 'feed_value_id' => 'v-disco', 'feed_value_name' => 'DISCO', 'is_active' => true, 'is_hand_disabled' => false],
        ];
        $arMemberships = [['id' => 9, 'family_id' => 1, 'offer_id' => 'p#a', 'in_feed' => false, 'hand_state' => 'removed', 'shade_code' => null]];
        $arTags = $this->makeTags(['p#a' => ['shade_code' => 'DISCO 1', 'collections' => [self::DISCO]]]);

        $obPlan = $this->obPlanner->plan($arTags, $arFamilies, $arMemberships, null);

        $this->assertSame([['value_id' => 'v-disco', 'offer_id' => 'p#a', 'shade_code' => 'DISCO 1', 'row_id' => 9]], $obPlan->arMembershipSets);
    }

    public function test_shade_codes_refresh_on_every_row_of_an_offer_in_the_catalog(): void
    {
        $arFamilies = array_merge($this->makeColorRows(), [
            ['id' => 3, 'family' => 'DISCO', 'slug' => 'disco', 'kind' => 'collection', 'sort_order' => 8, 'feed_value_id' => 'v-disco', 'feed_value_name' => 'DISCO', 'is_active' => true, 'is_hand_disabled' => false],
        ]);
        $arMemberships = [
            ['id' => 1, 'family_id' => 3, 'offer_id' => 'p#a', 'in_feed' => true, 'hand_state' => null, 'shade_code' => 'DISCO 1'],
            ['id' => 2, 'family_id' => 2, 'offer_id' => 'p#a', 'in_feed' => false, 'hand_state' => 'added', 'shade_code' => 'DISCO 1'],
            ['id' => 3, 'family_id' => 2, 'offer_id' => 'p#gone', 'in_feed' => false, 'hand_state' => 'added', 'shade_code' => 'OLD 1'],
        ];
        $arTags = $this->makeTags(['p#a' => ['shade_code' => 'DISCO 01', 'collections' => [self::DISCO]]]);

        $obPlan = $this->obPlanner->plan($arTags, $arFamilies, $arMemberships, null);

        $this->assertSame(
            [['row_id' => 1, 'shade_code' => 'DISCO 01'], ['row_id' => 2, 'shade_code' => 'DISCO 01']],
            $obPlan->arShadeUpdates
        );
    }

    public function test_plan_never_carries_a_hand_state(): void
    {
        $obPlan = $this->obPlanner->plan($this->makeSnapshotTags(), $this->makeColorRows(), [], null);

        foreach ($obPlan->arMembershipSets as $arSet) {
            $this->assertArrayNotHasKey('hand_state', $arSet);
        }
    }
}
