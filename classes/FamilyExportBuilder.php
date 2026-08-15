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
     * changes exactly when the metadata or the family ORDER changes and
     * ETag revalidation stays a 304 otherwise.
     *
     * @return array{version: string, count: int, families: array<string, array{name: string, names: array<string, string>, synonyms: array<string, array<int, string>>, hex: string}>}
     */
    public function build(): array
    {
        $arFamilies = [];

        foreach (Taxonomy::$familyMeta as $sFamilyName => $arMeta) {
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
}
