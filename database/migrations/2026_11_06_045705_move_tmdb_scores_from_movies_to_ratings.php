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
        DB::statement(<<<'SQL'
            INSERT INTO ratings (source, source_id, value, vote_count, as_of)
            SELECT 'tmdb', tmdb_id::text, vote_average, vote_count, COALESCE(synced_at, updated_at, CURRENT_TIMESTAMP)
            FROM movies
            WHERE vote_average BETWEEN 0 AND 10 AND vote_count BETWEEN 1 AND 2147483647
            ON CONFLICT (source, source_id) DO NOTHING
            SQL);

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

        DB::statement(<<<'SQL'
            UPDATE movies
            SET vote_average = ratings.value, vote_count = ratings.vote_count
            FROM ratings
            WHERE ratings.source = 'tmdb' AND ratings.source_id = movies.tmdb_id::text
            SQL);
    }
};
