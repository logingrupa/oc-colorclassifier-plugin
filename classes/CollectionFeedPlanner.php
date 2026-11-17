<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * CollectionFeedPlanner - pure planner that mirrors 1C collection tags into families and memberships.
 *
 * Rows are keyed by feed_value_id. A new 1C value becomes an active collection.
 * The planner only creates rows for value ids without one and only writes the
 * sync-owned columns (in_feed, shade_code, feed_value_name), never is_active or
 * hand_state, so a collection switched off by hand stays off. Guards run first
 * and abort with a reason and no writes.
 *
 * Family rows read: id, family, slug, kind, sort_order, feed_value_id, feed_value_name.
 * Membership rows read: id, family_id, offer_id, in_feed, hand_state, shade_code.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class CollectionFeedPlanner
{
    /** Rule of the ColorFamily key column, shared with the model. */
    public const KEY_PATTERN = '/^[A-Za-z][A-Za-z0-9 -]*$/';

    public const KEY_MAX_LENGTH = 64;

    private const MIN_OFFER_RATIO = 0.5;

    private const DRAFT_HEX = '#9e9e9e';

    private const SLUG_BASE_MAX_LENGTH = 60;

    private const LOCALES = ['en', 'lv', 'ru', 'lt', 'nb-no'];

    /**
     * @param string $sCharacteristicId 1C КодХарактеристики the collection tags come from.
     */
    public function __construct(private readonly string $sCharacteristicId)
    {
    }

    /**
     * @param array{has_collection_key: bool, offer_count: int, offers: array<string, array{shade_code: string|null, collections: array<int, array{value_id: string, value: string}>}>} $arTags CollectionTagExtractor output.
     * @param array<int, array<string, mixed>> $arFamilyRows Every color_families row, both kinds.
     * @param array<int, array<string, mixed>> $arMembershipRows Every membership row.
     * @param int|null $iLastOfferCount Offer count of the last applied run.
     * @param bool $blAllowEmpty Allow a collection to lose its last 1C member.
     */
    public function plan(array $arTags, array $arFamilyRows, array $arMembershipRows, ?int $iLastOfferCount, bool $blAllowEmpty): CollectionFeedPlan
    {
        $sCatalogAbort = $this->findCatalogAbortReason($arTags, $iLastOfferCount);

        if ($sCatalogAbort !== null) {
            return CollectionFeedPlan::abort($sCatalogAbort);
        }

        [$arNames, $arMembers] = $this->collectFeedMembers($arTags);
        $arFamilyByValueId = $this->indexCollectionFamilies($arFamilyRows);
        $arRowsByValueId = $this->indexCollectionRows($arFamilyByValueId, $arMembershipRows);

        if (!$blAllowEmpty) {
            $sEmptiedAbort = $this->findEmptiedCollection($arFamilyByValueId, $arRowsByValueId, $arMembers);

            if ($sEmptiedAbort !== null) {
                return CollectionFeedPlan::abort($sEmptiedAbort);
            }
        }

        [$arDrafts, $arSkippedValues] = $this->planDrafts($arNames, $arFamilyByValueId, $arFamilyRows);

        return $this->planMemberships(
            $arTags,
            $arDrafts,
            $this->planRenames($arNames, $arFamilyByValueId),
            array_diff_key($arMembers, array_column($arSkippedValues, null, 'value_id')),
            $arRowsByValueId,
            $arMembershipRows,
            $arSkippedValues
        );
    }

    /**
     * @param array<string, mixed> $arTags
     */
    private function findCatalogAbortReason(array $arTags, ?int $iLastOfferCount): ?string
    {
        if (!$arTags['has_collection_key']) {
            return 'the catalog has no collections key (cache from before this feature)';
        }

        if ($arTags['offer_count'] === 0) {
            return 'the catalog is empty';
        }

        if ($iLastOfferCount !== null && $arTags['offer_count'] < $iLastOfferCount * self::MIN_OFFER_RATIO) {
            return 'the catalog has ' . $arTags['offer_count'] . ' offers, under half of the last run (' . $iLastOfferCount . ')';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $arTags
     *
     * @return array{0: array<string, string>, 1: array<string, array<string, string|null>>} Names by value id, shade code by offer id by value id.
     */
    private function collectFeedMembers(array $arTags): array
    {
        $arNames = [];
        $arMembers = [];

        foreach ($arTags['offers'] as $sOfferId => $arOffer) {
            foreach ($arOffer['collections'] as $arCollection) {
                $sValueId = trim((string) ($arCollection['value_id'] ?? ''));
                $sValue = trim((string) ($arCollection['value'] ?? ''));

                if ($sValueId === '' || $sValue === '') {
                    continue;
                }

                $arNames[$sValueId] ??= $sValue;
                $arMembers[$sValueId][(string) $sOfferId] = $arOffer['shade_code'];
            }
        }

        return [$arNames, $arMembers];
    }

    /**
     * @param array<int, array<string, mixed>> $arFamilyRows
     *
     * @return array<string, array<string, mixed>> Collection family rows by feed value id.
     */
    private function indexCollectionFamilies(array $arFamilyRows): array
    {
        $arFamilyByValueId = [];

        foreach ($arFamilyRows as $arFamilyRow) {
            $sValueId = (string) ($arFamilyRow['feed_value_id'] ?? '');

            if (FamilyKind::normalize($arFamilyRow['kind'] ?? null) === FamilyKind::COLLECTION && $sValueId !== '') {
                $arFamilyByValueId[$sValueId] = $arFamilyRow;
            }
        }

        return $arFamilyByValueId;
    }

    /**
     * @param array<string, array<string, mixed>> $arFamilyByValueId
     * @param array<int, array<string, mixed>> $arMembershipRows
     *
     * @return array<string, array<string, array<string, mixed>>> Membership rows by offer id by value id.
     */
    private function indexCollectionRows(array $arFamilyByValueId, array $arMembershipRows): array
    {
        $arValueIdByFamilyId = [];

        foreach ($arFamilyByValueId as $sValueId => $arFamilyRow) {
            $arValueIdByFamilyId[(int) $arFamilyRow['id']] = (string) $sValueId;
        }

        $arRowsByValueId = [];

        foreach ($arMembershipRows as $arMembershipRow) {
            $sValueId = $arValueIdByFamilyId[(int) $arMembershipRow['family_id']] ?? null;

            if ($sValueId !== null) {
                $arRowsByValueId[$sValueId][(string) $arMembershipRow['offer_id']] = $arMembershipRow;
            }
        }

        return $arRowsByValueId;
    }

    /**
     * @param array<string, array<string, mixed>> $arFamilyByValueId
     * @param array<string, array<string, array<string, mixed>>> $arRowsByValueId
     * @param array<string, array<string, string|null>> $arMembers
     */
    private function findEmptiedCollection(array $arFamilyByValueId, array $arRowsByValueId, array $arMembers): ?string
    {
        foreach ($arFamilyByValueId as $sValueId => $arFamilyRow) {
            $iFeedMemberCount = count(array_filter(
                $arRowsByValueId[$sValueId] ?? [],
                static fn (array $arRow): bool => (bool) $arRow['in_feed']
            ));

            if ($iFeedMemberCount > 0 && empty($arMembers[$sValueId])) {
                return 'collection "' . $arFamilyRow['family'] . '" would drop from ' . $iFeedMemberCount . ' feed members to none (use --allow-empty)';
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $arNames
     * @param array<string, array<string, mixed>> $arFamilyByValueId
     * @param array<int, array<string, mixed>> $arFamilyRows
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array{value_id: string, name: string, reason: string}>} Drafts, and the new values that cannot be a family key.
     */
    private function planDrafts(array $arNames, array $arFamilyByValueId, array $arFamilyRows): array
    {
        $arNewNames = array_diff_key($arNames, $arFamilyByValueId);
        uasort($arNewNames, 'strnatcasecmp');

        $arTakenKeys = array_flip(array_map(static fn (array $arRow): string => strtolower((string) $arRow['family']), $arFamilyRows));
        $arTakenSlugs = array_flip(array_map(static fn (array $arRow): string => (string) $arRow['slug'], $arFamilyRows));
        $iSortOrder = (int) max(array_merge([0], array_map(static fn (array $arRow): int => (int) $arRow['sort_order'], $arFamilyRows)));
        $arDrafts = [];
        $arSkippedValues = [];

        foreach ($arNewNames as $sValueId => $sName) {
            $sSkipReason = null;

            if (preg_match(self::KEY_PATTERN, $sName) !== 1 || strlen($sName) > self::KEY_MAX_LENGTH) {
                $sSkipReason = 'cannot be a family key';
            } elseif (isset($arTakenKeys[strtolower($sName)])) {
                $sSkipReason = 'collides with an existing family';
            }

            if ($sSkipReason !== null) {
                $arSkippedValues[] = ['value_id' => (string) $sValueId, 'name' => $sName, 'reason' => $sSkipReason];
                continue;
            }

            $arTakenKeys[strtolower($sName)] = true;
            $arDrafts[] = $this->buildDraft((string) $sValueId, $sName, $this->buildSlug($sName, $arTakenSlugs), ++$iSortOrder);
        }

        return [$arDrafts, $arSkippedValues];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDraft(string $sValueId, string $sName, string $sSlug, int $iSortOrder): array
    {
        return [
            'kind'                   => FamilyKind::COLLECTION,
            'family'                 => $sName,
            'slug'                   => $sSlug,
            'hex'                    => self::DRAFT_HEX,
            'names'                  => array_fill_keys(self::LOCALES, $sName),
            'synonyms'               => array_fill_keys(self::LOCALES, []),
            'is_active'              => true,
            'sort_order'             => $iSortOrder,
            'feed_characteristic_id' => $this->sCharacteristicId,
            'feed_value_id'          => $sValueId,
            'feed_value_name'        => $sName,
        ];
    }

    /**
     * @param array<string, bool> $arTakenSlugs Slugs in use, extended with the returned one.
     */
    private function buildSlug(string $sKey, array &$arTakenSlugs): string
    {
        $sBaseSlug = substr(trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($sKey)), '-'), 0, self::SLUG_BASE_MAX_LENGTH);
        $sSlug = $sBaseSlug;

        for ($iSuffix = 2; isset($arTakenSlugs[$sSlug]); $iSuffix++) {
            $sSlug = $sBaseSlug . '-' . $iSuffix;
        }

        $arTakenSlugs[$sSlug] = true;

        return $sSlug;
    }

    /**
     * @param array<string, string> $arNames
     * @param array<string, array<string, mixed>> $arFamilyByValueId
     *
     * @return array<int, array{value_id: string, feed_value_name: string}>
     */
    private function planRenames(array $arNames, array $arFamilyByValueId): array
    {
        $arRenames = [];

        foreach ($arFamilyByValueId as $sValueId => $arFamilyRow) {
            if (isset($arNames[$sValueId]) && $arNames[$sValueId] !== ($arFamilyRow['feed_value_name'] ?? null)) {
                $arRenames[] = ['value_id' => (string) $sValueId, 'feed_value_name' => $arNames[$sValueId]];
            }
        }

        return $arRenames;
    }

    /**
     * @param array<string, mixed> $arTags
     * @param array<int, array<string, mixed>> $arDrafts
     * @param array<int, array{value_id: string, feed_value_name: string}> $arRenames
     * @param array<string, array<string, string|null>> $arMembers
     * @param array<string, array<string, array<string, mixed>>> $arRowsByValueId
     * @param array<int, array<string, mixed>> $arMembershipRows
     * @param array<int, array{value_id: string, name: string, reason: string}> $arSkippedValues
     */
    private function planMemberships(array $arTags, array $arDrafts, array $arRenames, array $arMembers, array $arRowsByValueId, array $arMembershipRows, array $arSkippedValues): CollectionFeedPlan
    {
        $arSets = [];
        $arUnsets = [];
        $arDeletes = [];
        $arHandledRowIds = [];

        foreach ($arMembers as $sValueId => $arOfferShades) {
            foreach ($arOfferShades as $sOfferId => $sShadeCode) {
                $arRow = $arRowsByValueId[$sValueId][(string) $sOfferId] ?? null;

                if ($arRow !== null && $arRow['in_feed']) {
                    continue;
                }

                $arSets[] = ['value_id' => (string) $sValueId, 'offer_id' => (string) $sOfferId, 'shade_code' => $sShadeCode, 'row_id' => $arRow === null ? null : (int) $arRow['id']];

                if ($arRow !== null) {
                    $arHandledRowIds[(int) $arRow['id']] = true;
                }
            }
        }

        foreach ($arRowsByValueId as $sValueId => $arRows) {
            foreach ($arRows as $sOfferId => $arRow) {
                if (array_key_exists($sOfferId, $arMembers[$sValueId] ?? []) || !$arRow['in_feed']) {
                    continue;
                }

                if ($arRow['hand_state'] === null) {
                    $arDeletes[] = ['row_id' => (int) $arRow['id']];
                    $arHandledRowIds[(int) $arRow['id']] = true;
                    continue;
                }

                $arUnsets[] = ['row_id' => (int) $arRow['id']];
            }
        }

        return new CollectionFeedPlan(
            null,
            $arDrafts,
            $arRenames,
            $arSets,
            $arUnsets,
            $arDeletes,
            $this->planShadeUpdates($arTags, $arMembershipRows, $arHandledRowIds),
            $arSkippedValues
        );
    }

    /**
     * The shade code belongs to the offer, so every membership row of an offer in the catalog follows it.
     *
     * @param array<string, mixed> $arTags
     * @param array<int, array<string, mixed>> $arMembershipRows
     * @param array<int, bool> $arSkippedRowIds Rows that a set already rewrites or a delete removes.
     *
     * @return array<int, array{row_id: int, shade_code: string|null}>
     */
    private function planShadeUpdates(array $arTags, array $arMembershipRows, array $arSkippedRowIds): array
    {
        $arUpdates = [];

        foreach ($arMembershipRows as $arRow) {
            $sOfferId = (string) $arRow['offer_id'];

            if (isset($arSkippedRowIds[(int) $arRow['id']]) || !isset($arTags['offers'][$sOfferId])) {
                continue;
            }

            $sCatalogShadeCode = $arTags['offers'][$sOfferId]['shade_code'];

            if (($arRow['shade_code'] ?? null) !== $sCatalogShadeCode) {
                $arUpdates[] = ['row_id' => (int) $arRow['id'], 'shade_code' => $sCatalogShadeCode];
            }
        }

        return $arUpdates;
    }
}
