<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * NewOfferQueue - which catalog offers the scheduled job colors next, and which failures it remembers.
 *
 * A candidate is a catalog offer with an image and no color entry. Offers never
 * tried go before offers whose image failed on an earlier run, catalog order kept
 * inside each group, so a broken image cannot hold back a new offer. The failure
 * memory keeps only failed offers that are still candidates.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
final class NewOfferQueue
{
    /**
     * @param array<int, array{offer_id: string}> $arOffers Catalog offers with images, in catalog order.
     * @param array<string, true> $arExistingOfferIdMap Offer ids that have a color entry.
     * @param array<string, true> $arFailedOfferIdMap Offer ids whose image failed on an earlier run.
     * @param int $iLimit Most offers in the batch.
     *
     * @return array{batch: array<int, array{offer_id: string}>, candidate_offer_ids: array<int, string>, left: int, total: int}
     *
     * @throws \InvalidArgumentException When the limit is below 1.
     */
    public static function select(array $arOffers, array $arExistingOfferIdMap, array $arFailedOfferIdMap, int $iLimit): array
    {
        if ($iLimit < 1) {
            throw new \InvalidArgumentException('The new offer limit must be 1 or more, got ' . $iLimit);
        }

        $arQueuedOfferIdMap    = [];
        $arNeverFailedOffers   = [];
        $arEarlierFailedOffers = [];

        foreach ($arOffers as $arOffer) {
            $sOfferId = (string) $arOffer['offer_id'];

            if (isset($arExistingOfferIdMap[$sOfferId]) || isset($arQueuedOfferIdMap[$sOfferId])) {
                continue;
            }

            $arQueuedOfferIdMap[$sOfferId] = true;

            if (isset($arFailedOfferIdMap[$sOfferId])) {
                $arEarlierFailedOffers[] = $arOffer;
                continue;
            }

            $arNeverFailedOffers[] = $arOffer;
        }

        $arCandidateOffers = array_merge($arNeverFailedOffers, $arEarlierFailedOffers);
        $arBatch           = array_slice($arCandidateOffers, 0, $iLimit);
        $iTotal            = count($arCandidateOffers);

        return [
            'batch'               => $arBatch,
            'candidate_offer_ids' => array_column($arCandidateOffers, 'offer_id'),
            'left'                => $iTotal - count($arBatch),
            'total'               => $iTotal,
        ];
    }

    /**
     * The failure memory after a run: earlier failures still among the candidates, plus
     * this run's failures, minus this run's successes.
     *
     * @param array<string, true> $arFailedOfferIdMap Failure memory before the run.
     * @param array<int, string> $arCandidateOfferIds Every candidate of the run, processed or not.
     * @param array<int, string> $arSucceededOfferIds Offers that got a color entry this run.
     * @param array<int, string> $arFailedNowOfferIds Offers whose image failed this run.
     *
     * @return array<string, true>
     */
    public static function nextFailedOfferIds(array $arFailedOfferIdMap, array $arCandidateOfferIds, array $arSucceededOfferIds, array $arFailedNowOfferIds): array
    {
        $arNextFailedOfferIdMap = array_intersect_key($arFailedOfferIdMap, array_fill_keys($arCandidateOfferIds, true));
        $arNextFailedOfferIdMap += array_fill_keys($arFailedNowOfferIds, true);

        return array_diff_key($arNextFailedOfferIdMap, array_fill_keys($arSucceededOfferIds, true));
    }
}
