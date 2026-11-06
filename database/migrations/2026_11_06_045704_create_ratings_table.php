<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every score from every Rating source, keyed by the identifier that source uses for the title.
     * There's no foreign key to `movies`: a source scores titles Reelio may have no Movie for.
     */
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->string('source_id');
            $table->decimal('value', 5, 3);
            $table->unsignedInteger('vote_count');
            $table->timestamp('as_of');

            $table->unique(['source', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
