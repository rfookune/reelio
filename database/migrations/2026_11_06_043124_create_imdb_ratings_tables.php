<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The live IMDb ratings table and the staging table each import loads before the two are swapped.
     */
    public function up(): void
    {
        foreach (['imdb_ratings', 'imdb_ratings_staging'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->string('imdb_id')->primary();
                $table->decimal('rating', 3, 1);
                $table->unsignedInteger('vote_count');
                $table->timestamp('imported_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imdb_ratings_staging');
        Schema::dropIfExists('imdb_ratings');
    }
};
