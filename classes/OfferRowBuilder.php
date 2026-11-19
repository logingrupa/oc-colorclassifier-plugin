<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * OfferRowBuilder - turns the full 1C catalog into the offer rows the plugin stores.
 *
 * Every offer with an image gets a row. An offer the shop lists (it is in the
 * offer directory) carries the shop's product name, swatch label and a detail
 * URL on the shop's product slug. Any other offer keeps its 1C names and gets
 * no detail URL, because no shop page exists for it.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class OfferRowBuilder
{
    /**
     * @param array<int, array<string, mixed>> $arFullCatalog Products from the unfiltered CommerceMlParser::getCatalog().
     * @param array<string, array{product_name: string, product_slug: string, offer_name: string, variant_title: string}> $arOfferDirectory
     *        Offers the shop lists, keyed by offer id.
     *
     * @return array<int, array{offer_id: string, product_name: string, variation_name: string, image_url: string, detail_url: string}>
     */
    public function build(array $arFullCatalog, array $arOfferDirectory): array
    {
        $arRows = [];

        foreach ($arFullCatalog as $arProduct) {
            foreach ((array) ($arProduct['offers'] ?? []) as $arOffer) {
                $sImageUrl = (string) ($arOffer['image_url'] ?? '');

                if ($sImageUrl === '') {
                    continue;
                }

                $sOfferId = (string) ($arOffer['id'] ?? '');
                $arRows[] = ['offer_id' => $sOfferId, 'image_url' => $sImageUrl]
                    + $this->resolveLabelsAndUrl($arProduct, $arOffer, $arOfferDirectory[$sOfferId] ?? null);
            }
        }

        return $arRows;
    }

    /**
     * @param array<string, mixed>      $arProduct
     * @param array<string, mixed>      $arOffer
     * @param array<string, string>|null $arListedOffer Directory entry of the offer, null when the shop does not list it.
     *
     * @return array{product_name: string, variation_name: string, detail_url: string}
     */
    private function resolveLabelsAndUrl(array $arProduct, array $arOffer, ?array $arListedOffer): array
    {
        if ($arListedOffer === null) {
            return [
                'product_name'   => (string) ($arProduct['name'] ?? ''),
                'variation_name' => (string) ($arOffer['variant_title'] ?? ($arOffer['name'] ?? '')),
                'detail_url'     => '',
            ];
        }

        return [
            'product_name'   => $arListedOffer['product_name'],
            'variation_name' => $arListedOffer['variant_title'],
            'detail_url'     => NailoLabTransforms::buildListedOfferDetailUrl($arListedOffer),
        ];
    }
}
