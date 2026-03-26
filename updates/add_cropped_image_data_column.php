<?php

use October\Rain\Database\Updates\Migration;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;

/**
 * Add cropped_image_data column to store the 50x50 center-cropped image as base64.
 */
class AddCroppedImageDataColumn extends Migration
{
    public function up(): void
    {
        Schema::table('logingrupa_colorclassifier_color_entries', function (Blueprint $table) {
            $table->text('cropped_image_data')->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('logingrupa_colorclassifier_color_entries', function (Blueprint $table) {
            $table->dropColumn('cropped_image_data');
        });
    }
}
