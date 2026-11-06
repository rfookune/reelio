<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Moves TMDB's score off the Movie row into `ratings`, keeping every stored score that is showable: a value
     * from 0 to 10 and a vote count that fits `ratings.vote_count`.
     */
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn(['vote_average', 'vote_count']);
        });
    }

    /**
     * Puts the columns back and copies TMDB's scores onto the Movie rows.
     */
    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->decimal('vote_average', 4, 2)->nullable();
            $table->unsignedBigInteger('vote_count')->nullable();
        });
    }
};
