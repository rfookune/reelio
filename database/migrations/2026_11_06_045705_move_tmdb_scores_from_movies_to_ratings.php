<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drops TMDB's score from the Movie row; it lives in `ratings` now. Existing scores aren't copied over: each
     * Movie's TMDB score is recorded again the next time the Catalog fetches it.
     */
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn(['vote_average', 'vote_count']);
        });
    }

    /**
     * Puts the columns back, empty.
     */
    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->decimal('vote_average', 4, 2)->nullable();
            $table->unsignedBigInteger('vote_count')->nullable();
        });
    }
};
