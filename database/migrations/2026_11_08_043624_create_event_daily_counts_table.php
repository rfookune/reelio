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
        Schema::create('event_daily_counts', function (Blueprint $table) {
            $table->date('day');
            $table->string('name');
            $table->unsignedBigInteger('count');
            $table->unsignedBigInteger('pool_size_total')->nullable();
            $table->primary(['day', 'name']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->index('occurred_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['occurred_at']);
        });

        Schema::dropIfExists('event_daily_counts');
    }
};
