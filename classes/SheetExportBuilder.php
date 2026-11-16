<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * SheetExportBuilder - maps ColorEntry rows to the compact offer color export.
 *
 * Sole responsibility: turn ColorEntry rows into the minimal array consumed
 * server-to-server by an external store (join key: offer UUID, the part of
 * offer_id after the last "#"). No routing, no HTTP concerns, no caching.
 *
 * Exported shape per offer: family, hex, hue, lightness, confidence (the
 * classification certainty consumers order shades within a family by;
 * null when the row has none) and manual (true when an admin overrode the
 * family or hex). Values are the EFFECTIVE ones from EffectiveColorResolver.
 * Rows without an effective hex or family, or without a numeric hue and
 * lightness, are skipped so a bad row can never break the export.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class SheetExportBuilder
{
    /**
     * Build the compact export payload from ColorEntry rows.
     *
     * The version stamp is the sha1 of the encoded exported rows plus the
     * latest updated_at, so it rotates exactly when any exported offer value,
     * the payload shape (fields added / removed / reordered), the set of
     * exported keys, or last_updated_at changes - and stays stable otherwise.
     * The same latest updated_at is exposed as last_updated_at (one global
     * value, not per offer) so consumers can see when data last changed.
     * Count reflects exported rows only, not total rows. Rows resolving to
     * an empty offer key or to an already-exported key are skipped
     * (first row wins on collision).
     *
     * @param iterable<int, object> $arColorEntries ColorEntry rows.
     *
     * @return array{version: string, last_updated_at: string, count: int, offers: array<string, array{family: string, hex: string, hue: float, lightness: float, confidence: float|null, manual: bool}>}
     */
    public function build(iterable $arColorEntries): array
    {
        $arOffers = [];
        $sLatestUpdatedAt = '';

        foreach ($arColorEntries as $obEntry) {
            $arOfferData = $this->buildOfferData($obEntry);

            if ($arOfferData === null) {
                continue;
            }

            $sOfferKey = self::resolveOfferKey((string) $obEntry->offer_id);

            if ($sOfferKey === '' || array_key_exists($sOfferKey, $arOffers)) {
                continue;
            }

            $arOffers[$sOfferKey] = $arOfferData;

            $sUpdatedAt = (string) ($obEntry->updated_at ?? '');
            if ($sUpdatedAt > $sLatestUpdatedAt) {
                $sLatestUpdatedAt = $sUpdatedAt;
            }
        }

        return [
            'version'         => $this->buildVersionStamp($sLatestUpdatedAt, $arOffers),
            'last_updated_at' => $sLatestUpdatedAt,
            'count'           => count($arOffers),
            'offers'          => $arOffers,
        ];
    }

    /**
     * Map a single ColorEntry row to its compact offer data, or null to skip.
     *
     * Skips the row when offer_id, the effective hex or the effective family
     * is empty, or when the effective hue / lightness is not numeric.
     *
     * @param object $obEntry ColorEntry row.
     *
     * @return array{family: string, hex: string, hue: float, lightness: float, confidence: float|null, manual: bool}|null
     */
    private function buildOfferData(object $obEntry): ?array
    {
        if ((string) ($obEntry->offer_id ?? '') === '') {
            return null;
        }

        $arEffective = EffectiveColorResolver::resolve($obEntry);

        if ($arEffective['hex'] === null || $arEffective['family'] === null) {
            return null;
        }

        if (!is_numeric($arEffective['hue']) || !is_numeric($arEffective['lightness'])) {
            return null;
        }

        // A missing or malformed score exports as null rather than skipping the
        // row - the color data is still good, only the in-family ordering
        // signal is absent
        return [
            'family'     => $arEffective['family'],
            'hex'        => $arEffective['hex'],
            'hue'        => (float) $arEffective['hue'],
            'lightness'  => (float) $arEffective['lightness'],
            'confidence' => $arEffective['confidence'],
            'manual'     => $arEffective['manual'],
        ];
    }

    /**
     * Resolve the public offer key from the internal composite offer_id.
     *
     * The internal offer_id is a CommerceML composite "productUUID#offerUUID";
     * external consumers join on the offer UUID alone, so only the part after
     * the last "#" is exposed. Keys without "#" are returned unchanged.
     *
     * @param string $sOfferId Internal ColorEntry offer_id.
     *
     * @return string
     */
    public static function resolveOfferKey(string $sOfferId): string
    {
        $iLastHashPosition = strrpos($sOfferId, '#');

        if ($iLastHashPosition === false) {
            return $sOfferId;
        }

        return substr($sOfferId, $iLastHashPosition + 1);
    }

    /**
     * Build a deterministic version stamp for the exported data set.
     *
     * Hashes the same json_encode used for the response body, so the stamp
     * rotates whenever the encoded rows change in any way - values, field
     * shape, key set - never only on updated_at / count like before (1.5.2).
     *
     * @param string $sLatestUpdatedAt Latest updated_at among exported rows.
     * @param array<string, array{family: string, hex: string, hue: float, lightness: float, confidence: float|null, manual: bool}> $arOffers Exported rows keyed by offer UUID.
     *
     * @return string
     */
    private function buildVersionStamp(string $sLatestUpdatedAt, array $arOffers): string
    {
        return sha1($sLatestUpdatedAt . '|' . (string) json_encode($arOffers));
    }
}
