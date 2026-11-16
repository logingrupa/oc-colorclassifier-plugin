<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * MembershipStateNormalizer - pure rules for a hand edit of one family membership.
 *
 * A row has two writers: the 1C sync owns in_feed, people own hand_state
 * ('added' or 'removed'). A hand edit equal to 1C is stored as null, the way
 * OverrideNormalizer stores an override equal to the classifier. A missing row
 * counts as in_feed false.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class MembershipStateNormalizer
{
    public const ADDED = 'added';

    public const REMOVED = 'removed';

    /**
     * @param bool $blInFeed Whether 1C tags the offer with the family.
     *
     * @return array{hand_state: string|null, delete: bool}
     */
    public function normalizeAdd(bool $blInFeed): array
    {
        return ['hand_state' => $blInFeed ? null : self::ADDED, 'delete' => false];
    }

    /**
     * @param bool $blInFeed Whether 1C tags the offer with the family.
     *
     * @return array{hand_state: string|null, delete: bool} Delete when only a hand edit held the row.
     */
    public function normalizeRemove(bool $blInFeed): array
    {
        return $blInFeed
            ? ['hand_state' => self::REMOVED, 'delete' => false]
            : ['hand_state' => null, 'delete' => true];
    }

    /**
     * @param bool $blInFeed Whether 1C tags the offer with the family.
     *
     * @return array{hand_state: string|null, delete: bool} Delete when 1C does not hold the row.
     */
    public function normalizeReset(bool $blInFeed): array
    {
        return ['hand_state' => null, 'delete' => !$blInFeed];
    }

    /**
     * @param string|null $sHandState Hand state of the row.
     * @param bool $blInFeed Whether 1C tags the offer with the family.
     *
     * @return bool True when the offer counts as a member of the family.
     */
    public function isEffective(?string $sHandState, bool $blInFeed): bool
    {
        return match ($sHandState) {
            self::ADDED => true,
            self::REMOVED => false,
            default => $blInFeed,
        };
    }
}
