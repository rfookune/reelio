<?php

namespace App\Services\Catalog;

use App\Models\Movie;
use App\Services\Catalog\Enums\OfferType;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
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
                    'title_id' => $movie->id,
                    'streaming_service_id' => $offer['serviceId'],
                    'country' => $offer['country'],
                    'offer_type' => OfferType::from($offer['offerType'])->value,
                    'display_priority' => $offer['displayPriority'],
                ], $offers));
            }
        });
    }

    /**
     * Deletes up to `$limit` Streaming services no Availability references, and returns how many. A sync upserts its
     * services before it inserts their Availability, so a `$fetchedBefore` a little in the past, checked again on the
     * locked row, keeps the services a sync has just stored; with none, every unreferenced service goes.
     */
    public function deleteUnreferencedServices(int $limit, ?CarbonInterface $fetchedBefore = null): int
    {
        $fetchedInTime = fn (Builder $query): Builder => $query->when($fetchedBefore !== null, fn (Builder $query): Builder => $query->where('fetched_at', '<', $fetchedBefore));
        $unreferenced = $fetchedInTime(DB::table('streaming_services')->select('id'))
            ->whereNotExists(fn (Builder $availability): Builder => $availability->select(DB::raw(1))->from('availabilities')->whereColumn('availabilities.streaming_service_id', 'streaming_services.id'))
            ->orderBy('id')->limit($limit);

        return $fetchedInTime(DB::table('streaming_services'))->whereIn('id', $unreferenced)->delete();
    }
}
