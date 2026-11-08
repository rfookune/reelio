<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('title_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tmdb_id')->unique();
            $table->unsignedInteger('season_number')->nullable();
            $table->string('name')->nullable();
            $table->text('overview')->nullable();
            $table->string('poster_path')->nullable();
            $table->date('air_date')->nullable();
            $table->unsignedInteger('episode_count')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'title_id']);
        });

        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('title_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id');
            $table->foreign(['season_id', 'title_id'])->references(['id', 'title_id'])->on('seasons')->cascadeOnDelete();
            $table->unsignedBigInteger('tmdb_id')->unique();
            $table->unsignedInteger('season_number')->nullable();
            $table->unsignedInteger('episode_number')->nullable();
            $table->string('name')->nullable();
            $table->text('overview')->nullable();
            $table->date('air_date')->nullable();
            $table->unsignedInteger('runtime')->nullable();
            $table->string('episode_type')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episodes');
        Schema::dropIfExists('seasons');
    }
};
