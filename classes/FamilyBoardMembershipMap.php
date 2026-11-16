<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * FamilyBoardMembershipMap - the extra colors and collections of each offer, for the Family board.
 *
 * Family rows read: id, family, kind, sort_order. Membership rows read: family_id,
 * offer_id (the full 1C composite id), in_feed, hand_state. Unlike the export
 * index it keeps inactive families, so a collection draft can be filled and
 * checked on the board before the owner switches it on.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class FamilyBoardMembershipMap
{
    public const STATE_FEED = 'feed';

    /** @var array<string, array<int, array{name: string, kind: string, state: string}>> Memberships by offer id in display order. */
    private array $arMembershipsByOfferId = [];

    /** @var array<string, array<string, true>> Offer ids that 1C tags, by family name. */
    private array $arFeedOfferIdsByFamily = [];

    /**
     * @param iterable<int, object> $arFamilyRows
     * @param array<int, array<string, mixed>> $arMembershipRows
     */
    public function __construct(iterable $arFamilyRows, array $arMembershipRows)
    {
        $arFamiliesById = [];

        foreach ($arFamilyRows as $obFamily) {
            $arFamiliesById[(int) $obFamily->id] = $obFamily;
        }

        uasort($arFamiliesById, static fn (object $obLeft, object $obRight): int => [(int) $obLeft->sort_order, (int) $obLeft->id]
            <=> [(int) $obRight->sort_order, (int) $obRight->id]);
        $arPositionByFamilyId = array_flip(array_keys($arFamiliesById));
        $arPositionedMemberships = [];

        foreach ($arMembershipRows as $arRow) {
            $obFamily = $arFamiliesById[(int) $arRow['family_id']] ?? null;
            $sState = $obFamily === null ? null : $this->resolveState($arRow['hand_state'] ?? null, (bool) $arRow['in_feed']);

            if ($sState === null) {
                continue;
            }

            $sOfferId = (string) $arRow['offer_id'];
            $arPositionedMemberships[$sOfferId][$arPositionByFamilyId[(int) $arRow['family_id']]] = [
                'name'  => (string) $obFamily->family,
                'kind'  => FamilyKind::normalize($obFamily->kind ?? null),
                'state' => $sState,
            ];

            if ((bool) $arRow['in_feed'] && ($arRow['hand_state'] ?? null) !== MembershipStateNormalizer::REMOVED) {
                $this->arFeedOfferIdsByFamily[(string) $obFamily->family][$sOfferId] = true;
            }
        }

        foreach ($arPositionedMemberships as $sOfferId => $arByPosition) {
            ksort($arByPosition);
            $this->arMembershipsByOfferId[(string) $sOfferId] = array_values($arByPosition);
        }
    }

    /**
     * @param string $sOfferId Full 1C composite offer id.
     * @param string|null $sPrimaryFamily Effective primary color of the offer, never listed as an extra.
     *
     * @return array{extraColors: array<int, string>, collections: array<int, string>, collectionStates: array<string, string>}
     *               Effective members in display order; the states cover every collection row, removed ones too.
     */
    public function describe(string $sOfferId, ?string $sPrimaryFamily): array
    {
        $arDescription = ['extraColors' => [], 'collections' => [], 'collectionStates' => []];
        $obNormalizer = new MembershipStateNormalizer();

        foreach ($this->arMembershipsByOfferId[$sOfferId] ?? [] as $arMembership) {
            $blEffective = $arMembership['state'] !== MembershipStateNormalizer::REMOVED;

            if ($arMembership['kind'] === FamilyKind::COLLECTION) {
                $arDescription['collectionStates'][$arMembership['name']] = $arMembership['state'];

                if ($blEffective) {
                    $arDescription['collections'][] = $arMembership['name'];
                }
            } elseif ($blEffective && $arMembership['name'] !== $sPrimaryFamily) {
                $arDescription['extraColors'][] = $arMembership['name'];
            }
        }

        return $arDescription;
    }

    /**
     * @param string $sFamilyName Family key.
     * @param array<string, true> $arPresentOfferIds Offer ids that have a board card.
     *
     * @return int Offers 1C tags with the family that the board has no card for (no image).
     */
    public function countFeedOffersWithoutCard(string $sFamilyName, array $arPresentOfferIds): int
    {
        return count(array_diff_key($this->arFeedOfferIdsByFamily[$sFamilyName] ?? [], $arPresentOfferIds));
    }

    /**
     * @return string|null The state to show, or null for a row that is no member and has no hand edit.
     */
    private function resolveState(?string $sHandState, bool $blInFeed): ?string
    {
        return match (true) {
            $sHandState === MembershipStateNormalizer::ADDED => MembershipStateNormalizer::ADDED,
            $sHandState === MembershipStateNormalizer::REMOVED => MembershipStateNormalizer::REMOVED,
            $blInFeed => self::STATE_FEED,
            default => null,
        };
    }
}
