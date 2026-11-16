<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * FamilyMembershipIndex - answers which families an offer belongs to for the exports.
 *
 * Family rows read: id, family, is_active, sort_order. Membership rows read:
 * family_id, offer_id (the full 1C composite id), in_feed, hand_state, shade_code.
 * Inactive families never appear in a list, because families.json leaves
 * inactive collections out and the consumer keeps an offer's links untouched
 * when its list names a family it does not know.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class FamilyMembershipIndex
{
    /** @var array<string, array<int, string>> Effective extra family names by offer id, in display order. */
    private array $arExtraFamilyNamesByOfferId = [];

    /** @var array<string, string> First shade code found by offer id. */
    private array $arShadeCodeByOfferId = [];

    /**
     * @param array<int, array<string, mixed>> $arFamilyRows
     * @param array<int, array<string, mixed>> $arMembershipRows
     */
    public function __construct(array $arFamilyRows = [], array $arMembershipRows = [])
    {
        $arActiveFamilies = $this->sortActiveFamilies($arFamilyRows);
        $obNormalizer = new MembershipStateNormalizer();
        $arEffectiveFamilyIdsByOfferId = [];

        foreach ($arMembershipRows as $arRow) {
            $sOfferId = (string) $arRow['offer_id'];
            $sShadeCode = (string) ($arRow['shade_code'] ?? '');

            if ($sShadeCode !== '') {
                $this->arShadeCodeByOfferId[$sOfferId] ??= $sShadeCode;
            }

            if ($obNormalizer->isEffective($arRow['hand_state'] ?? null, (bool) $arRow['in_feed'])) {
                $arEffectiveFamilyIdsByOfferId[$sOfferId][(int) $arRow['family_id']] = true;
            }
        }

        foreach ($arEffectiveFamilyIdsByOfferId as $sOfferId => $arFamilyIds) {
            $this->arExtraFamilyNamesByOfferId[(string) $sOfferId] = array_values(array_map(
                static fn (array $arFamily): string => (string) $arFamily['family'],
                array_filter($arActiveFamilies, static fn (array $arFamily): bool => isset($arFamilyIds[(int) $arFamily['id']]))
            ));
        }
    }

    /**
     * @param string $sOfferId Full 1C composite offer id.
     * @param string $sPrimaryFamily Effective primary color key of the offer.
     *
     * @return array<int, string> The primary first, then every other active member family, no duplicates.
     */
    public function listFamilyNames(string $sOfferId, string $sPrimaryFamily): array
    {
        $arExtraNames = array_filter(
            $this->arExtraFamilyNamesByOfferId[$sOfferId] ?? [],
            static fn (string $sName): bool => $sName !== $sPrimaryFamily
        );

        return array_merge([$sPrimaryFamily], array_values($arExtraNames));
    }

    /**
     * @param string $sOfferId Full 1C composite offer id.
     */
    public function findShadeCode(string $sOfferId): ?string
    {
        return $this->arShadeCodeByOfferId[$sOfferId] ?? null;
    }

    /**
     * @param array<int, array<string, mixed>> $arFamilyRows
     *
     * @return array<int, array<string, mixed>> Active rows by sort_order, then id.
     */
    private function sortActiveFamilies(array $arFamilyRows): array
    {
        $arActiveFamilies = array_filter($arFamilyRows, static fn (array $arFamily): bool => (bool) $arFamily['is_active']);

        usort($arActiveFamilies, static fn (array $arLeft, array $arRight): int => [(int) $arLeft['sort_order'], (int) $arLeft['id']]
            <=> [(int) $arRight['sort_order'], (int) $arRight['id']]);

        return $arActiveFamilies;
    }
}
