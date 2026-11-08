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
        Schema::create('titles', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 8);
            $table->unsignedBigInteger('tmdb_id');
            $table->unique(['kind', 'tmdb_id']);
            $table->string('slug')->nullable();
            $table->string('title')->nullable();
            $table->string('original_title')->nullable();
            $table->text('overview')->nullable();
            $table->text('tagline')->nullable();
            $table->string('poster_path')->nullable();
            $table->string('backdrop_path')->nullable();
            $table->string('original_language')->nullable();
            $table->string('imdb_id')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('last_viewed_at');
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('movie_details', function (Blueprint $table) {
            $table->foreignId('title_id')->primary()->constrained()->cascadeOnDelete();
            $table->date('release_date')->nullable();
            $table->unsignedInteger('runtime')->nullable();
        });

        Schema::create('tv_show_details', function (Blueprint $table) {
            $table->foreignId('title_id')->primary()->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tv_show_details');
        Schema::dropIfExists('movie_details');
        Schema::dropIfExists('titles');
    }
};
