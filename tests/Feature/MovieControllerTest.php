<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\RecordAnalyticsEvent;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserTitle;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('signed-in movie views record only an allowed referrer', function (mixed $referrer, string $expected) {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    fakeCatalogKnowing($movie);
    Queue::fake([RecordAnalyticsEvent::class]);
    $url = route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id, 'referrer' => $referrer]);

    $this->actingAs($user)->get($url)->assertOk();

    Queue::assertPushed(RecordAnalyticsEvent::class, fn (RecordAnalyticsEvent $job) => $job->userId === $user->id
        && $job->name === 'movie_viewed'
        && $job->properties === ['movie_id' => $movie->id, 'referrer' => $expected]);
    Queue::assertPushed(RecordAnalyticsEvent::class, 1);
})->with([
    'discover' => ['discover', 'discover'],
    'search' => ['search', 'search'],
    'watchlist' => ['watchlist', 'watchlist'],
    'watched' => ['watched', 'watched'],
    'picker' => ['picker', 'picker'],
    'direct' => ['direct', 'direct'],
    'missing' => [null, 'direct'],
    'unknown' => ['private search text', 'direct'],
    'array' => [['search'], 'direct'],
]);

test('the entry redirect preserves the referrer and records only the final page view', function () {
    $user = User::factory()->create();
    Queue::fake([RecordAnalyticsEvent::class]);
    $this->app->instance(CatalogSource::class, new FakeCatalogSource([new MovieDetails(tmdbId: 157336, title: 'Interstellar')]));

    $this->actingAs($user)->followingRedirects()->get('/movies/tmdb/157336?referrer=search')->assertOk();

    Queue::assertPushed(RecordAnalyticsEvent::class, fn (RecordAnalyticsEvent $job) => $job->name === 'movie_viewed'
        && $job->properties['referrer'] === 'search');
    Queue::assertPushed(RecordAnalyticsEvent::class, 1);
});

test('the entry redirect drops a referrer that is not a Movie referrer', function (string $referrer) {
    $this->app->instance(CatalogSource::class, new FakeCatalogSource([new MovieDetails(tmdbId: 157336, title: 'Interstellar')]));

    $response = $this->get('/movies/tmdb/157336?referrer='.urlencode($referrer))->assertStatus(301);

    expect($response->headers->get('Location'))->not->toContain('referrer=');
})->with(['private search text', 'movie', 'tv-show']);

test('stale slugs permanently redirect without recording an extra view', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['slug' => 'the-correct-title']);
    Queue::fake([RecordAnalyticsEvent::class]);

    $this->actingAs($user)->get('/movies/an-old-title-'.$movie->id.'?referrer=picker')
        ->assertStatus(301)
        ->assertRedirect(route('movies.show', ['slug' => 'the-correct-title', 'id' => $movie->id, 'referrer' => 'picker']));

    Queue::assertNothingPushed();
});

test('guest views update the last viewed time without recording analytics', function () {
    $movie = Movie::factory()->create(['last_viewed_at' => '2026-11-01 00:00:00']);
    fakeCatalogKnowing($movie);
    $this->travelTo('2026-11-05 12:00:00');
    Queue::fake([RecordAnalyticsEvent::class]);

    $this->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canonicalUrl', route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id])));

    $this->assertDatabaseHas('titles', ['id' => $movie->id, 'last_viewed_at' => '2026-11-05 12:00:00']);
    Queue::assertNothingPushed();
});

test('reloading only part of the Movie page records no new view', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['last_viewed_at' => '2026-11-01 00:00:00']);
    fakeCatalogKnowing($movie);
    $this->travelTo('2026-11-05 12:00:00');
    Queue::fake([RecordAnalyticsEvent::class]);

    $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'movies/Show',
            'X-Inertia-Partial-Data' => 'whereToWatch',
        ])
        ->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))
        ->assertOk()
        ->assertJsonPath('props.whereToWatch.country', 'US')
        ->assertJsonMissingPath('props.movie');

    $this->assertDatabaseHas('titles', ['id' => $movie->id, 'last_viewed_at' => '2026-11-01 00:00:00']);
    Queue::assertNothingPushed();
});

test('a missing local movie returns 404', function () {
    $this->get('/movies/missing-999999')->assertNotFound();
});

test('oversized local ids return 404', function () {
    $this->get('/movies/missing-999999999999999999999999999')->assertNotFound();
});

test('the movie page arrives in one response with its details, cast and credits', function () {
    $movie = Movie::factory()->create(['tmdb_id' => 157336, 'title' => 'Interstellar', 'slug' => 'interstellar', 'tagline' => 'Go further.', 'backdrop_path' => '/b.jpg', 'original_title' => 'Interstellar']);
    $cast = array_map(fn (int $i) => ['id' => $i, 'name' => "Actor {$i}", 'character' => "Role {$i}", 'profilePath' => null], range(1, 12));
    fakeCatalogKnowing(sourceDetailsFor(
        $movie,
        directors: ['Christopher Nolan'],
        writers: ['Jonathan Nolan'],
        cast: $cast,
        certification: 'PG-13',
        usReleaseDate: '2014-11-07',
        trailer: ['key' => 'LY19rHKAaAg', 'name' => 'Trailer 4', 'type' => 'Trailer'],
    ));

    $this->get(route('movies.show', ['slug' => 'interstellar', 'id' => $movie->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('movie.tagline', 'Go further.')
            ->where('movie.backdropPath', '/b.jpg')
            ->where('movie.originalTitle', 'Interstellar')
            ->where('movie.directors', ['Christopher Nolan'])
            ->where('movie.writers', ['Jonathan Nolan'])
            ->where('movie.certification', 'PG-13')
            ->where('movie.usReleaseDate', '2014-11-07')
            ->where('movie.trailer', ['key' => 'LY19rHKAaAg', 'name' => 'Trailer 4', 'type' => 'Trailer'])
            ->has('movie.cast', 12)
            ->has('movie.genres', 0)
            ->missing('deferredProps'));
});

test('the movie page shows the top-billed cast and links the full cast', function () {
    $movie = Movie::factory()->create(['title' => 'Interstellar', 'slug' => 'interstellar']);
    $cast = array_map(fn (int $i) => ['id' => $i, 'name' => "Actor {$i}", 'character' => null, 'profilePath' => null], range(1, 20));
    fakeCatalogKnowing(sourceDetailsFor($movie, cast: $cast));

    $this->get(route('movies.show', ['slug' => 'interstellar', 'id' => $movie->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('movie.cast', array_slice($cast, 0, 12))
            ->where('movie.castCount', 20)
            ->where('castUrl', route('movies.cast', ['slug' => 'interstellar', 'id' => $movie->id])));
});

test('movie details are fetched in the request when missing and then served from the cache', function () {
    $movie = Movie::factory()->create();
    $source = fakeCatalogKnowing(sourceDetailsFor($movie, directors: ['A']));
    $url = route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]);

    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('movie.directors', ['A']));
    $source->movies = [];
    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('movie.directors', ['A']));
});

test('a sparse movie still renders when the source has little', function () {
    $movie = Movie::factory()->create(['tagline' => null, 'backdrop_path' => null, 'poster_path' => null, 'original_title' => 'Autre']);
    fakeCatalogKnowing($movie);

    $this->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('movie.originalTitle', 'Autre')->where('movie.cast', [])->where('movie.certification', null)->where('movie.trailer', null));
});

test('an entry older than a day is served as stale when the source fails, and the page renders normally', function () {
    $movie = Movie::factory()->create();
    $source = fakeCatalogKnowing(sourceDetailsFor($movie, directors: ['A'], certification: 'R'));
    $url = route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]);
    $this->get($url)->assertOk();

    $this->travel(2)->days();
    $source->failing = true;

    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('movie.detailsUnavailable', false)
        ->where('movie.directors', ['A'])
        ->where('movie.certification', 'R'));
});

test('a stale entry is refreshed in the request once the source answers again', function () {
    $movie = Movie::factory()->create();
    $source = fakeCatalogKnowing(sourceDetailsFor($movie, directors: ['Old']));
    $url = route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]);
    $this->get($url)->assertOk();

    $this->travel(2)->days();
    $source->movies = [sourceDetailsFor($movie, directors: ['New'])];

    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('movie.directors', ['New']));
});

test('with a row but no cached details and the source down, stored fields render and details are flagged unavailable', function () {
    $movie = Movie::factory()->create(['title' => 'Stored Title', 'slug' => 'stored-title']);
    $this->app->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->get(route('movies.show', ['slug' => 'stored-title', 'id' => $movie->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('movie.title', 'Stored Title')
            ->where('movie.detailsUnavailable', true)
            ->where('movie.cast', [])
            ->where('movie.directors', [])
            ->where('movie.certification', null));
});

test('a 404 from the source is an answer, not an outage: the Movie is gone rather than unavailable', function () {
    $movie = Movie::factory()->create();

    $this->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))->assertGone();
});

test('the movie page carries only the signed-in viewer\'s User rating', function () {
    [$owner, $other] = User::factory()->count(2)->create();
    $movie = Movie::factory()->create();
    $unrated = Movie::factory()->create();
    fakeCatalogKnowing($movie, $unrated);
    UserTitle::query()->create(['user_id' => $owner->id, 'title_id' => $movie->id, 'rating' => 7]);
    $url = route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]);

    $this->actingAs($owner)->get($url)->assertInertia(fn (Assert $page) => $page->where('userRating', 7));
    $this->actingAs($other)->get($url)->assertInertia(fn (Assert $page) => $page->where('userRating', null));
    $this->actingAs($owner)->get(route('movies.show', ['slug' => $unrated->slug, 'id' => $unrated->id]))
        ->assertInertia(fn (Assert $page) => $page->where('userRating', null));
    auth()->logout();
    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('userRating', null));
});

test('the movie page carries the User rating of a Title that is on neither list', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    fakeCatalogKnowing($movie);
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $movie->id, 'rating' => 1]);

    $this->actingAs($user)->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))
        ->assertInertia(fn (Assert $page) => $page->where('userRating', 1)->where('userMovies.'.$movie->tmdb_id.'.watchlisted', false));
});
