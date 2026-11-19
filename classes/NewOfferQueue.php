<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * NewOfferQueue - which catalog offers the scheduled job colors next, and which failures it remembers.
 *
 * A candidate is a catalog offer with an image and no color entry. Offers never
 * tried go before offers whose image failed on an earlier run, catalog order kept
 * inside each group, so a broken image cannot hold back a new offer. An offer that
 * failed MAX_FAILED_ATTEMPTS times is retired: it stays in the failure memory but
 * is left out of the batch. The failure memory maps offer id to the number of runs
 * that failed on it, and keeps only offers that are still candidates. A value of
 * true, written before attempts were counted, reads as one attempt.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
final class NewOfferQueue
{
    /** @var int Failed runs after which the scheduled job stops trying an offer. */
    public const MAX_FAILED_ATTEMPTS = 2;

    /**
     * @param array<int, array{offer_id: string}> $arOffers Catalog offers with images, in catalog order.
     * @param array<string, true> $arExistingOfferIdMap Offer ids that have a color entry.
     * @param array<string, int|true> $arFailedAttempts Offer id => failed runs so far.
     * @param int $iLimit Most offers in the batch.
     *
     * @return array{batch: array<int, array{offer_id: string}>, candidate_offer_ids: array<int, string>, retired_offers: array<int, array{offer_id: string}>, left: int, total: int}
     *
     * @throws \InvalidArgumentException When the limit is below 1.
     */
    public static function select(array $arOffers, array $arExistingOfferIdMap, array $arFailedAttempts, int $iLimit): array
    {
        if ($iLimit < 1) {
            throw new \InvalidArgumentException('The new offer limit must be 1 or more, got ' . $iLimit);
        }

        $arQueuedOfferIdMap    = [];
        $arNeverFailedOffers   = [];
        $arEarlierFailedOffers = [];
        $arRetiredOffers       = [];

        foreach ($arOffers as $arOffer) {
            $sOfferId = (string) $arOffer['offer_id'];

            if (isset($arExistingOfferIdMap[$sOfferId]) || isset($arQueuedOfferIdMap[$sOfferId])) {
                continue;
            }

            $arQueuedOfferIdMap[$sOfferId] = true;
            $iFailedAttempts               = (int) ($arFailedAttempts[$sOfferId] ?? 0);

            if ($iFailedAttempts >= self::MAX_FAILED_ATTEMPTS) {
                $arRetiredOffers[] = $arOffer;
            } elseif ($iFailedAttempts > 0) {
                $arEarlierFailedOffers[] = $arOffer;
            } else {
                $arNeverFailedOffers[] = $arOffer;
            }
        }

        $arTriedOffers = array_merge($arNeverFailedOffers, $arEarlierFailedOffers);
        $arBatch       = array_slice($arTriedOffers, 0, $iLimit);
        $iTotal        = count($arTriedOffers);

        return [
            'batch'               => $arBatch,
            'candidate_offer_ids' => array_column(array_merge($arTriedOffers, $arRetiredOffers), 'offer_id'),
            'retired_offers'      => $arRetiredOffers,
            'left'                => $iTotal - count($arBatch),
            'total'               => $iTotal,
        ];
    }

    /**
     * The failure memory after a run: earlier failures still among the candidates, plus
     * one more attempt for each offer that failed this run, minus this run's successes.
     * It is always computed from the memory before the run, so recomputing it several
     * times in one run counts an offer once.
     *
     * @param array<string, int|true> $arFailedAttempts Failure memory before the run.
     * @param array<int, string> $arCandidateOfferIds Every candidate of the run, processed or not, retired ones included.
     * @param array<int, string> $arSucceededOfferIds Offers that got a color entry this run.
     * @param array<int, string> $arFailedNowOfferIds Offers whose image failed this run.
     *
     * @return array<string, int>
     */
    public static function nextFailedAttempts(array $arFailedAttempts, array $arCandidateOfferIds, array $arSucceededOfferIds, array $arFailedNowOfferIds): array
    {
        $arNextFailedAttempts = array_map(
            'intval',
            array_intersect_key($arFailedAttempts, array_fill_keys($arCandidateOfferIds, true))
        );

        foreach ($arFailedNowOfferIds as $sOfferId) {
            $arNextFailedAttempts[$sOfferId] = (int) ($arFailedAttempts[$sOfferId] ?? 0) + 1;
        }

        return array_diff_key($arNextFailedAttempts, array_fill_keys($arSucceededOfferIds, true));
    }
}
