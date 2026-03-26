<?php

use October\Rain\Database\Updates\Migration;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;

/**
 * Migration to create the color_entries table for storing
 * extracted and classified color data per product offer.
 */
class CreateColorEntriesTable extends Migration
{
    /**
     * Run the migration — create logingrupa_colorclassifier_color_entries table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('logingrupa_colorclassifier_color_entries', function (Blueprint $table) {
            $table->increments('id');
            $table->string('offer_id')->index();
            $table->string('product_name');
            $table->string('variation_name');
            $table->text('image_url');
            $table->string('hex_color', 7);
            $table->json('oklch_values')->nullable();
            $table->json('palette_colors')->nullable();
            $table->string('color_name')->nullable();
            $table->json('taxonomy')->nullable();
            $table->decimal('confidence_score', 3, 2)->default(0.00);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migration — drop the color_entries table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('logingrupa_colorclassifier_color_entries');
    }
}
