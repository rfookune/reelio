<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("CREATE COLLATION IF NOT EXISTS movie_title (provider = icu, locale = 'und-u-ks-level1', deterministic = false)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP COLLATION movie_title');
    }
};
