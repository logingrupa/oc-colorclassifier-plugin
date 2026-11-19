<?php

use Logingrupa\ColorClassifier\Classes\NewOfferQueue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * QUEUE-01..21: the scheduled job takes catalog offers without a color entry,
 * once each, never-tried offers before earlier failures in catalog order, at
 * most the limit per run. Its failure memory counts failed runs per offer, keeps
 * only offers that are still waiting, and retires an offer after
 * NewOfferQueue::MAX_FAILED_ATTEMPTS failed runs.
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
     * @param array<int, string> $arOfferIds
     *
     * @return array<string, int>
     */
    private static function attempts(array $arOfferIds, int $iFailedAttempts = 1): array
    {
        return array_fill_keys($arOfferIds, $iFailedAttempts);
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
     * @param array<string, int> $arExpectedAttempts
     * @param array<string, int> $arFailedAttempts
     */
    private function assertFailedAttempts(array $arExpectedAttempts, array $arFailedAttempts): void
    {
        ksort($arExpectedAttempts);
        ksort($arFailedAttempts);

        $this->assertSame($arExpectedAttempts, $arFailedAttempts);
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
        $arSelection = NewOfferQueue::select(self::offers(['a', 'b', 'c']), self::offerIdMap(['b']), self::attempts(['b', 'c']), 10);

        $this->assertSame(['a', 'c'], self::batchOfferIds($arSelection));
        $this->assertSame(['a', 'c'], $arSelection['candidate_offer_ids']);
        $this->assertSame(2, $arSelection['total']);
    }

    public function test_earlier_failures_go_after_never_tried_offers_in_catalog_order(): void
    {
        $arSelection = NewOfferQueue::select(self::offers(['a', 'b', 'c', 'd', 'e']), [], self::attempts(['d', 'b']), 10);

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
        $arSelection = NewOfferQueue::select(self::offers(['a', 'b', 'c']), [], self::attempts(['a']), 2);

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
        $arSelection = NewOfferQueue::select(self::offers(['a', 'b', 'c', 'd']), self::offerIdMap(['b']), self::attempts(['a']), 1);

        $this->assertSame(['c'], self::batchOfferIds($arSelection));
        $this->assertSame(['c', 'd', 'a'], $arSelection['candidate_offer_ids']);
    }

    public function test_empty_catalog_gives_an_empty_batch(): void
    {
        $arSelection = NewOfferQueue::select([], self::offerIdMap(['a']), self::attempts(['b']), 100);

        $this->assertSame(
            ['batch' => [], 'candidate_offer_ids' => [], 'retired_offers' => [], 'left' => 0, 'total' => 0],
            $arSelection
        );
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
        $arNext = NewOfferQueue::nextFailedAttempts(self::attempts(['a', 'b']), ['a', 'b'], ['a'], ['b']);

        $this->assertFailedAttempts(['b' => 2], $arNext);
    }

    public function test_a_failed_offer_gone_from_the_candidates_leaves_the_memory(): void
    {
        $arNext = NewOfferQueue::nextFailedAttempts(self::attempts(['gone', 'has-entry-now', 'b']), ['b', 'c'], ['c'], []);

        $this->assertFailedAttempts(['b' => 1], $arNext);
    }

    public function test_an_earlier_failure_not_reached_this_run_stays(): void
    {
        $arNext = NewOfferQueue::nextFailedAttempts(self::attempts(['c']), ['a', 'b', 'c'], ['a', 'b'], []);

        $this->assertFailedAttempts(['c' => 1], $arNext);
    }

    public function test_this_runs_failures_join_the_memory(): void
    {
        $arNext = NewOfferQueue::nextFailedAttempts(self::attempts(['a']), ['a', 'b', 'c'], ['c'], ['a', 'b']);

        $this->assertFailedAttempts(['a' => 2, 'b' => 1], $arNext);
    }

    public function test_an_offer_a_run_died_on_goes_behind_the_never_tried_offers_next_run(): void
    {
        $arStoredBeforeOfferB = NewOfferQueue::nextFailedAttempts([], ['a', 'b', 'c'], ['a'], ['b']);

        $arSelection = NewOfferQueue::select(self::offers(['a', 'b', 'c', 'd']), self::offerIdMap(['a']), $arStoredBeforeOfferB, 10);

        $this->assertFailedAttempts(['b' => 1], $arStoredBeforeOfferB);
        $this->assertSame(['c', 'd', 'b'], self::batchOfferIds($arSelection));
    }

    public function test_queue15_an_offer_with_two_failed_attempts_is_retired(): void
    {
        $arSelection = NewOfferQueue::select(self::offers(['a', 'b', 'c']), [], self::attempts(['b'], 2), 10);

        $this->assertSame(['a', 'c'], self::batchOfferIds($arSelection));
        $this->assertSame(['b'], array_column($arSelection['retired_offers'], 'offer_id'));
        $this->assertSame(['a', 'c', 'b'], $arSelection['candidate_offer_ids']);
        $this->assertSame(2, $arSelection['total']);
        $this->assertSame(0, $arSelection['left']);
    }

    public function test_queue16_an_offer_with_one_failed_attempt_is_still_tried_after_every_never_tried_offer(): void
    {
        $arSelection = NewOfferQueue::select(self::offers(['a', 'b', 'c']), [], self::attempts(['a']), 10);

        $this->assertSame(['b', 'c', 'a'], self::batchOfferIds($arSelection));
        $this->assertSame([], $arSelection['retired_offers']);
    }

    public function test_queue17_a_memory_value_of_true_counts_as_one_attempt(): void
    {
        $arLegacyMemory = self::offerIdMap(['a']);

        $arSelection = NewOfferQueue::select(self::offers(['a', 'b']), [], $arLegacyMemory, 10);
        $this->assertSame(['b', 'a'], self::batchOfferIds($arSelection));

        $arNext = NewOfferQueue::nextFailedAttempts($arLegacyMemory, ['a', 'b'], ['b'], ['a']);
        $this->assertFailedAttempts(['a' => 2], $arNext);

        $arAfterSecondFailure = NewOfferQueue::select(self::offers(['a', 'b']), [], $arNext, 10);
        $this->assertSame(['a'], array_column($arAfterSecondFailure['retired_offers'], 'offer_id'));
    }

    public function test_queue18_next_failed_attempts_counts_keeps_and_drops(): void
    {
        $arNext = NewOfferQueue::nextFailedAttempts(
            ['raise' => 1, 'ok' => 1, 'gone' => 1, 'retired' => 2],
            ['raise', 'new', 'ok', 'retired'],
            ['ok'],
            ['raise', 'new']
        );

        $this->assertFailedAttempts(['raise' => 2, 'new' => 1, 'retired' => 2], $arNext);
    }

    public function test_queue19_two_runs_that_die_on_the_same_offer_retire_it_for_the_third(): void
    {
        $arOffers  = self::offers(['a', 'b']);
        $arMemory  = [];

        for ($iRun = 1; $iRun <= 2; $iRun++) {
            $arSelection = NewOfferQueue::select($arOffers, [], $arMemory, 10);
            $this->assertContains('a', self::batchOfferIds($arSelection));
            $arMemory = NewOfferQueue::nextFailedAttempts($arMemory, $arSelection['candidate_offer_ids'], [], ['a']);
        }

        $arThirdRun = NewOfferQueue::select($arOffers, [], $arMemory, 10);

        $this->assertSame(['b'], self::batchOfferIds($arThirdRun));
        $this->assertSame(['a'], array_column($arThirdRun['retired_offers'], 'offer_id'));
    }

    public function test_queue20_recomputing_in_one_run_counts_an_offer_once(): void
    {
        $arBeforeRun = self::attempts(['a']);
        $arCandidates = ['a', 'b'];

        NewOfferQueue::nextFailedAttempts($arBeforeRun, $arCandidates, [], ['a']);
        NewOfferQueue::nextFailedAttempts($arBeforeRun, $arCandidates, [], ['a', 'b']);
        $arLast = NewOfferQueue::nextFailedAttempts($arBeforeRun, $arCandidates, [], ['a', 'b']);

        $this->assertFailedAttempts(['a' => 2, 'b' => 1], $arLast);
    }

    public function test_queue21_an_offer_is_retired_after_two_failed_attempts(): void
    {
        $this->assertSame(2, NewOfferQueue::MAX_FAILED_ATTEMPTS);
    }
}
