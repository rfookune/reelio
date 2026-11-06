<?php

namespace Database\Factories;

use App\Catalog\OfferType;
use App\Models\Availability;
use App\Models\Movie;
use App\Models\StreamingService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Availability>
 */
class AvailabilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'movie_id' => Movie::factory(),
            'streaming_service_id' => StreamingService::factory(),
            'country' => 'US',
            'offer_type' => OfferType::Stream,
            'display_priority' => fake()->numberBetween(1, 100),
        ];
    }
}
