<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * ColorScope - narrows offer rows to the 1C groups that get new color entries.
 *
 * An offer is in scope when the group path of its product (category_path_ids,
 * from the top-level group down to the product's own group) holds at least one
 * scope group id, so every subgroup of a scope group is in scope too. A row
 * without a group path is out of scope until the next catalog warm.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
final class ColorScope
{
    /**
     * @param array<int, array<string, mixed>> $arOfferRows      Rows from OfferRowBuilder.
     * @param array<int, string>               $arScopeGroupIds  1C group ids that are in scope.
     *
     * @return array<int, array<string, mixed>> The rows in scope, in catalog order.
     */
    public static function keepInScope(array $arOfferRows, array $arScopeGroupIds): array
    {
        $arScopeGroupIdSet = array_flip($arScopeGroupIds);

        return array_values(array_filter(
            $arOfferRows,
            static function (array $arOfferRow) use ($arScopeGroupIdSet): bool {
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
