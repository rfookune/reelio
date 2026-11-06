<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per Movie, Streaming service, supported country and offer type.
     */
    public function up(): void
    {
        Schema::create('availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->foreignId('streaming_service_id')->constrained()->restrictOnDelete();
            $table->char('country', 2);
            $table->string('offer_type', 8);
            $table->unsignedInteger('display_priority');

            $table->unique(['movie_id', 'streaming_service_id', 'country', 'offer_type']);
            $table->index(['movie_id', 'country']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availabilities');
    }
};
