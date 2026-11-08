<?php

namespace Database\Seeders;

use App\Models\UserEpisode;
use Illuminate\Database\Seeder;

class UserEpisodeSeeder extends Seeder
{
    public function run(): void
    {
        UserEpisode::factory()->create();
    }
}
