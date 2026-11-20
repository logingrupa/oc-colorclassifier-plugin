<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * ColorScope - narrows offer rows to the offers that get new color entries.
 *
 * An offer is in scope when the group path of its product (category_path_ids,
 * from the top-level group down to the product's own group) holds at least one
 * scope group id, so every subgroup of a scope group is in scope too, or when
 * its product id (the part of the offer id before "#") is on the product list.
 * An excluded product is out of scope whatever its group or listing. A row
 * without a group path is out of scope until the next catalog warm, unless its
 * product is listed.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
final class ColorScope
{
    /**
     * @param array<int, array<string, mixed>> $arOfferRows                Rows from OfferRowBuilder.
     * @param array<int, string>               $arScopeGroupIds            1C group ids that are in scope.
     * @param array<int, string>               $arScopeProductIds          1C product ids that are in scope whatever their group.
     * @param array<int, string>               $arExcludedProductIds       1C product ids that are never in scope.
     *
     * @return array<int, array<string, mixed>> The rows in scope, in catalog order.
     */
    public static function keepInScope(array $arOfferRows, array $arScopeGroupIds, array $arScopeProductIds, array $arExcludedProductIds): array
    {
        $arScopeGroupIdSet      = array_flip($arScopeGroupIds);
        $arScopeProductIdSet    = array_flip($arScopeProductIds);
        $arExcludedProductIdSet = array_flip($arExcludedProductIds);

        return array_values(array_filter(
            $arOfferRows,
            static function (array $arOfferRow) use ($arScopeGroupIdSet, $arScopeProductIdSet, $arExcludedProductIdSet): bool {
                $sProductId = strtok((string) ($arOfferRow['offer_id'] ?? ''), '#');

                if (isset($arExcludedProductIdSet[$sProductId])) {
                    return false;
                }

                if (isset($arScopeProductIdSet[$sProductId])) {
                    return true;
                }

                foreach ((array) ($arOfferRow['category_path_ids'] ?? []) as $sGroupId) {
                    if (isset($arScopeGroupIdSet[$sGroupId])) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }
}
