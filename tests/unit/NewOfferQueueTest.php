<?php

use Logingrupa\ColorClassifier\Classes\NewOfferQueue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * QUEUE-01..14: the scheduled job takes catalog offers without a color entry,
 * once each, never-tried offers before earlier failures in catalog order, at
 * most the limit per run, and its failure memory keeps only failed offers that
 * are still waiting.
 */
class NewOfferQueueTest extends TestCase
{
    /**
     * @return array{offer_id: string, product_name: string, variation_name: string, image_url: string}
     */
    private static function offer(string $sOfferId, string $sProductName = 'Gel Polish'): array
    {
        return [
            'offer_id'       => $sOfferId,
            'product_name'   => $sProductName,
            'variation_name' => 'Shade ' . $sOfferId,
            'image_url'      => 'https://example.test/' . $sOfferId . '.png',
        ];
    }

    /**
     * @param array<int, string> $arOfferIds
     *
     * @return array<int, array{offer_id: string, product_name: string, variation_name: string, image_url: string}>
     */
    private static function offers(array $arOfferIds): array
    {
        return array_map(static fn (string $sOfferId): array => self::offer($sOfferId), $arOfferIds);
    }

    /**
     * @param array<int, string> $arOfferIds
     *
     * @return array<string, true>
     */
    private static function offerIdMap(array $arOfferIds): array
    {
        return array_fill_keys($arOfferIds, true);
    }

    /**
     * @param array{batch: array<int, array{offer_id: string}>} $arSelection
     *
     * @return array<int, string>
     */
    private static function batchOfferIds(array $arSelection): array
    {
        return array_column($arSelection['batch'], 'offer_id');
    }

    /**
     * @param array<int, string> $arExpectedOfferIds
     * @param array<string, true> $arFailedOfferIdMap
     */
    private function assertFailedOfferIds(array $arExpectedOfferIds, array $arFailedOfferIdMap): void
    {
        $arActualOfferIds = array_keys($arFailedOfferIdMap);
        sort($arExpectedOfferIds);
        sort($arActualOfferIds);

        $this->assertSame($arExpectedOfferIds, $arActualOfferIds);
        $this->assertSame([], array_filter($arFailedOfferIdMap, static fn ($blFailed): bool => $blFailed !== true));
    }

    public function test_offers_with_a_color_entry_are_left_out(): void
    {
        $arSelection = NewOfferQueue::select(self::offers(['a', 'b', 'c']), self::offerIdMap(['b']), [], 10);

        $this->assertSame(['a', 'c'], self::batchOfferIds($arSelection));
        $this->assertSame(2, $arSelection['total']);
        $this->assertSame(0, $arSelection['left']);
    }

    public function test_an_offer_with_a_color_entry_is_left_out_even_when_it_failed_before(): void
    {
        $arSelection = NewOfferQueue::select(self::offers(['a', 'b', 'c']), self::offerIdMap(['b']), self::offerIdMap(['b', 'c']), 10);

        $this->assertSame(['a', 'c'], self::batchOfferIds($arSelection));
        $this->assertSame(['a', 'c'], $arSelection['candidate_offer_ids']);
        $this->assertSame(2, $arSelection['total']);
    }

    public function test_earlier_failures_go_after_never_tried_offers_in_catalog_order(): void
    {
        $arSelection = NewOfferQueue::select(self::offers(['a', 'b', 'c', 'd', 'e']), [], self::offerIdMap(['d', 'b']), 10);

        $this->assertSame(['a', 'c', 'e', 'b', 'd'], self::batchOfferIds($arSelection));
    }

    public function test_limit_caps_the_batch_and_left_counts_the_rest(): void
    {
        $arSelection = NewOfferQueue::select(self::offers(['a', 'b', 'c', 'd', 'e']), [], [], 2);

        $this->assertSame(['a', 'b'], self::batchOfferIds($arSelection));
        $this->assertSame(5, $arSelection['total']);
        $this->assertSame(3, $arSelection['left']);
    }

    public function test_limit_reaches_earlier_failures_only_after_every_new_offer(): void
    {
        $arSelection = NewOfferQueue::select(self::offers(['a', 'b', 'c']), [], self::offerIdMap(['a']), 2);

        $this->assertSame(['b', 'c'], self::batchOfferIds($arSelection));
        $this->assertSame(1, $arSelection['left']);
    }

    public function test_a_duplicate_offer_id_counts_once_and_the_first_row_wins(): void
    {
        $arOffers = [self::offer('a', 'First'), self::offer('b'), self::offer('a', 'Second')];

        $arSelection = NewOfferQueue::select($arOffers, [], [], 10);

        $this->assertSame(['a', 'b'], self::batchOfferIds($arSelection));
        $this->assertSame('First', $arSelection['batch'][0]['product_name']);
        $this->assertSame(2, $arSelection['total']);
    }

    public function test_candidate_offer_ids_cover_every_candidate_not_only_the_batch(): void
    {
        $arSelection = NewOfferQueue::select(self::offers(['a', 'b', 'c', 'd']), self::offerIdMap(['b']), self::offerIdMap(['a']), 1);

        $this->assertSame(['c'], self::batchOfferIds($arSelection));
        $this->assertSame(['c', 'd', 'a'], $arSelection['candidate_offer_ids']);
    }

    public function test_empty_catalog_gives_an_empty_batch(): void
    {
        $arSelection = NewOfferQueue::select([], self::offerIdMap(['a']), self::offerIdMap(['b']), 100);

        $this->assertSame(['batch' => [], 'candidate_offer_ids' => [], 'left' => 0, 'total' => 0], $arSelection);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function invalidLimitProvider(): array
    {
        return [
            'zero'     => [0],
            'negative' => [-1],
        ];
    }

    #[DataProvider('invalidLimitProvider')]
    public function test_limit_below_one_throws(int $iLimit): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NewOfferQueue::select(self::offers(['a']), [], [], $iLimit);
    }

    public function test_a_failed_offer_that_succeeds_leaves_the_memory(): void
    {
        $arNext = NewOfferQueue::nextFailedOfferIds(self::offerIdMap(['a', 'b']), ['a', 'b'], ['a'], ['b']);

        $this->assertFailedOfferIds(['b'], $arNext);
    }

    public function test_a_failed_offer_gone_from_the_candidates_leaves_the_memory(): void
    {
        $arNext = NewOfferQueue::nextFailedOfferIds(self::offerIdMap(['gone', 'has-entry-now', 'b']), ['b', 'c'], ['c'], []);

        $this->assertFailedOfferIds(['b'], $arNext);
    }

    public function test_an_earlier_failure_not_reached_this_run_stays(): void
    {
        $arNext = NewOfferQueue::nextFailedOfferIds(self::offerIdMap(['c']), ['a', 'b', 'c'], ['a', 'b'], []);

        $this->assertFailedOfferIds(['c'], $arNext);
    }

    public function test_this_runs_failures_join_the_memory(): void
    {
        $arNext = NewOfferQueue::nextFailedOfferIds(self::offerIdMap(['a']), ['a', 'b', 'c'], ['c'], ['a', 'b']);

        $this->assertFailedOfferIds(['a', 'b'], $arNext);
    }

    public function test_an_offer_a_run_died_on_goes_behind_the_never_tried_offers_next_run(): void
    {
        $arStoredBeforeOfferB = NewOfferQueue::nextFailedOfferIds([], ['a', 'b', 'c'], ['a'], ['b']);

        $arSelection = NewOfferQueue::select(self::offers(['a', 'b', 'c', 'd']), self::offerIdMap(['a']), $arStoredBeforeOfferB, 10);

        $this->assertFailedOfferIds(['b'], $arStoredBeforeOfferB);
        $this->assertSame(['c', 'd', 'b'], self::batchOfferIds($arSelection));
    }
}
