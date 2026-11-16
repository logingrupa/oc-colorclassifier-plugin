<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * FamilyBoardPayloadBuilder - the JSON the Family board page boots from.
 *
 * Pure: it receives rows and returns arrays. Every family, hex and confidence
 * on a card comes from EffectiveColorResolver, and the classifier values stay
 * beside them (auto*) so the page can show "classifier said X, shops get Y".
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class FamilyBoardPayloadBuilder
{
    /**
     * @param iterable<int, object> $arEntries ColorEntry rows (without the image blob).
     * @param iterable<int, object> $arFamilyRows ColorFamily rows.
     *
     * @return array{families: array<int, array<string, mixed>>, cards: array<int, array<string, mixed>>, lowConfidenceThreshold: float}
     */
    public function build(iterable $arEntries, iterable $arFamilyRows): array
    {
        $arCards = [];

        foreach ($arEntries as $obEntry) {
            $arCards[] = $this->mapCard($obEntry);
        }

        return [
            'families'               => $this->mapFamilies($arFamilyRows),
            'cards'                  => $arCards,
            'lowConfidenceThreshold' => EffectiveColorResolver::LOW_CONFIDENCE_THRESHOLD,
        ];
    }

    /**
     * One offer card.
     *
     * @param object $obEntry ColorEntry or a plain object with the same columns.
     *
     * @return array<string, mixed>
     */
    public function mapCard(object $obEntry): array
    {
        $arEffective = EffectiveColorResolver::resolve($obEntry);

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
        ];
    }

    /**
     * Families in Families page order; custom means the classifier never outputs it.
     *
     * @param iterable<int, object> $arFamilyRows ColorFamily rows.
     *
     * @return array<int, array{family: string, slug: string, hex: string, active: bool, custom: bool}>
     */
    private function mapFamilies(iterable $arFamilyRows): array
    {
        $arRows = [];

        foreach ($arFamilyRows as $obFamily) {
            $arRows[] = $obFamily;
        }

        usort($arRows, static fn (object $obLeft, object $obRight): int => [(int) $obLeft->sort_order, (int) $obLeft->id]
            <=> [(int) $obRight->sort_order, (int) $obRight->id]);

        return array_map(static fn (object $obFamily): array => [
            'family' => (string) $obFamily->family,
            'slug'   => (string) $obFamily->slug,
            'hex'    => (string) $obFamily->hex,
            'active' => (bool) $obFamily->is_active,
            'custom' => !in_array((string) $obFamily->family, Taxonomy::$colorFamilies, true),
        ], $arRows);
    }
}
