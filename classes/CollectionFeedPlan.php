<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * CollectionFeedPlan - the writes one 1C collection sync would make, or why it aborted.
 *
 * Existing rows are addressed by row_id, rows to create by their feed value id,
 * because a draft family has no id before the plan is applied.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
final class CollectionFeedPlan
{
    /**
     * @param string|null $sAbortReason Set when the sync must write nothing.
     * @param array<int, array<string, mixed>> $arFamilyDrafts Collection rows to create, active.
     * @param array<int, array{value_id: string, feed_value_name: string}> $arFamilyRenames
     * @param array<int, array{value_id: string, offer_id: string, shade_code: string|null, row_id: int|null}> $arMembershipSets Rows that turn in_feed on.
     * @param array<int, array{row_id: int}> $arMembershipUnsets Rows with a hand state that turn in_feed off.
     * @param array<int, array{row_id: int}> $arMembershipDeletes Rows no 1C tag and no hand state hold.
     * @param array<int, array{row_id: int, shade_code: string|null}> $arShadeUpdates
     * @param array<int, array{value_id: string, name: string, reason: string}> $arSkippedValues 1C values that got no family and no membership.
     * @param array<int, array{row_id: int, is_active: bool}> $arFamilySwitches Collection rows whose is_active follows 1C.
     */
    public function __construct(
        public readonly ?string $sAbortReason = null,
        public readonly array $arFamilyDrafts = [],
        public readonly array $arFamilyRenames = [],
        public readonly array $arMembershipSets = [],
        public readonly array $arMembershipUnsets = [],
        public readonly array $arMembershipDeletes = [],
        public readonly array $arShadeUpdates = [],
        public readonly array $arSkippedValues = [],
        public readonly array $arFamilySwitches = [],
    ) {
    }

    /**
     * @param string $sReason Why nothing may be written.
     */
    public static function abort(string $sReason): self
    {
        return new self($sReason);
    }

    public function isAborted(): bool
    {
        return $this->sAbortReason !== null;
    }

    /**
     * @return array{create: int, rename: int, set: int, unset: int, delete: int, shade: int, on: int, off: int}
     */
    public function countSummary(): array
    {
        $iSwitchOnCount = count(array_filter($this->arFamilySwitches, static fn (array $arSwitch): bool => $arSwitch['is_active']));

        return [
            'create' => count($this->arFamilyDrafts),
            'rename' => count($this->arFamilyRenames),
            'set'    => count($this->arMembershipSets),
            'unset'  => count($this->arMembershipUnsets),
            'delete' => count($this->arMembershipDeletes),
            'shade'  => count($this->arShadeUpdates),
            'on'     => $iSwitchOnCount,
            'off'    => count($this->arFamilySwitches) - $iSwitchOnCount,
        ];
    }

    public function countWrites(): int
    {
        return array_sum($this->countSummary());
    }

    /**
     * @param string $sVerb "Planned" for a dry run, "Applied" for a real run.
     *
     * @return string The summary line the command and the Families page show, plus a second "Skipped" line when 1C values were left out.
     */
    public function describe(string $sVerb): string
    {
        $arCounts = $this->countSummary();
        $sSummary = sprintf(
            '%s: create %d, rename %d, set %d, unset %d, delete %d, shade %d, on %d, off %d, writes %d',
            $sVerb,
            $arCounts['create'],
            $arCounts['rename'],
            $arCounts['set'],
            $arCounts['unset'],
            $arCounts['delete'],
            $arCounts['shade'],
            $arCounts['on'],
            $arCounts['off'],
            $this->countWrites()
        );

        return $this->arSkippedValues === [] ? $sSummary : $sSummary . "\n" . $this->describeSkipped();
    }

    private function describeSkipped(): string
    {
        $arEntries = array_map(
            static fn (array $arSkipped): string => $arSkipped['name'] . ' (' . $arSkipped['reason'] . ')',
            $this->arSkippedValues
        );

        return 'Skipped ' . count($arEntries) . ': ' . implode(', ', $arEntries);
    }
}
