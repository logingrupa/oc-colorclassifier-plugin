<?php

use October\Rain\Database\Updates\Migration;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;

/**
 * Migration to add the manual override and lock columns to color entries.
 */
class AddOverrideColumnsToColorEntries extends Migration
{
    /**
     * Run the migration - add family_override, hex_override and is_locked.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('logingrupa_colorclassifier_color_entries', function (Blueprint $table) {
            $table->string('family_override', 64)->nullable()->after('confidence_score');
            $table->string('hex_override', 7)->nullable()->after('family_override');
            $table->boolean('is_locked')->default(false)->index()->after('hex_override');
        });
    }

    /**
     * Reverse the migration - drop the override and lock columns.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('logingrupa_colorclassifier_color_entries', function (Blueprint $table) {
            $table->dropColumn(['family_override', 'hex_override', 'is_locked']);
        });
    }
}
