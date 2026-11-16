<?php

use October\Rain\Database\Updates\Migration;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;

/**
 * Migration to create the color_families table: names, synonyms, hex and order
 * for every family exported to shops and edited on the Families page.
 */
class CreateColorFamiliesTable extends Migration
{
    /**
     * Run the migration - create the color_families table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('logingrupa_colorclassifier_color_families', function (Blueprint $table) {
            $table->increments('id');
            $table->string('family', 64)->unique();
            $table->string('slug', 64)->unique();
            $table->string('hex', 7);
            $table->json('names');
            $table->json('synonyms');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migration - drop the color_families table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('logingrupa_colorclassifier_color_families');
    }
}
