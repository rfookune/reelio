<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The country a signed-in user chose for Availability; null until they choose one.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('preferred_country', 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferred_country');
        });
    }
};
