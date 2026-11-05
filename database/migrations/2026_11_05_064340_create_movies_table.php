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
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tmdb_id')->unique();
            $table->string('slug')->nullable();
            $table->string('title')->nullable();
            $table->string('original_title')->nullable();
            $table->text('overview')->nullable();
            $table->text('tagline')->nullable();
            $table->date('release_date')->nullable();
            $table->unsignedInteger('runtime')->nullable();
            $table->string('poster_path')->nullable();
            $table->string('backdrop_path')->nullable();
            $table->string('original_language')->nullable();
            $table->decimal('vote_average', 4, 2)->nullable();
            $table->unsignedBigInteger('vote_count')->nullable();
            $table->string('imdb_id')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('last_viewed_at');
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
