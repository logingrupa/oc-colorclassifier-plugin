<?php

use October\Rain\Database\Updates\Migration;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;

/**
 * Migration to add is_hand_disabled to color_families: true while a collection
 * is switched off by hand, so the 1C sync never switches it back on. Existing
 * rows start false.
 */
class AddHandDisabledToColorFamilies extends Migration
{
    private const TABLE = 'logingrupa_colorclassifier_color_families';

    /**
     * Run the migration - add is_hand_disabled.
     *
     * @return void
     */
    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE) || Schema::hasColumn(self::TABLE, 'is_hand_disabled')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->boolean('is_hand_disabled')->default(false)->after('is_active');
        });
    }

    /**
     * Reverse the migration - drop is_hand_disabled.
     *
     * @return void
     */
    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumn(self::TABLE, 'is_hand_disabled')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn('is_hand_disabled');
        });
    }
}
