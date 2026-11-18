<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * CollectionTagExtractor - turns a parsed catalog into the planner's tag input.
 *
 * Image-less offers stay in, because a collection lists every 1C member, not
 * only the offers that have a ColorEntry.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class CollectionTagExtractor
{
    /** Length of the shade_code column of the membership table. */
    public const SHADE_CODE_MAX_LENGTH = 64;

    /**
     * @param array<int, array<string, mixed>> $arCatalog Products from CommerceMlParser::getCatalog().
     *
     * @return array{has_collection_key: bool, offer_count: int, offers: array<string, array{shade_code: string|null, collections: array<int, array{value_id: string, value: string}>}>}
     */
    public function extract(array $arCatalog): array
    {
        $blHasCollectionKey = false;
        $arOffers = [];

        foreach ($arCatalog as $arProduct) {
            foreach ((array) ($arProduct['offers'] ?? []) as $arOffer) {
                $blHasCollectionKey = $blHasCollectionKey || array_key_exists('collections', $arOffer);
                $sOfferId = (string) ($arOffer['id'] ?? '');

                if ($sOfferId === '') {
                    continue;
                }

                $arOffers[$sOfferId] = [
                    'shade_code'  => $this->normalizeShadeCode($arOffer['shade_code'] ?? null),
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

    /**
     * Every shade code the plugin writes passes through here, so no write exceeds the column.
     *
     * @param mixed $mShadeCode Parenthesis text of the offer name.
     *
     * @return string|null Null when empty, not text or longer than the column.
     */
    private function normalizeShadeCode(mixed $mShadeCode): ?string
    {
        if (!is_string($mShadeCode) || $mShadeCode === '' || mb_strlen($mShadeCode) > self::SHADE_CODE_MAX_LENGTH) {
            return null;
        }

        return $mShadeCode;
    }
}
