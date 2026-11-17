<?php

use Logingrupa\ColorClassifier\Classes\CollectionHandSwitch;
use Logingrupa\ColorClassifier\Classes\FamilyKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * HAND-01..05: a model save records a hand switch only when the person moved
 * the Active switch, a stale Families form keeps what the 1C sync wrote, a
 * new collection starts with the flag matching Active and a color is untouched.
 */
class CollectionHandSwitchTest extends TestCase
{
    /**
     * @return array<string, array{0: bool|null, 1: bool, 2: bool|null, 3: array{is_active: bool, is_hand_disabled: bool|null}}>
     */
    public static function saveProvider(): array
    {
        return [
            'new collection active'                       => [null, true, null, ['is_active' => true, 'is_hand_disabled' => false]],
            'new collection inactive'                     => [null, false, null, ['is_active' => false, 'is_hand_disabled' => true]],
            'form switch-off'                             => [true, false, true, ['is_active' => false, 'is_hand_disabled' => true]],
            'form switch-on clears the flag'              => [false, true, false, ['is_active' => true, 'is_hand_disabled' => false]],
            'form save with the switch left off'          => [false, false, false, ['is_active' => false, 'is_hand_disabled' => null]],
            'form save with the switch left on'           => [true, true, true, ['is_active' => true, 'is_hand_disabled' => null]],
            'stale form after the sync switched it on'    => [true, false, false, ['is_active' => true, 'is_hand_disabled' => null]],
            'stale form after the sync switched it off'   => [false, true, true, ['is_active' => false, 'is_hand_disabled' => null]],
            'form switch-off after the sync switched off' => [false, false, true, ['is_active' => false, 'is_hand_disabled' => true]],
            'save outside the form switches off'          => [true, false, null, ['is_active' => false, 'is_hand_disabled' => true]],
            'save outside the form switches on'           => [false, true, null, ['is_active' => true, 'is_hand_disabled' => false]],
            'save outside the form leaves Active alone'   => [false, false, null, ['is_active' => false, 'is_hand_disabled' => null]],
        ];
    }

    /**
     * @param array{is_active: bool, is_hand_disabled: bool|null} $arExpected
     */
    #[DataProvider('saveProvider')]
    public function test_collection_save_resolves_active_and_the_hand_flag(?bool $blStored, bool $blSaved, ?bool $blRendered, array $arExpected): void
    {
        $this->assertSame($arExpected, CollectionHandSwitch::resolve(FamilyKind::COLLECTION, $blStored, $blSaved, $blRendered));
    }

    public function test_color_row_is_left_alone(): void
    {
        $this->assertNull(CollectionHandSwitch::resolve(FamilyKind::COLOR, true, false, true));
        $this->assertNull(CollectionHandSwitch::resolve(FamilyKind::COLOR, null, false, null));
    }
}
