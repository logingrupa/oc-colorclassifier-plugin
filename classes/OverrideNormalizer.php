<?php

namespace Logingrupa\ColorClassifier\Classes;

/**
 * OverrideNormalizer - pure rules for storing a manual family or hex override.
 *
 * An override equal to the classifier result is stored as null, so a row only
 * counts as manual when it really differs. Saving any override locks the row.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
class OverrideNormalizer
{
    /**
     * Normalize a requested family override.
     *
     * @param string|null $sRequestedFamily Family key from the form or board.
     * @param string|null $sAutoFamily Classifier family of the row.
     * @param array<int, string> $arActiveFamilyNames Family keys an override may name.
     *
     * @return string|null Null when empty or equal to the auto family.
     *
     * @throws \InvalidArgumentException When the family is not an active family.
     */
    public function normalizeFamilyOverride(?string $sRequestedFamily, ?string $sAutoFamily, array $arActiveFamilyNames): ?string
    {
        $sFamily = trim((string) $sRequestedFamily);

        if ($sFamily === '' || $sFamily === $sAutoFamily) {
            return null;
        }

        if (!in_array($sFamily, $arActiveFamilyNames, true)) {
            throw new \InvalidArgumentException('"' . $sFamily . '" is not an active family');
        }

        return $sFamily;
    }

    /**
     * Normalize a requested hex override.
     *
     * @param string|null $sRequestedHex Hex from the form or board.
     * @param string|null $sAutoHex Classifier hex of the row.
     *
     * @return string|null Null when empty or equal to the auto hex.
     *
     * @throws \InvalidArgumentException When the hex is not six hex digits.
     */
    public function normalizeHexOverride(?string $sRequestedHex, ?string $sAutoHex): ?string
    {
        $sHex = $this->normalizeHex($sRequestedHex);

        if ($sHex === null || $sHex === strtolower(trim((string) $sAutoHex))) {
            return null;
        }

        return $sHex;
    }

    /**
     * @param string|null $sRequestedHex Six hex digits with or without "#".
     *
     * @return string|null "#" plus lowercase digits, null when empty.
     *
     * @throws \InvalidArgumentException When the value is not six hex digits.
     */
    public function normalizeHex(?string $sRequestedHex): ?string
    {
        $sHex = trim((string) $sRequestedHex);

        if ($sHex === '') {
            return null;
        }

        if (preg_match('/^#?([0-9a-fA-F]{6})$/', $sHex, $arMatches) !== 1) {
            throw new \InvalidArgumentException('"' . $sHex . '" is not a #rrggbb color');
        }

        return '#' . strtolower($arMatches[1]);
    }

    /**
     * @param bool $blLockRequested Lock switch as posted.
     * @param string|null $sFamilyOverride Normalized family override.
     * @param string|null $sHexOverride Normalized hex override.
     *
     * @return bool True when requested or when either override is set.
     */
    public function resolveLock(bool $blLockRequested, ?string $sFamilyOverride, ?string $sHexOverride): bool
    {
        return $blLockRequested || $sFamilyOverride !== null || $sHexOverride !== null;
    }
}
