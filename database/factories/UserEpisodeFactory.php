<?php

namespace Database\Factories;

use App\Models\Episode;
use App\Models\User;
use App\Models\UserEpisode;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserEpisode> */
class UserEpisodeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'episode_id' => Episode::factory(), 'watched_at' => now()];
    }
}
