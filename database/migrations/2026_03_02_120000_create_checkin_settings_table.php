<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('checkin_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('minimum_checkout_hours')->default(4);
            $table->boolean('require_location')->default(false);
            $table->boolean('allow_leader_bulk_actions')->default(true);
            $table->boolean('allow_leader_include_self')->default(true);
            $table->unsignedSmallInteger('max_targets_per_action')->default(25);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkin_settings');
    }
};
