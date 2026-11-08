<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A user's two independent facts about a Movie: the Date added to their Watchlist and the Watched date. A row
     * with neither is deleted, and a Movie can't be deleted while anyone holds a fact about it.
     */
    public function up(): void
    {
        Schema::create('user_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('title_id')->index()->constrained()->restrictOnDelete();
            $table->timestamp('watchlisted_at')->nullable();
            $table->timestamp('watched_at')->nullable();

            $table->unique(['user_id', 'title_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_titles');
    }
};
