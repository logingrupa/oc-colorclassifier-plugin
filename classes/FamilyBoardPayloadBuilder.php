<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * FamilyBoardPayloadBuilder - the JSON the Family board page boots from.
 *
 * Pure: it receives rows and returns arrays. Every family, hex and confidence
 * on a card comes from EffectiveColorResolver, and the classifier values stay
 * beside them (auto*) so the page can show "classifier said X, shops get Y".
 * Extra colors and collections come from FamilyBoardMembershipMap.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class FamilyBoardPayloadBuilder
{
    /**
     * @param iterable<int, object> $arEntries ColorEntry rows (without the image blob).
     * @param iterable<int, object> $arFamilyRows ColorFamily rows.
     * @param array<int, array<string, mixed>> $arMembershipRows Family membership rows.
     *
     * @return array{families: array<int, array<string, mixed>>, cards: array<int, array<string, mixed>>, lowConfidenceThreshold: float}
     */
    public function build(iterable $arEntries, iterable $arFamilyRows, array $arMembershipRows = []): array
    {
        $arFamilyList = $this->toList($arFamilyRows);
        $arEntryList = $this->toList($arEntries);
        $arPresentOfferIds = [];

        foreach ($arEntryList as $obEntry) {
            $arPresentOfferIds[(string) $obEntry->offer_id] = true;
        }

        $obMembershipMap = new FamilyBoardMembershipMap($arFamilyList, $arMembershipRows);

        return [
            'families'               => $this->mapFamilies($arFamilyList, $obMembershipMap, $arPresentOfferIds),
            'cards'                  => $this->mapCardsWithMap($arEntryList, $obMembershipMap),
            'lowConfidenceThreshold' => EffectiveColorResolver::LOW_CONFIDENCE_THRESHOLD,
        ];
    }

    /**
     * @param iterable<int, object> $arEntries ColorEntry rows.
     * @param iterable<int, object> $arFamilyRows ColorFamily rows.
     * @param array<int, array<string, mixed>> $arMembershipRows Family membership rows.
     *
     * @return array<int, array<string, mixed>> One card per entry.
     */
    public function mapCards(iterable $arEntries, iterable $arFamilyRows, array $arMembershipRows): array
    {
        return $this->mapCardsWithMap($arEntries, new FamilyBoardMembershipMap($arFamilyRows, $arMembershipRows));
    }

    /**
     * @param iterable<int, object> $arEntries ColorEntry rows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapCardsWithMap(iterable $arEntries, FamilyBoardMembershipMap $obMembershipMap): array
    {
        $arCards = [];

        foreach ($arEntries as $obEntry) {
            $arCards[] = $this->mapCard($obEntry, $obMembershipMap);
        }

        return $arCards;
    }

    /**
     * One offer card.
     *
     * @param object $obEntry ColorEntry or a plain object with the same columns.
     * @param FamilyBoardMembershipMap|null $obMembershipMap Without a map the card has no extra groups.
     *
     * @return array<string, mixed>
     */
    public function mapCard(object $obEntry, ?FamilyBoardMembershipMap $obMembershipMap = null): array
    {
        $arEffective = EffectiveColorResolver::resolve($obEntry);
        $arMemberships = ($obMembershipMap ?? new FamilyBoardMembershipMap([], []))->describe((string) $obEntry->offer_id, $arEffective['family']);

        return [
            'id'               => (int) $obEntry->id,
            'offerKey'         => SheetExportBuilder::resolveOfferKey((string) $obEntry->offer_id),
            'productName'      => (string) ($obEntry->product_name ?? ''),
            'variationName'    => (string) ($obEntry->variation_name ?? ''),
            'colorName'        => (string) ($obEntry->color_name ?? ''),
            'imageUrl'         => (string) ($obEntry->image_url ?? ''),
            'palette'          => is_array($obEntry->palette_colors ?? null) ? array_values($obEntry->palette_colors) : [],
            'family'           => $arEffective['family'],
            'autoFamily'       => $arEffective['auto_family'],
            'familyOverridden' => $arEffective['family_overridden'],
            'hex'              => $arEffective['hex'],
            'autoHex'          => $arEffective['auto_hex'],
            'hexOverridden'    => $arEffective['hex_overridden'],
            'confidence'       => $arEffective['confidence'],
            'autoConfidence'   => $arEffective['auto_confidence'],
            'manual'           => $arEffective['manual'],
            'locked'           => (bool) ($obEntry->is_locked ?? false),
            'extraColors'      => $arMemberships['extraColors'],
            'collections'      => $arMemberships['collections'],
            'collectionStates' => $arMemberships['collectionStates'],
        ];
    }

    /**
     * Families in Families page order; custom means the classifier never outputs it.
     *
     * @param array<int, object> $arFamilyRows ColorFamily rows.
     * @param array<string, true> $arPresentOfferIds Offer ids that have a card.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapFamilies(array $arFamilyRows, FamilyBoardMembershipMap $obMembershipMap, array $arPresentOfferIds): array
    {
        usort($arFamilyRows, static fn (object $obLeft, object $obRight): int => [(int) $obLeft->sort_order, (int) $obLeft->id]
            <=> [(int) $obRight->sort_order, (int) $obRight->id]);

        return array_map(static function (object $obFamily) use ($obMembershipMap, $arPresentOfferIds): array {
            $sKind = FamilyKind::normalize($obFamily->kind ?? null);
            $sFeedValueId = (string) ($obFamily->feed_value_id ?? '');

            return [
                'family'                => (string) $obFamily->family,
                'slug'                  => (string) $obFamily->slug,
                'hex'                   => (string) $obFamily->hex,
                'active'                => (bool) $obFamily->is_active,
                'custom'                => $sKind === FamilyKind::COLOR && !in_array((string) $obFamily->family, Taxonomy::$colorFamilies, true),
                'kind'                  => $sKind,
                'fromFeed'              => $sFeedValueId !== '',
                'gradient'              => is_array($obFamily->gradient ?? null) ? array_values($obFamily->gradient) : null,
                'feedOffersWithoutCard' => $sKind === FamilyKind::COLLECTION
                    ? $obMembershipMap->countFeedOffersWithoutCard((string) $obFamily->family, $arPresentOfferIds)
                    : 0,
            ];
        }, $arFamilyRows);
    }

    /**
     * @param iterable<int, object> $obRows
     *
     * @return array<int, object>
     */
    private function toList(iterable $obRows): array
    {
        $arList = [];

        foreach ($obRows as $obRow) {
            $arList[] = $obRow;
        }

        return $arList;
    }
}
