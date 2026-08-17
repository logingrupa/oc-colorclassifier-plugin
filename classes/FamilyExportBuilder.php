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
 * hex. Taxonomy::$familyMeta is hand-authored compile-time data, so a family
 * with a missing slug / hex or a duplicate slug throws instead of being
 * silently dropped from production (fail fast on authored data).
 *
 * Export ORDER is a contract: consumers render family pills in payload key
 * order, which is the hand-curated Taxonomy::$familyMeta order (spectral:
 * Red through Black). The owner rejected confidence-derived ordering for
 * families (1.5.1) - reorder by editing the taxonomy, never by data.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class FamilyExportBuilder
{
    /**
     * Build the families export payload from the taxonomy metadata.
     *
     * The version stamp is derived from the exported payload itself, so it
     * changes exactly when the metadata changes (the family ORDER is the
     * metadata key order) and ETag revalidation stays a 304 otherwise.
     *
     * @return array{version: string, count: int, families: array<string, array{name: string, names: array<string, string>, synonyms: array<string, array<int, string>>, hex: string}>}
     *
     * @throws \RuntimeException When a curated family has a missing slug / hex or a duplicate slug.
     */
    public function build(): array
    {
        $arFamilies = [];

        foreach (Taxonomy::$familyMeta as $sFamilyName => $arMeta) {
            $sSlug = (string) ($arMeta['slug'] ?? '');
            $sHex = (string) ($arMeta['hex'] ?? '');

            // $familyMeta is hand-authored: a typo must fail loudly, never
            // silently drop a family from the production export
            if ($sSlug === '' || $sHex === '') {
                throw new \RuntimeException(
                    'Taxonomy::$familyMeta["' . $sFamilyName . '"] is missing a slug or hex'
                );
            }

            if (array_key_exists($sSlug, $arFamilies)) {
                throw new \RuntimeException(
                    'Taxonomy::$familyMeta["' . $sFamilyName . '"] duplicates slug "' . $sSlug . '"'
                );
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
}
