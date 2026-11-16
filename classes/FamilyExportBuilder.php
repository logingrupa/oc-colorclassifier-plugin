<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * FamilyExportBuilder - maps color_families rows to the compact families export.
 *
 * Sole responsibility: turn the rows the route queries into the payload
 * consumed server-to-server by an external store (join/URL key: family slug).
 * No routing, no HTTP concerns, no caching, no database access - the same
 * split SheetExportBuilder keeps for offers.json.
 *
 * Exported shape per family (keyed by slug): name (the family key used by
 * offers.json), localized display names, per-locale synonym lists, hex,
 * sort_order and active. Inactive families stay in the export with
 * active false, because consumers delete shop rows whose slug disappears.
 *
 * Export ORDER is a contract: consumers render family pills in payload key
 * order, which is the Families page sort order (sort_order, then id).
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class FamilyExportBuilder
{
    /**
     * Build the families export payload.
     *
     * The version stamp is derived from the exported payload itself, so it
     * changes exactly when a name, synonym, hex, order or active flag changes
     * and ETag revalidation stays a 304 otherwise.
     *
     * @param iterable<int, object|array<string, mixed>> $arFamilyRows color_families rows (models, objects or arrays).
     *
     * @return array{version: string, count: int, families: array<string, array{name: string, names: array<string, string>, synonyms: array<string, array<int, string>>, hex: string, sort_order: int, active: bool}>}
     *
     * @throws \RuntimeException When a family has a missing slug or hex, or a slug appears twice.
     */
    public function build(iterable $arFamilyRows): array
    {
        $arFamilies = [];

        foreach ($this->sortRows($arFamilyRows) as $arRow) {
            $sFamilyName = (string) $arRow['family'];
            $sSlug = (string) $arRow['slug'];
            $sHex = (string) $arRow['hex'];

            if ($sSlug === '' || $sHex === '') {
                throw new \RuntimeException('Family "' . $sFamilyName . '" is missing a slug or hex');
            }

            if (array_key_exists($sSlug, $arFamilies)) {
                throw new \RuntimeException('Family "' . $sFamilyName . '" duplicates slug "' . $sSlug . '"');
            }

            $arFamilies[$sSlug] = [
                'name'       => $sFamilyName,
                'names'      => (array) $arRow['names'],
                'synonyms'   => (array) $arRow['synonyms'],
                'hex'        => $sHex,
                'sort_order' => (int) $arRow['sort_order'],
                'active'     => (bool) $arRow['is_active'],
            ];
        }

        return [
            'version'  => sha1((string) json_encode($arFamilies)),
            'count'    => count($arFamilies),
            'families' => $arFamilies,
        ];
    }

    /**
     * Read every row into one array shape and order it by sort_order, then id.
     *
     * @param iterable<int, object|array<string, mixed>> $arFamilyRows
     *
     * @return array<int, array<string, mixed>>
     */
    private function sortRows(iterable $arFamilyRows): array
    {
        $arRows = [];

        foreach ($arFamilyRows as $mFamilyRow) {
            $arRows[] = [
                'id'         => $this->readField($mFamilyRow, 'id'),
                'family'     => $this->readField($mFamilyRow, 'family'),
                'slug'       => $this->readField($mFamilyRow, 'slug'),
                'hex'        => $this->readField($mFamilyRow, 'hex'),
                'names'      => $this->readField($mFamilyRow, 'names'),
                'synonyms'   => $this->readField($mFamilyRow, 'synonyms'),
                'sort_order' => $this->readField($mFamilyRow, 'sort_order'),
                'is_active'  => $this->readField($mFamilyRow, 'is_active'),
            ];
        }

        usort($arRows, static fn (array $arLeft, array $arRight): int => [(int) $arLeft['sort_order'], (int) $arLeft['id']]
            <=> [(int) $arRight['sort_order'], (int) $arRight['id']]);

        return $arRows;
    }

    /**
     * @param object|array<string, mixed> $mFamilyRow Model, object or array row.
     * @param string $sField Column name.
     *
     * @return mixed
     */
    private function readField(object|array $mFamilyRow, string $sField): mixed
    {
        return is_array($mFamilyRow) ? ($mFamilyRow[$sField] ?? null) : ($mFamilyRow->{$sField} ?? null);
    }
}
