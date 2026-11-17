<?php

declare(strict_types=1);

namespace Logingrupa\ColorClassifier\Classes;

/**
 * CollectionHandSwitch - what a model save writes to a collection's Active switch and hand flag.
 *
 * A form save is a hand switch only when the person moved the switch away from
 * the value the form showed. A switch left alone keeps the stored value, which
 * the 1C sync may have changed while the form was open. Outside the form a save
 * is a hand switch when it changes the stored value. A new collection starts
 * with the flag matching its Active value.
 *
 * @package Logingrupa\ColorClassifier\Classes
 */
final class CollectionHandSwitch
{
    /**
     * @param string $sKind Normalized family kind.
     * @param bool|null $blStoredActive is_active in the database, null for a new row.
     * @param bool $blSavedActive is_active the save carries.
     * @param bool|null $blRenderedActive is_active the form showed, null outside the form.
     *
     * @return array{is_active: bool, is_hand_disabled: bool|null}|null Null for a color row; a null flag keeps the stored one.
     */
    public static function resolve(string $sKind, ?bool $blStoredActive, bool $blSavedActive, ?bool $blRenderedActive): ?array
    {
        if ($sKind !== FamilyKind::COLLECTION) {
            return null;
        }

        if ($blStoredActive === null) {
            return ['is_active' => $blSavedActive, 'is_hand_disabled' => !$blSavedActive];
        }

        $blMoved = $blSavedActive !== ($blRenderedActive ?? $blStoredActive);

        if (!$blMoved) {
            return ['is_active' => $blStoredActive, 'is_hand_disabled' => null];
        }

        return ['is_active' => $blSavedActive, 'is_hand_disabled' => !$blSavedActive];
    }
}
