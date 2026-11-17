<?php

use Illuminate\Support\Facades\DB;
use October\Rain\Database\Updates\Migration;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;

/**
 * Migration to add the gradient type and angle to color_families. Existing
 * rows become linear at 90 degrees, the left to right gradient they drew before.
 */
class AddGradientTypeAndAngleToColorFamilies extends Migration
{
    private const TABLE = 'logingrupa_colorclassifier_color_families';

    /** @var int The most stops the code before this migration exports. */
    private const PREVIOUS_MAX_STOP_COUNT = 3;

    /**
     * Run the migration - add gradient_type and gradient_angle.
     *
     * @return void
     */
    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE) || Schema::hasColumn(self::TABLE, 'gradient_type')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string('gradient_type', 16)->default('linear')->after('gradient');
            $table->unsignedSmallInteger('gradient_angle')->default(90)->after('gradient_type');
        });
    }

    /**
     * Reverse the migration - clear every gradient the previous code cannot
     * export (more than 3 stops), then drop gradient_type and gradient_angle.
     *
     * @return void
     */
    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumn(self::TABLE, 'gradient_type')) {
            return;
        }

        foreach (DB::table(self::TABLE)->whereNotNull('gradient')->get(['id', 'gradient']) as $obRow) {
            if (count((array) json_decode((string) $obRow->gradient, true)) > self::PREVIOUS_MAX_STOP_COUNT) {
                DB::table(self::TABLE)->where('id', $obRow->id)->update(['gradient' => null]);
            }
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn(['gradient_type', 'gradient_angle']);
        });
    }
}
