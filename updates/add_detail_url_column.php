<?php

use October\Rain\Database\Updates\Migration;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;

/**
 * Migration to add detail_url column for frontend product deep links.
 */
class AddDetailUrlColumn extends Migration
{
    /**
     * Run the migration — add detail_url column after image_url.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('logingrupa_colorclassifier_color_entries', function (Blueprint $table) {
            $table->string('detail_url')->nullable()->after('image_url');
        });
    }

    /**
     * Reverse the migration — drop detail_url column.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('logingrupa_colorclassifier_color_entries', function (Blueprint $table) {
            $table->dropColumn('detail_url');
        });
    }
}
