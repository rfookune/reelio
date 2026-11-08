<?php

use App\Jobs\RecordAnalyticsEvent;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\User;
use App\Services\Catalog\AvailabilityWriter;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\MovieDetails;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('a view more than a day after the last sync refreshes the Movie, its genres, Availability and TMDB rating', function () {
    $this->travelTo('2026-11-05 12:00:00');
    $movie = Movie::factory()->create(['tmdb_id' => 157336, 'title' => 'Old Title', 'slug' => 'old-title', 'synced_at' => '2026-11-04 11:00:00']);
    $this->app->instance(CatalogSource::class, new FakeCatalogSource([new MovieDetails(
        tmdbId: 157336,
        title: 'Interstellar',
        overview: 'New overview.',
        voteAverage: 8.5,
        voteCount: 41832,
        genres: [['id' => 878, 'name' => 'Science Fiction']],
        directors: ['Christopher Nolan'],
        availability: [['serviceId' => 350, 'serviceName' => 'Apple TV', 'logoPath' => '/apple.png', 'country' => 'US', 'offerType' => 'stream', 'displayPriority' => 1]],
    )]));

    $this->get(route('movies.show', ['slug' => 'old-title', 'id' => $movie->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('movie.title', 'Interstellar')
            ->where('movie.overview', 'New overview.')
            ->where('movie.genres', ['Science Fiction'])
            ->where('movie.directors', ['Christopher Nolan'])
            ->where('movie.ratings.0.source', 'tmdb')
            ->where('movie.ratings.0.value', 8.5)
            ->where('canonicalUrl', route('movies.show', ['slug' => 'interstellar', 'id' => $movie->id]))
            ->where('whereToWatch.groups.0.services', [['id' => 350, 'name' => 'Apple TV', 'logoPath' => '/apple.png']])
            ->where('whereToWatch.updated', 'Updated today')
            ->where('whereToWatch.outOfDate', false));

    $this->assertDatabaseHas('titles', ['id' => $movie->id, 'slug' => 'interstellar', 'synced_at' => '2026-11-05 12:00:00']);
    $this->assertDatabaseHas('streaming_services', ['id' => 350, 'name' => 'Apple TV']);
    expect(DB::table('ratings')->sole())->toMatchObject(['source' => 'tmdb', 'source_id' => '157336', 'vote_count' => 41832]);
});

test('a failed refresh renders the stored Movie and flags Where to watch as possibly out of date', function () {
    $this->travelTo('2026-11-05 12:00:00');
    $movie = Movie::factory()->create(['title' => 'Stored Title', 'slug' => 'stored-title', 'synced_at' => '2026-11-03 12:00:00']);
    $this->app->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->get(route('movies.show', ['slug' => 'stored-title', 'id' => $movie->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('movie.title', 'Stored Title')
            ->where('whereToWatch.updated', 'Updated 2 days ago')
            ->where('whereToWatch.outOfDate', true));

    $this->assertDatabaseHas('titles', ['id' => $movie->id, 'synced_at' => '2026-11-03 12:00:00', 'removed_at' => null]);
});

test('a 404 on refresh makes it a Removed Movie: the row stays with everything from TMDB cleared, and the page is gone', function () {
    $this->travelTo('2026-11-05 12:00:00');
    $movie = Movie::factory()->create([
        'tmdb_id' => 157336, 'slug' => 'interstellar', 'title' => 'Interstellar', 'original_title' => 'Interstellar', 'tagline' => 'Go further.',
        'runtime' => 169, 'poster_path' => '/p.jpg', 'backdrop_path' => '/b.jpg', 'original_language' => 'en', 'imdb_id' => 'tt0816692',
        'synced_at' => '2026-11-03 12:00:00',
    ]);
    $movie->genres()->attach(Genre::factory()->create());
    app(AvailabilityWriter::class)->replace($movie, [['serviceId' => 350, 'serviceName' => 'Apple TV', 'logoPath' => null, 'country' => 'US', 'offerType' => 'stream', 'displayPriority' => 1]]);
    app(RatingStore::class)->record(RatingSourceName::Tmdb, '157336', 8.5, 41832);
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0816692', 8.7, 2_400_000);

    $this->get(route('movies.show', ['slug' => 'interstellar', 'id' => $movie->id]))->assertGone();

    $this->assertDatabaseHas('titles', [
        'id' => $movie->id, 'tmdb_id' => 157336, 'slug' => null, 'title' => null, 'original_title' => null, 'overview' => null, 'tagline' => null,
        'poster_path' => null, 'backdrop_path' => null, 'original_language' => null, 'imdb_id' => null,
        'synced_at' => null, 'removed_at' => '2026-11-05 12:00:00',
    ]);
    $this->assertDatabaseHas('movie_details', ['title_id' => $movie->id, 'release_date' => null, 'runtime' => null]);
    $this->assertDatabaseEmpty('genre_title');
    $this->assertDatabaseEmpty('availabilities');
    $this->assertDatabaseMissing('ratings', ['source' => 'tmdb']);
    $this->assertDatabaseHas('ratings', ['source' => 'imdb', 'source_id' => 'tt0816692']);
});

test('removal deletes the TMDB rating even while the TMDB source is switched off', function () {
    $this->travelTo('2026-11-05 12:00:00');
    $movie = Movie::factory()->create(['tmdb_id' => 157336, 'synced_at' => '2026-11-03 12:00:00']);
    app(RatingStore::class)->record(RatingSourceName::Tmdb, '157336', 8.5, 41832);
    config(['ratings.sources.tmdb.enabled' => false]);

    $this->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))->assertGone();

    $this->assertDatabaseEmpty('ratings');
});

test('a Removed Movie\'s page is a title-less 410 with noindex at its old slug, and the source isn\'t asked again', function () {
    $movie = Movie::factory()->removed()->create(['tmdb_id' => 157336]);
    fakeCatalogKnowing(new MovieDetails(tmdbId: 157336, title: 'Interstellar'));

    $this->get(route('movies.show', ['slug' => 'interstellar', 'id' => $movie->id]))
        ->assertGone()
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertDontSee('Interstellar')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 410)
            ->where('title', 'This movie is no longer available.')
            ->where('message', 'Our data source, TMDB, has removed this movie, so we can\'t show its details any more.'));

    $this->assertDatabaseHas('titles', ['id' => $movie->id, 'title' => null]);
});

test('a signed-in view of a Removed Movie is a 410 that records no view', function () {
    $movie = Movie::factory()->removed()->create(['last_viewed_at' => '2026-11-01 00:00:00']);
    Queue::fake([RecordAnalyticsEvent::class]);
    $this->travelTo('2026-11-05 12:00:00');

    $this->actingAs(User::factory()->create())
        ->get(route('movies.show', ['slug' => 'movie', 'id' => $movie->id]))
        ->assertGone();

    $this->assertDatabaseHas('titles', ['id' => $movie->id, 'last_viewed_at' => '2026-11-01 00:00:00']);
    Queue::assertNothingPushed();
});

test('refreshing a Removed Movie leaves it removed, even if the source has it again', function () {
    $movie = Movie::factory()->removed()->create(['tmdb_id' => 157336]);
    fakeCatalogKnowing(new MovieDetails(tmdbId: 157336, title: 'Interstellar'));

    $result = app(Catalog::class)->refresh($movie, new CatalogContext);

    expect($result->isRemoved())->toBeTrue();
    $this->assertDatabaseHas('titles', ['id' => $movie->id, 'title' => null, 'synced_at' => null]);
});

test('a refresh writes every column, even ones another writer changed after the Movie was loaded', function () {
    $movie = Movie::factory()->create(['tmdb_id' => 157336, 'title' => 'Interstellar', 'slug' => 'interstellar']);
    fakeCatalogKnowing(sourceDetailsFor($movie));
    DB::table('titles')->where('id', $movie->id)->update(['title' => null, 'slug' => null]);

    app(Catalog::class)->refresh($movie, new CatalogContext);

    $this->assertDatabaseHas('titles', ['id' => $movie->id, 'title' => 'Interstellar', 'slug' => 'interstellar']);
});
