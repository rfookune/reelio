<?php

use App\Jobs\RecordAnalyticsEvent;
use App\Models\Availability;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\StreamingService;
use App\Models\User;
use App\Models\UserMovie;
use App\Services\Catalog\Enums\OfferType;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use Illuminate\Support\Facades\Queue;

test('guests get 401 from the picker', function () {
    $this->getJson(route('watchlist.pick'))->assertUnauthorized();
});

test('the pick comes from the user\'s own Watchlist, rewatches included, never Watched-only or unavailable Movies', function () {
    $user = User::factory()->create();
    $rewatch = Movie::factory()->create(['tmdb_id' => 2]);
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 1]))->create();
    UserMovie::factory()->for($user)->for($rewatch)->create(['watched_at' => '2026-01-01']);
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 3]))->watched()->create();
    UserMovie::factory()->for($user)->for(Movie::factory()->removed()->state(['tmdb_id' => 4]))->create();
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 5, 'title' => null, 'slug' => null]))->create();
    UserMovie::factory()->for(Movie::factory()->state(['tmdb_id' => 6]))->create();
    $this->actingAs($user);

    $picked = collect(range(1, 20))->map(fn () => $this->getJson(route('watchlist.pick'))->assertOk()->assertJsonPath('poolSize', 2)->json('movie.tmdbId'));

    expect($picked->unique()->sort()->values()->all())->toBe([1, 2]);
});

test('genres narrow the pool by any match and the filter offers only genres the candidates have', function () {
    $user = User::factory()->create();
    [$action, $comedy, $drama, $horror] = collect([[28, 'Action'], [35, 'Comedy'], [18, 'Drama'], [27, 'Horror']])
        ->map(fn (array $genre) => Genre::factory()->create(['id' => $genre[0], 'name' => $genre[1]]));
    $both = Movie::factory()->create(['tmdb_id' => 1]);
    $both->genres()->attach([$action->id, $comedy->id]);
    $dramaOnly = Movie::factory()->create(['tmdb_id' => 2]);
    $dramaOnly->genres()->attach($drama);
    $watchedHorror = Movie::factory()->create(['tmdb_id' => 3]);
    $watchedHorror->genres()->attach($horror);
    UserMovie::factory()->for($user)->for($both)->create();
    UserMovie::factory()->for($user)->for($dramaOnly)->create();
    UserMovie::factory()->for($user)->for($watchedHorror)->watched()->create();
    $this->actingAs($user);

    $this->getJson(route('watchlist.pick', ['genres' => [28, 35]]))->assertOk()
        ->assertJsonPath('poolSize', 1)
        ->assertJsonPath('movie.tmdbId', 1)
        ->assertJsonPath('genres', [['id' => 28, 'name' => 'Action'], ['id' => 35, 'name' => 'Comedy'], ['id' => 18, 'name' => 'Drama']]);
    $this->getJson(route('watchlist.pick', ['genres' => [18, 35]]))->assertJsonPath('poolSize', 2);
});

test('Pick again leaves the previous pick out only when another Movie qualifies', function () {
    $user = User::factory()->create();
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 1]))->create();
    $this->actingAs($user);

    $this->getJson(route('watchlist.pick', ['previous' => 1]))->assertJsonPath('poolSize', 1)->assertJsonPath('movie.tmdbId', 1);

    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 2]))->create();
    foreach (range(1, 10) as $attempt) {
        $this->getJson(route('watchlist.pick', ['previous' => 1]))->assertJsonPath('poolSize', 2)->assertJsonPath('movie.tmdbId', 2);
    }
});

test('no candidates and genres matching nothing return no Movie', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->getJson(route('watchlist.pick'))->assertOk()->assertExactJson(['poolSize' => 0, 'genres' => [], 'movie' => null]);

    $movie = Movie::factory()->create();
    $movie->genres()->attach(Genre::factory()->create(['id' => 18, 'name' => 'Drama']));
    UserMovie::factory()->for($user)->for($movie)->create();

    $this->getJson(route('watchlist.pick', ['genres' => [99]]))->assertOk()
        ->assertExactJson(['poolSize' => 0, 'genres' => [['id' => 18, 'name' => 'Drama']], 'movie' => null]);
});

test('invalid criteria are rejected with 422', function (array $query) {
    $this->actingAs(User::factory()->create())->getJson(route('watchlist.pick', $query))->assertUnprocessable();
})->with([
    'genres not a list' => [['genres' => 'drama']],
    'non-numeric genre' => [['genres' => ['drama']]],
    'repeated genre' => [['genres' => [18, 18]]],
    'non-numeric previous' => [['previous' => 'movie']],
    'zero previous' => [['previous' => 0]],
]);

test('the pick has what\'s needed to decide, read from storage alone, with only Stream and Free for the user\'s country', function () {
    $this->travelTo('2026-11-05 12:00:00');
    config(['ratings.sources.imdb.enabled' => true]);
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));
    $user = User::factory()->create(['preferred_country' => 'GB']);
    $movie = Movie::factory()->create([
        'tmdb_id' => 157336, 'slug' => 'interstellar', 'title' => 'Interstellar', 'release_date' => '2014-11-05', 'runtime' => 169,
        'overview' => 'A team travels through a wormhole.', 'poster_path' => '/poster.jpg', 'imdb_id' => 'tt0816692',
        'synced_at' => '2026-10-20 08:00:00', 'last_viewed_at' => '2026-10-20 08:00:00',
    ]);
    $movie->genres()->attach([Genre::factory()->create(['id' => 878, 'name' => 'Science Fiction'])->id, Genre::factory()->create(['id' => 12, 'name' => 'Adventure'])->id]);
    UserMovie::factory()->for($user)->for($movie)->create();
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0816692', 8.7, 2000000);
    app(RatingStore::class)->record(RatingSourceName::Tmdb, '157336', 8.4, 30000);
    $netflix = StreamingService::factory()->create(['id' => 8, 'name' => 'Netflix', 'logo_path' => '/netflix.png']);
    $pluto = StreamingService::factory()->create(['id' => 300, 'name' => 'Pluto TV', 'logo_path' => '/pluto.png']);
    $apple = StreamingService::factory()->create(['id' => 2, 'name' => 'Apple TV', 'logo_path' => '/apple.png']);
    Availability::factory()->for($movie)->for($pluto)->create(['country' => 'GB', 'offer_type' => OfferType::Free, 'display_priority' => 3]);
    Availability::factory()->for($movie)->for($netflix)->create(['country' => 'GB', 'offer_type' => OfferType::Stream, 'display_priority' => 1]);
    Availability::factory()->for($movie)->for($apple)->create(['country' => 'GB', 'offer_type' => OfferType::Rent, 'display_priority' => 2]);
    Availability::factory()->for($movie)->for($apple)->create(['country' => 'US', 'offer_type' => OfferType::Stream, 'display_priority' => 2]);

    $this->actingAs($user)->getJson(route('watchlist.pick'))->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertExactJson([
            'poolSize' => 1,
            'genres' => [['id' => 12, 'name' => 'Adventure'], ['id' => 878, 'name' => 'Science Fiction']],
            'movie' => [
                'tmdbId' => 157336,
                'title' => 'Interstellar',
                'year' => 2014,
                'runtime' => 169,
                'genres' => ['Adventure', 'Science Fiction'],
                'overview' => 'A team travels through a wormhole.',
                'posterPath' => '/poster.jpg',
                'ratings' => [
                    ['source' => 'imdb', 'name' => 'IMDb', 'value' => 8.7, 'voteCount' => 2000000],
                    ['source' => 'tmdb', 'name' => 'TMDB', 'value' => 8.4, 'voteCount' => 30000],
                ],
                'href' => route('movies.show', ['slug' => 'interstellar', 'id' => $movie->id, 'referrer' => 'picker']),
                'whereToWatch' => [
                    'country' => 'GB',
                    'countryName' => 'United Kingdom',
                    'groups' => [
                        ['type' => 'stream', 'label' => 'Stream', 'caption' => null, 'services' => [['id' => 8, 'name' => 'Netflix', 'logoPath' => '/netflix.png']]],
                        ['type' => 'free', 'label' => 'Free', 'caption' => 'Free, may include ads', 'services' => [['id' => 300, 'name' => 'Pluto TV', 'logoPath' => '/pluto.png']]],
                    ],
                    'updated' => 'Updated 16 days ago',
                    'outOfDate' => true,
                ],
            ],
        ]);

    expect($movie->fresh()->last_viewed_at->toDateTimeString())->toBe('2026-10-20 08:00:00');
});

test('each pick records movie_picker_used with the pool size', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $user = User::factory()->create();
    UserMovie::factory()->count(3)->for($user)->create();

    $this->actingAs($user)->getJson(route('watchlist.pick', ['genres' => [99]]))->assertOk();
    $this->getJson(route('watchlist.pick'))->assertOk();
    app()->terminate();

    Queue::assertPushed(RecordAnalyticsEvent::class, 2);
    Queue::assertPushed(RecordAnalyticsEvent::class, fn ($job) => $job->name === 'movie_picker_used' && $job->userId === $user->id && $job->properties === ['pool_size' => 0]);
    Queue::assertPushed(RecordAnalyticsEvent::class, fn ($job) => $job->name === 'movie_picker_used' && $job->properties === ['pool_size' => 3]);
});

test('picks are limited to 30 a minute per user', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->freezeTime();

    foreach (range(1, 30) as $attempt) {
        $this->getJson(route('watchlist.pick'))->assertOk();
    }

    $this->getJson(route('watchlist.pick'))->assertTooManyRequests()->assertHeader('Retry-After', '60');
    $this->actingAs(User::factory()->create())->getJson(route('watchlist.pick'))->assertOk();
});
