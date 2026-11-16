<?php

use Illuminate\Support\Facades\DB;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

/**
 * Migration to seed the color_families table with the 23 classifier families.
 */
class SeedColorFamiliesTable extends Migration
{
    private const TABLE = 'logingrupa_colorclassifier_color_families';

    /**
     * Run the migration - insert the seed families unless the table already has rows.
     *
     * @return void
     */
    public function up(): void
    {
        if (DB::table(self::TABLE)->exists()) {
            return;
        }

        $obNow = now();
        $arRows = [];

        foreach ($this->loadSeed() as $iIndex => $arFamily) {
            $arRows[] = [
                'family'     => $arFamily['family'],
                'slug'       => $arFamily['slug'],
                'hex'        => $arFamily['hex'],
                'names'      => json_encode($arFamily['names'], JSON_UNESCAPED_UNICODE),
                'synonyms'   => json_encode($arFamily['synonyms'], JSON_UNESCAPED_UNICODE),
                'sort_order' => $iIndex + 1,
                'is_active'  => true,
                'created_at' => $obNow,
                'updated_at' => $obNow,
            ];
        }

        DB::table(self::TABLE)->insert($arRows);
    }

    /**
     * Reverse the migration - delete the seeded families.
     *
     * @return void
     */
    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        DB::table(self::TABLE)->whereIn('family', array_column($this->loadSeed(), 'family'))->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadSeed(): array
    {
        return require __DIR__ . '/data/color_families_seed.php';
    }
}
