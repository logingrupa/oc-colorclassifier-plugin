<?php

use October\Rain\Database\Updates\Migration;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;

/**
 * Migration to add the family kind, the 1C feed link and the optional gradient
 * to color_families. Existing rows become colors with no feed link.
 */
class AddKindAndFeedLinkToColorFamilies extends Migration
{
    private const TABLE = 'logingrupa_colorclassifier_color_families';

    /**
     * Run the migration - add kind, feed_characteristic_id, feed_value_id, feed_value_name and gradient.
     *
     * @return void
     */
    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE) || Schema::hasColumn(self::TABLE, 'kind')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string('kind', 16)->default('color')->index()->after('slug');
            $table->string('feed_characteristic_id', 36)->nullable()->after('kind');
            $table->string('feed_value_id', 36)->nullable()->unique()->after('feed_characteristic_id');
            $table->string('feed_value_name', 255)->nullable()->after('feed_value_id');
            $table->text('gradient')->nullable()->after('hex');
        });
    }

    /**
     * Reverse the migration - drop the columns and their indexes.
     *
     * @return void
     */
    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumn(self::TABLE, 'kind')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropUnique(['feed_value_id']);
            $table->dropIndex(['kind']);
        });

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn(['kind', 'feed_characteristic_id', 'feed_value_id', 'feed_value_name', 'gradient']);
        });
    }
}
