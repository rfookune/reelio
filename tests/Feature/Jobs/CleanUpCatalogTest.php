<?php

use App\Jobs\CleanUpCatalog;
use App\Jobs\RefreshMovie;
use App\Models\Availability;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\StreamingService;
use App\Models\UserMovie;
use App\Services\Catalog\Sources\MovieDetails;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/** A Movie last viewed the given number of days and minutes before now, with genres, Availability and ratings. */
function storedMovieViewedDaysAgo(int $days, int $minutes = 1, mixed ...$attributes): Movie
{
    $movie = Movie::factory()->create(['last_viewed_at' => now()->subDays($days)->subMinutes($minutes), ...$attributes]);
    $movie->genres()->attach(Genre::factory()->create());
    Availability::factory()->for($movie)->create();
    app(RatingStore::class)->record(RatingSourceName::Tmdb, 'movie:'.$movie->tmdb_id, 7.5, 1000);

    return $movie;
}

test('a Movie nobody holds is purged with its genre links, Availability and TMDB rating once unviewed for over 30 days', function () {
    $this->travelTo('2026-11-07 05:00:00');
    $unviewed = storedMovieViewedDaysAgo(30, imdb_id: 'tt0816692');
    $viewedRecently = storedMovieViewedDaysAgo(30, minutes: 0);
    $held = storedMovieViewedDaysAgo(100);
    UserMovie::factory()->for($held)->watched()->create();
    $removed = Movie::factory()->removed()->create(['last_viewed_at' => now()->subDays(31)]);
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0816692', 8.7, 2_400_000);
    app(RatingStore::class)->record(RatingSourceName::Tmdb, 'tv:'.$unviewed->tmdb_id, 8.0, 200);

    CleanUpCatalog::dispatch();

    expect(Movie::query()->orderBy('id')->pluck('id')->all())->toBe([$viewedRecently->id, $held->id]);
    $this->assertDatabaseMissing('genre_title', ['title_id' => $unviewed->id]);
    $this->assertDatabaseMissing('availabilities', ['title_id' => $unviewed->id]);
    $this->assertDatabaseMissing('ratings', ['source' => 'tmdb', 'source_id' => 'movie:'.$unviewed->tmdb_id]);
    $this->assertDatabaseHas('ratings', ['source' => 'imdb', 'source_id' => 'tt0816692']);
    $this->assertDatabaseHas('ratings', ['source' => 'tmdb', 'source_id' => 'movie:'.$held->tmdb_id]);
    $this->assertDatabaseHas('ratings', ['source' => 'tmdb', 'source_id' => 'tv:'.$unviewed->tmdb_id, 'value' => 8.0]);
    $this->assertModelMissing($removed);
});

test('with the purge switched off, unviewed Movies nobody holds are kept', function () {
    $this->travelTo('2026-11-07 05:00:00');
    config(['catalog.purge_unviewed.enabled' => false]);
    $unviewed = storedMovieViewedDaysAgo(100);

    CleanUpCatalog::dispatch();

    $this->assertModelExists($unviewed);
});

test('a Movie not synced for over 170 days has everything from TMDB cleared, keeps its facts, and the next refresh restores it', function () {
    $this->travelTo('2026-11-07 05:00:00');
    $stale = storedMovieViewedDaysAgo(1, synced_at: now()->subDays(170)->subMinute(), title: 'Interstellar', slug: 'interstellar', poster_path: '/p.jpg', imdb_id: 'tt0816692');
    $facts = UserMovie::factory()->for($stale)->create();
    $withinCap = storedMovieViewedDaysAgo(1, synced_at: now()->subDays(170));
    app(RatingStore::class)->record(RatingSourceName::Tmdb, 'tv:'.$stale->tmdb_id, 8.0, 200);

    CleanUpCatalog::dispatch();

    $this->assertDatabaseHas('titles', [
        'id' => $stale->id, 'tmdb_id' => $stale->tmdb_id, 'slug' => null, 'title' => null, 'overview' => null,
        'poster_path' => null, 'imdb_id' => null, 'synced_at' => null, 'removed_at' => null,
    ]);
    $this->assertDatabaseHas('movie_details', ['title_id' => $stale->id, 'release_date' => null]);
    $this->assertDatabaseMissing('genre_title', ['title_id' => $stale->id]);
    $this->assertDatabaseMissing('availabilities', ['title_id' => $stale->id]);
    $this->assertDatabaseMissing('ratings', ['source' => 'tmdb', 'source_id' => 'movie:'.$stale->tmdb_id]);
    $this->assertDatabaseHas('ratings', ['source' => 'tmdb', 'source_id' => 'tv:'.$stale->tmdb_id, 'value' => 8.0]);
    $this->assertModelExists($facts);
    $this->assertDatabaseHas('titles', ['id' => $withinCap->id, 'title' => $withinCap->title]);

    fakeCatalogKnowing(new MovieDetails(tmdbId: $stale->tmdb_id, title: 'Interstellar', posterPath: '/p.jpg', genres: [['id' => 878, 'name' => 'Science Fiction']]));
    RefreshMovie::dispatch($stale->id);

    expect($stale->refresh())->title->toBe('Interstellar')->slug->toBe('interstellar')->synced_at->toDateTimeString()->toBe('2026-11-07 05:00:00')
        ->and($stale->genres()->pluck('id')->all())->toBe([878]);
});

test('TMDB ratings of both kinds older than 170 days are deleted without Title rows, and fresh and IMDb ratings are kept', function () {
    $this->travelTo('2026-05-20 05:00:00');
    app(RatingStore::class)->record(RatingSourceName::Tmdb, 'movie:1', 7.0, 100);
    app(RatingStore::class)->record(RatingSourceName::Tmdb, 'tv:1', 8.0, 200);
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0000001', 7.0, 100);
    $this->travelTo('2026-05-21 05:00:00');
    app(RatingStore::class)->record(RatingSourceName::Tmdb, 'movie:2', 7.0, 100);
    app(RatingStore::class)->record(RatingSourceName::Tmdb, 'tv:2', 8.0, 200);
    $this->travelTo('2026-11-07 05:00:00');

    CleanUpCatalog::dispatch();

    expect(DB::table('ratings')->orderBy('source')->orderBy('source_id')->get()->map(fn (object $rating): string => "{$rating->source}:{$rating->source_id}")->all())->toBe(['imdb:tt0000001', 'tmdb:movie:2', 'tmdb:tv:2']);
});

test('with the purge switched off, the cap guard still clears stale Movies and deletes old TMDB ratings', function () {
    $this->travelTo('2026-05-01 05:00:00');
    app(RatingStore::class)->record(RatingSourceName::Tmdb, 'movie:1', 7.0, 100);
    app(RatingStore::class)->record(RatingSourceName::Tmdb, 'tv:1', 8.0, 200);
    $this->travelTo('2026-11-07 05:00:00');
    config(['catalog.purge_unviewed.enabled' => false]);
    $stale = storedMovieViewedDaysAgo(100, synced_at: now()->subDays(171));

    CleanUpCatalog::dispatch();

    $this->assertDatabaseHas('titles', ['id' => $stale->id, 'title' => null, 'synced_at' => null]);
    $this->assertDatabaseMissing('ratings', ['source' => 'tmdb', 'source_id' => 'movie:1']);
    $this->assertDatabaseMissing('ratings', ['source' => 'tmdb', 'source_id' => 'tv:1']);
});

test('Streaming services no Availability references are deleted once not fetched for an hour', function () {
    $this->travelTo('2026-11-07 05:00:00');
    $referenced = Availability::factory()->for(StreamingService::factory()->state(['fetched_at' => now()->subDays(10)]))->create()->streaming_service_id;
    StreamingService::factory()->create(['fetched_at' => now()->subHour()->subMinute()]);
    $justFetched = StreamingService::factory()->create(['fetched_at' => now()->subHour()])->id;

    CleanUpCatalog::dispatch();

    expect(StreamingService::query()->orderBy('id')->pluck('id')->all())->toBe(collect([$referenced, $justFetched])->sort()->values()->all());
});

test('a full batch hands the rest to the next run, which stops once nothing is left', function () {
    $this->travelTo('2026-11-07 05:00:00');
    config(['catalog.cleanup.batch_size' => 1]);
    Queue::fake([CleanUpCatalog::class]);
    storedMovieViewedDaysAgo(31);
    storedMovieViewedDaysAgo(31);

    app()->call([new CleanUpCatalog, 'handle']);

    expect(Movie::query()->count())->toBe(1);
    Queue::assertPushed(CleanUpCatalog::class, 1);

    Queue::fake([CleanUpCatalog::class]);
    app()->call([new CleanUpCatalog, 'handle']);

    expect(Movie::query()->count())->toBe(0);
    Queue::assertPushed(CleanUpCatalog::class, 1);

    Queue::fake([CleanUpCatalog::class]);
    app()->call([new CleanUpCatalog, 'handle']);

    Queue::assertNotPushed(CleanUpCatalog::class);
});
