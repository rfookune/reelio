<?php

namespace App\Services\Catalog;

use App\Services\Catalog\Enums\OfferType;
use App\Models\Movie;
use Illuminate\Support\Facades\DB;

/**
 * Stores a Movie's Availability from a Catalog source sync: the Streaming services it references are upserted
 * and the Movie's rows are replaced wholesale, in one transaction. Services are upserted in id order so that
 * concurrent syncs touching the same services lock them in the same order.
 */
class AvailabilityWriter
{
    /**
     * @param  list<array{serviceId: int, serviceName: string, logoPath: ?string, country: string, offerType: string, displayPriority: int}>  $offers
     */
    public function replace(Movie $movie, array $offers): void
    {
        DB::transaction(function () use ($movie, $offers): void {
            $services = [];

            foreach ($offers as $offer) {
                $services[$offer['serviceId']] = ['id' => $offer['serviceId'], 'name' => $offer['serviceName'], 'logo_path' => $offer['logoPath'], 'fetched_at' => now()];
            }

            ksort($services);

            if ($services !== []) {
                DB::table('streaming_services')->upsert(array_values($services), ['id'], ['name', 'logo_path', 'fetched_at']);
            }

            $movie->availabilities()->delete();

            if ($offers !== []) {
                DB::table('availabilities')->insert(array_map(fn (array $offer): array => [
                    'movie_id' => $movie->id,
                    'streaming_service_id' => $offer['serviceId'],
                    'country' => $offer['country'],
                    'offer_type' => OfferType::from($offer['offerType'])->value,
                    'display_priority' => $offer['displayPriority'],
                ], $offers));
            }
        });
    }
}
