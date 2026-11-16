<?php

use October\Rain\Database\Updates\Migration;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;

/**
 * Migration to create the family_memberships table: which extra colors and
 * collections an offer belongs to, written by the 1C sync (in_feed, shade_code)
 * and by people (hand_state).
 */
class CreateFamilyMembershipsTable extends Migration
{
    private const TABLE = 'logingrupa_colorclassifier_family_memberships';

    /**
     * Run the migration - create the family_memberships table.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('family_id')->index();
            $table->string('offer_id', 255)->index();
            $table->boolean('in_feed')->default(false);
            $table->string('hand_state', 8)->nullable();
            $table->string('shade_code', 64)->nullable();
            $table->timestamps();
            $table->unique(['family_id', 'offer_id'], 'cc_family_memberships_family_offer_unique');
        });
    }

    /**
     * Reverse the migration - drop the family_memberships table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
}
