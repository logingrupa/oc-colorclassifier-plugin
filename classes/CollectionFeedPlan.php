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
     * @param array<int, array<string, mixed>> $arFamilyDrafts Collection rows to create, inactive.
     * @param array<int, array{value_id: string, feed_value_name: string}> $arFamilyRenames
     * @param array<int, array{value_id: string, offer_id: string, shade_code: string|null, row_id: int|null}> $arMembershipSets Rows that turn in_feed on.
     * @param array<int, array{row_id: int}> $arMembershipUnsets Rows with a hand state that turn in_feed off.
     * @param array<int, array{row_id: int}> $arMembershipDeletes Rows no 1C tag and no hand state hold.
     * @param array<int, array{row_id: int, shade_code: string|null}> $arShadeUpdates
     */
    public function __construct(
        public readonly ?string $sAbortReason = null,
        public readonly array $arFamilyDrafts = [],
        public readonly array $arFamilyRenames = [],
        public readonly array $arMembershipSets = [],
        public readonly array $arMembershipUnsets = [],
        public readonly array $arMembershipDeletes = [],
        public readonly array $arShadeUpdates = [],
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
     * @return array{create: int, rename: int, set: int, unset: int, delete: int, shade: int}
     */
    public function countSummary(): array
    {
        return [
            'create' => count($this->arFamilyDrafts),
            'rename' => count($this->arFamilyRenames),
            'set'    => count($this->arMembershipSets),
            'unset'  => count($this->arMembershipUnsets),
            'delete' => count($this->arMembershipDeletes),
            'shade'  => count($this->arShadeUpdates),
        ];
    }

    public function countWrites(): int
    {
        return array_sum($this->countSummary());
    }
}
