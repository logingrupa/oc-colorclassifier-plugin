<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * FamilyExportBuilder - maps the family taxonomy metadata to the compact
 * families export.
 *
 * Sole responsibility: turn Taxonomy::$familyMeta into the payload consumed
 * server-to-server by an external store (join/URL key: family slug). No
 * routing, no HTTP concerns, no caching - the same split SheetExportBuilder
 * keeps for offers.json.
 *
 * Exported shape per family (keyed by slug): name (the family key used by
 * offers.json), localized display names, per-locale synonym lists, canonical
 * hex. Families missing a slug or hex are skipped so a malformed entry can
 * never break the export.
 *
 * Export ORDER is a contract: consumers render family pills in payload key
 * order. Families are ordered by average ColorEntry confidence descending
 * (confidently classified families first, boundary-straddling ones sink);
 * families without any classified offer sink below all scored ones. Ties and
 * the unscored tail keep the hand-curated Taxonomy::$familyMeta order, which
 * is also the complete order when no entries are passed at all.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class FamilyExportBuilder
{
    /**
     * Build the families export payload from the taxonomy metadata and the
     * classified ColorEntry rows.
     *
     * The version stamp is derived from the exported payload itself, so it
     * changes exactly when the metadata or the family ORDER changes - a
     * confidence drift that does not reorder anything keeps the stamp, and
     * ETag revalidation stays a 304.
     *
     * @param iterable<int, object> $arColorEntries ColorEntry rows (taxonomy + confidence_score are read).
     *
     * @return array{version: string, count: int, families: array<string, array{name: string, names: array<string, string>, synonyms: array<string, array<int, string>>, hex: string}>}
     */
    public function build(iterable $arColorEntries = []): array
    {
        $arConfidenceByFamily = $this->aggregateConfidence($arColorEntries);

        $arFamilies = [];

        foreach ($this->orderFamilyNames($arConfidenceByFamily) as $sFamilyName) {
            $arMeta = Taxonomy::$familyMeta[$sFamilyName];
            $sSlug = (string) ($arMeta['slug'] ?? '');
            $sHex = (string) ($arMeta['hex'] ?? '');

            if ($sSlug === '' || $sHex === '' || array_key_exists($sSlug, $arFamilies)) {
                continue;
            }

            $arFamilies[$sSlug] = [
                'name'     => $sFamilyName,
                'names'    => (array) ($arMeta['names'] ?? []),
                'synonyms' => (array) ($arMeta['synonyms'] ?? []),
                'hex'      => $sHex,
            ];
        }

        return [
            'version'  => sha1((string) json_encode($arFamilies)),
            'count'    => count($arFamilies),
            'families' => $arFamilies,
        ];
    }

    /**
     * Average confidence_score per taxonomy family from the classified rows.
     *
     * Rows with malformed taxonomy, a family unknown to the taxonomy, or a
     * non-numeric confidence are skipped - a bad row says nothing about a
     * family's certainty and must not drag its average.
     *
     * @param iterable<int, object> $arColorEntries ColorEntry rows.
     *
     * @return array<string, float> Average confidence keyed by family name.
     */
    private function aggregateConfidence(iterable $arColorEntries): array
    {
        $arSumByFamily = [];
        $arCountByFamily = [];

        foreach ($arColorEntries as $obEntry) {
            $arTaxonomy = $obEntry->taxonomy ?? null;

            if (!is_array($arTaxonomy)) {
                continue;
            }

            $sFamilyName = $arTaxonomy['family'] ?? null;

            if (!is_string($sFamilyName) || !array_key_exists($sFamilyName, Taxonomy::$familyMeta)) {
                continue;
            }

            $flConfidence = $obEntry->confidence_score ?? null;

            if (!is_numeric($flConfidence)) {
                continue;
            }

            $arSumByFamily[$sFamilyName] = ($arSumByFamily[$sFamilyName] ?? 0.0) + (float) $flConfidence;
            $arCountByFamily[$sFamilyName] = ($arCountByFamily[$sFamilyName] ?? 0) + 1;
        }

        $arAverageByFamily = [];

        foreach ($arSumByFamily as $sFamilyName => $flSum) {
            $arAverageByFamily[$sFamilyName] = $flSum / $arCountByFamily[$sFamilyName];
        }

        return $arAverageByFamily;
    }

    /**
     * The export order: scored families by average confidence descending,
     * then unscored families; inside each group and on equal averages the
     * hand-curated taxonomy order decides, so the result is deterministic
     * for one data set.
     *
     * @param array<string, float> $arConfidenceByFamily Average confidence keyed by family name.
     *
     * @return array<int, string> Family names in export order.
     */
    private function orderFamilyNames(array $arConfidenceByFamily): array
    {
        $arTaxonomyPosition = array_flip(array_keys(Taxonomy::$familyMeta));
        $arFamilyNames = array_keys(Taxonomy::$familyMeta);

        usort(
            $arFamilyNames,
            function (string $sFirst, string $sSecond) use ($arConfidenceByFamily, $arTaxonomyPosition): int {
                $bFirstScored = array_key_exists($sFirst, $arConfidenceByFamily);
                $bSecondScored = array_key_exists($sSecond, $arConfidenceByFamily);

                if ($bFirstScored !== $bSecondScored) {
                    return $bFirstScored ? -1 : 1;
                }

                if ($bFirstScored && $arConfidenceByFamily[$sFirst] !== $arConfidenceByFamily[$sSecond]) {
                    return $arConfidenceByFamily[$sSecond] <=> $arConfidenceByFamily[$sFirst];
                }

                return $arTaxonomyPosition[$sFirst] <=> $arTaxonomyPosition[$sSecond];
            }
        );

        return $arFamilyNames;
    }
}
