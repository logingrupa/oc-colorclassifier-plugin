<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * OfferLinkRefreshPlanner - diffs the link fields stored on color entries
 * against the current catalog.
 *
 * Only the four fields a shop shows next to a swatch are compared. Colors,
 * overrides and locks never enter the plan, so a refresh cannot move them.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class OfferLinkRefreshPlanner
{
    /** @var string[] Stored fields that follow the catalog. */
    public const LINK_FIELDS = ['product_name', 'variation_name', 'detail_url', 'image_url'];

    /**
     * @param array<string, array<string, mixed>> $arStoredLinkFieldsByOfferId Stored rows keyed by offer_id, with their is_locked flag.
     * @param array<int, array<string, mixed>>    $arCatalogOffers              Rows shaped like OfferDataProvider::getOffersWithImages().
     *
     * @return array{changes: array<string, array<string, string>>, unchanged: int, absent: int, reprocess: array<int, string>}
     *         reprocess lists the offers whose image changed on an unlocked row: the stored color still describes the old picture.
     */
    public function plan(array $arStoredLinkFieldsByOfferId, array $arCatalogOffers): array
    {
        $arCatalogByOfferId = [];

        foreach ($arCatalogOffers as $arCatalogOffer) {
            $arCatalogByOfferId[(string) ($arCatalogOffer['offer_id'] ?? '')] = $arCatalogOffer;
        }

        $arChanges = [];
        $arReprocess = [];
        $iUnchanged = 0;
        $iAbsent = 0;

        foreach ($arStoredLinkFieldsByOfferId as $sOfferId => $arStoredRow) {
            $arCatalogOffer = $arCatalogByOfferId[(string) $sOfferId] ?? null;

            if ($arCatalogOffer === null) {
                $iAbsent++;
                continue;
            }

            $arChangedFields = $this->diffLinkFields($arStoredRow, $arCatalogOffer);

            if ($arChangedFields === []) {
                $iUnchanged++;
                continue;
            }

            $arChanges[(string) $sOfferId] = $arChangedFields;

            if (array_key_exists('image_url', $arChangedFields) && empty($arStoredRow['is_locked'])) {
                $arReprocess[] = (string) $sOfferId;
            }
        }

        return ['changes' => $arChanges, 'unchanged' => $iUnchanged, 'absent' => $iAbsent, 'reprocess' => $arReprocess];
    }

    /**
     * @param array<string, mixed> $arStoredRow
     * @param array<string, mixed> $arCatalogOffer
     *
     * @return array<string, string> Catalog value of each link field that differs.
     */
    private function diffLinkFields(array $arStoredRow, array $arCatalogOffer): array
    {
        $arChangedFields = [];

        foreach (self::LINK_FIELDS as $sField) {
            $sCatalogValue = (string) ($arCatalogOffer[$sField] ?? '');

            if ((string) ($arStoredRow[$sField] ?? '') !== $sCatalogValue) {
                $arChangedFields[$sField] = $sCatalogValue;
            }
        }

        return $arChangedFields;
    }
}
