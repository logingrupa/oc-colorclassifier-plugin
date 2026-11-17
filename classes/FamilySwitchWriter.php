<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;

/**
 * FamilySwitchWriter - writes the 1C sync's Active switches of collection rows.
 *
 * Writes go past the model so they never count as a hand switch. The where
 * clause lets a hand switch made after the sync read the rows win.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
final class FamilySwitchWriter
{
    private const FAMILY_TABLE = 'logingrupa_colorclassifier_color_families';

    public function __construct(private readonly ConnectionInterface $obConnection)
    {
    }

    /**
     * @param array<int, array{row_id: int, is_active: bool}> $arSwitches
     */
    public function apply(array $arSwitches, DateTimeInterface $obNow): void
    {
        foreach ($arSwitches as $arSwitch) {
            $obQuery = $this->obConnection->table(self::FAMILY_TABLE)->where('id', $arSwitch['row_id']);

            if ($arSwitch['is_active']) {
                $obQuery->where('is_active', false)->where('is_hand_disabled', false);
            } else {
                $obQuery->where('is_active', true);
            }

            $obQuery->update(['is_active' => $arSwitch['is_active'], 'updated_at' => $obNow]);
        }
    }
}
