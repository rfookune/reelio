<?php

use App\Jobs\BuildDiscover;
use App\Models\Availability;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\UserMovie;
use App\Services\Catalog\Catalog;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;

const PURGE_QUESTION = 'Clear the TMDB data Reelio stores? Users\' facts are kept. Remove the TMDB token first, or the next refreshes fetch it all again.';

test('catalog:purge clears everything from TMDB off every Movie and deletes every TMDB rating, genre, Streaming service and the Discover payload, keeping users\' facts and IMDb ratings', function () {
    fakeDiscoverSource(['trending' => [movieListings(101, 110)]]);
    BuildDiscover::dispatchSync();
    $held = Movie::factory()->create(['tmdb_id' => 1, 'imdb_id' => 'tt0000001']);
    $held->genres()->attach(Genre::factory()->create());
    Availability::factory()->for($held)->create();
    $facts = UserMovie::factory()->for($held)->create();
    $unheld = Movie::factory()->create(['tmdb_id' => 2]);
    $removed = Movie::factory()->removed()->create();
    app(RatingStore::class)->record(RatingSourceName::Tmdb, '1', 7.0, 100);
    app(RatingStore::class)->record(RatingSourceName::Tmdb, '999', 7.0, 100);
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0000001', 8.0, 100);

    $this->artisan('catalog:purge')
        ->expectsConfirmation(PURGE_QUESTION, 'yes')
        ->expectsOutputToContain('Cleared 2 Movies and deleted 12 TMDB ratings.') // Two recorded here, ten by the Discover build.
        ->assertSuccessful();

    foreach ([$held, $unheld] as $movie) {
        $this->assertDatabaseHas('movies', ['id' => $movie->id, 'title' => null, 'slug' => null, 'imdb_id' => null, 'synced_at' => null, 'removed_at' => null]);
    }
    $this->assertDatabaseHas('movies', ['id' => $removed->id, 'removed_at' => $removed->removed_at]);
    $this->assertDatabaseEmpty('genre_movie');
    $this->assertDatabaseEmpty('availabilities');
    $this->assertModelExists($facts);
    $this->assertDatabaseMissing('ratings', ['source' => 'tmdb']);
    $this->assertDatabaseHas('ratings', ['source' => 'imdb', 'source_id' => 'tt0000001']);
    $this->assertDatabaseEmpty('genres');
    $this->assertDatabaseEmpty('streaming_services');
    expect(app(Catalog::class)->storedTrending())->toBe([]);
});

test('catalog:purge changes nothing unless confirmed, and --force skips the question', function () {
    $movie = Movie::factory()->create(['tmdb_id' => 1]);
    app(RatingStore::class)->record(RatingSourceName::Tmdb, '1', 7.0, 100);

    $this->artisan('catalog:purge')->expectsConfirmation(PURGE_QUESTION, 'no')->assertFailed();

    $this->assertDatabaseHas('movies', ['id' => $movie->id, 'title' => $movie->title]);
    $this->assertDatabaseHas('ratings', ['source' => 'tmdb', 'source_id' => '1']);

    $this->artisan('catalog:purge --force')->assertSuccessful();

    $this->assertDatabaseHas('movies', ['id' => $movie->id, 'title' => null]);
    $this->assertDatabaseMissing('ratings', ['source' => 'tmdb']);
});

test('catalog:purge works through every Movie, however many batches they take', function () {
    config(['catalog.cleanup.batch_size' => 1]);
    Movie::factory()->count(3)->create();

    $this->artisan('catalog:purge --force')->expectsOutputToContain('Cleared 3 Movies')->assertSuccessful();

    expect(Movie::query()->whereNotNull('synced_at')->count())->toBe(0);
});
