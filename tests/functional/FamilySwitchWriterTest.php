<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Logingrupa\ColorClassifier\Classes\FamilySwitchWriter;
use PHPUnit\Framework\TestCase;

/**
 * SWITCH-01..04: the sync's switch writes against a real (SQLite) table. A
 * switch lands on the row the plan read, and a hand switch made after the
 * read wins over it.
 */
class FamilySwitchWriterTest extends TestCase
{
    private const FAMILY_TABLE = 'logingrupa_colorclassifier_color_families';

    private const NOW = '2026-11-17 11:20:00';

    private Connection $obConnection;

    protected function setUp(): void
    {
        $obCapsule = new Capsule();
        $obCapsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->obConnection = $obCapsule->getConnection();
        $this->obConnection->getSchemaBuilder()->create(self::FAMILY_TABLE, static function (Blueprint $obTable): void {
            $obTable->increments('id');
            $obTable->boolean('is_active');
            $obTable->boolean('is_hand_disabled');
            $obTable->timestamp('updated_at')->nullable();
        });
    }

    private function insertRow(int $iRowId, bool $blActive, bool $blHandDisabled): void
    {
        $this->obConnection->table(self::FAMILY_TABLE)->insert(['id' => $iRowId, 'is_active' => $blActive, 'is_hand_disabled' => $blHandDisabled, 'updated_at' => null]);
    }

    /**
     * @return array{is_active: bool, is_hand_disabled: bool, updated_at: string|null}
     */
    private function readRow(int $iRowId): array
    {
        $obRow = $this->obConnection->table(self::FAMILY_TABLE)->where('id', $iRowId)->first();

        return ['is_active' => (bool) $obRow->is_active, 'is_hand_disabled' => (bool) $obRow->is_hand_disabled, 'updated_at' => $obRow->updated_at];
    }

    /**
     * @param array<int, array{row_id: int, is_active: bool}> $arSwitches
     */
    private function applySwitches(array $arSwitches): void
    {
        (new FamilySwitchWriter($this->obConnection))->apply($arSwitches, new DateTimeImmutable(self::NOW));
    }

    public function test_switch_off_and_switch_on_land_on_their_rows(): void
    {
        $this->insertRow(1, true, false);
        $this->insertRow(2, false, false);
        $this->insertRow(3, true, false);

        $this->applySwitches([['row_id' => 1, 'is_active' => false], ['row_id' => 2, 'is_active' => true]]);

        $this->assertSame(['is_active' => false, 'is_hand_disabled' => false, 'updated_at' => self::NOW], $this->readRow(1));
        $this->assertSame(['is_active' => true, 'is_hand_disabled' => false, 'updated_at' => self::NOW], $this->readRow(2));
        $this->assertSame(['is_active' => true, 'is_hand_disabled' => false, 'updated_at' => null], $this->readRow(3));
    }

    public function test_switch_on_leaves_a_row_switched_off_by_hand_after_the_read(): void
    {
        $this->insertRow(1, false, true);

        $this->applySwitches([['row_id' => 1, 'is_active' => true]]);

        $this->assertSame(['is_active' => false, 'is_hand_disabled' => true, 'updated_at' => null], $this->readRow(1));
    }

    public function test_switch_off_leaves_a_row_already_off(): void
    {
        $this->insertRow(1, false, true);

        $this->applySwitches([['row_id' => 1, 'is_active' => false]]);

        $this->assertSame(['is_active' => false, 'is_hand_disabled' => true, 'updated_at' => null], $this->readRow(1));
    }

    public function test_switch_on_leaves_a_row_already_on(): void
    {
        $this->insertRow(1, true, false);

        $this->applySwitches([['row_id' => 1, 'is_active' => true]]);

        $this->assertSame(['is_active' => true, 'is_hand_disabled' => false, 'updated_at' => null], $this->readRow(1));
    }
}
