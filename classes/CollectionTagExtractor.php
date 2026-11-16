<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * CollectionTagExtractor - turns a parsed catalog into the planner's tag input.
 *
 * Image-less offers stay in, because a collection lists every 1C member, not
 * only the offers that have a ColorEntry. Offers the injected callable rejects
 * (the nailolab exclusion list) are skipped.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class CollectionTagExtractor
{
    /**
     * @param array<int, array<string, mixed>> $arCatalog Products from CommerceMlParser::getCatalog().
     * @param callable(string): bool $fnIsExcludedOffer Receives the full offer id.
     *
     * @return array{has_collection_key: bool, offer_count: int, offers: array<string, array{shade_code: string|null, collections: array<int, array{value_id: string, value: string}>}>}
     */
    public function extract(array $arCatalog, callable $fnIsExcludedOffer): array
    {
        $blHasCollectionKey = false;
        $arOffers = [];

        foreach ($arCatalog as $arProduct) {
            foreach ((array) ($arProduct['offers'] ?? []) as $arOffer) {
                $blHasCollectionKey = $blHasCollectionKey || array_key_exists('collections', $arOffer);
                $sOfferId = (string) ($arOffer['id'] ?? '');

                if ($sOfferId === '' || $fnIsExcludedOffer($sOfferId)) {
                    continue;
                }

                $arOffers[$sOfferId] = [
                    'shade_code'  => $arOffer['shade_code'] ?? null,
                    'collections' => (array) ($arOffer['collections'] ?? []),
                ];
            }
        }

        return [
            'has_collection_key' => $blHasCollectionKey,
            'offer_count'        => count($arOffers),
            'offers'             => $arOffers,
        ];
    }
}
