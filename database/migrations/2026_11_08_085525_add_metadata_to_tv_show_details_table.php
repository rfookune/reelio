<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tv_show_details', function (Blueprint $table) {
            $table->date('first_air_date')->nullable();
            $table->date('last_air_date')->nullable();
            $table->string('status')->nullable();
            $table->unsignedInteger('season_count')->nullable();
            $table->unsignedInteger('episode_count')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tv_show_details', function (Blueprint $table) {
            $table->dropColumn(['first_air_date', 'last_air_date', 'status', 'season_count', 'episode_count']);
        });
    }
};
