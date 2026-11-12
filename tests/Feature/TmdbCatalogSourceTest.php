<?php

use App\Enums\TitleKind;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Enums\CrewJob;
use App\Services\Catalog\Enums\SectionKind;
use App\Services\Catalog\Sources\Listings\BrowseFilters;
use App\Services\Catalog\Sources\Listings\BrowseSort;
use App\Services\Catalog\Sources\Listings\Era;
use App\Services\Catalog\Sources\Listings\MovieListing;
use App\Services\Catalog\Sources\Listings\SectionQuery;
use App\Services\Catalog\Sources\SourceMisconfigured;
use App\Services\Catalog\Sources\SourceUnavailable;
use App\Services\Catalog\Sources\Tmdb\TmdbCatalogSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

test('the catalog source translates recorded movie metadata with one authenticated detail call', function () {
    config(['catalog.tmdb.token' => 'test-read-token']);
    Http::fake([
        'https://api.themoviedb.org/3/movie/157336?*' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/tmdb/movie_157336.json')), true, flags: JSON_THROW_ON_ERROR)),
    ]);

    $movie = app(TmdbCatalogSource::class)->movie(157336, new CatalogContext('en-US'));

    expect($movie)->toMatchArray([
        'tmdbId' => 157336,
        'title' => 'Interstellar',
        'originalTitle' => 'Interstellar',
        'releaseDate' => '2014-11-04',
        'runtime' => 169,
        'posterPath' => '/yQvGrMoipbRoddT0ZR8tPoR7NfX.jpg',
        'backdropPath' => '/8sNiAPPYU14PUepFNeSNGUTiHW.jpg',
        'originalLanguage' => 'en',
        'imdbId' => 'tt0816692',
        'genres' => [
            ['id' => 12, 'name' => 'Adventure'],
            ['id' => 18, 'name' => 'Drama'],
            ['id' => 878, 'name' => 'Science Fiction'],
        ],
    ]);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-read-token')
        && $request['language'] === 'en-US'
        && $request['include_adult'] === 'false'
        && $request['include_video_language'] === 'en,null'
        && $request['append_to_response'] === 'credits,videos,release_dates,watch/providers'
        && ! isset($request['region']));
});

test('the catalog source treats a missing movie as an answer', function () {
    Http::fake(['https://api.themoviedb.org/3/movie/999?*' => Http::response(['status_code' => 34], 404)]);

    expect(app(TmdbCatalogSource::class)->movie(999, new CatalogContext))->toBeNull();
});

test('the catalog source fails explicitly on unsuccessful or malformed responses', function (array $body, int $status) {
    Http::fake(['https://api.themoviedb.org/3/movie/157336?*' => Http::response($body, $status)]);

    expect(fn () => app(TmdbCatalogSource::class)->movie(157336, new CatalogContext))->toThrow(SourceUnavailable::class);
})->with([
    'unauthorized' => [[], 401],
    'rate limited' => [[], 429],
    'server error' => [[], 500],
    'empty response' => [[], 200],
    'wrong id' => [['id' => 1, 'title' => 'Wrong Movie'], 200],
    'invalid release date' => [['id' => 157336, 'release_date' => 'not-a-date'], 200],
]);

test('the catalog source fails explicitly on a connection failure', function () {
    Http::fake(['https://api.themoviedb.org/3/movie/157336?*' => Http::failedConnection()]);

    expect(fn () => app(TmdbCatalogSource::class)->movie(157336, new CatalogContext))->toThrow(SourceUnavailable::class);
});

test('sparse movie details keep absent metadata nullable', function () {
    Http::fake(['https://api.themoviedb.org/3/movie/157336?*' => Http::response(['id' => 157336, 'release_date' => ''])]);

    $movie = app(TmdbCatalogSource::class)->movie(157336, new CatalogContext);

    expect($movie->title)->toBeNull();
    expect($movie->releaseDate)->toBeNull();
    expect($movie->genres)->toBe([]);
});

test('the catalog source reads credits and US release details from the recorded fixture', function () {
    Http::fake(['https://api.themoviedb.org/3/movie/157336?*' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/tmdb/movie_157336.json')), true, flags: JSON_THROW_ON_ERROR))]);

    $movie = app(TmdbCatalogSource::class)->movie(157336, new CatalogContext('en-US'));

    expect($movie->tagline)->toBe('Mankind was born on Earth. It was never meant to die here.')
        ->and($movie->directors)->toBe(['Christopher Nolan'])
        ->and($movie->writers)->toBe(['Jonathan Nolan', 'Christopher Nolan'])
        ->and($movie->certification)->toBe('PG-13')
        ->and($movie->usReleaseDate)->toBe('2014-11-07')
        ->and($movie->cast)->toHaveCount(37)
        ->and($movie->crew[0])->toBe(['id' => 525, 'name' => 'Christopher Nolan', 'job' => 'Director'])
        ->and(collect($movie->crew)->pluck('job')->unique()->values()->all())->each->toBeIn(CrewJob::names())
        ->and(collect($movie->crew)->map(fn (array $member): string => $member['id'].$member['job'])->duplicates())->toBeEmpty()
        ->and($movie->cast[0])->toBe(['id' => 10297, 'name' => 'Matthew McConaughey', 'character' => 'Cooper', 'profilePath' => '/lCySuYjhXix3FzQdS4oceDDrXKI.jpg']);
});

test('the US release date falls back to the earliest premiere and tolerates missing credits', function () {
    Http::fake(['https://api.themoviedb.org/3/movie/5?*' => Http::response([
        'id' => 5,
        'release_dates' => ['results' => [
            ['iso_3166_1' => 'FR', 'release_dates' => [['certification' => 'U', 'release_date' => '2000-01-01T00:00:00.000Z', 'type' => 3]]],
            ['iso_3166_1' => 'US', 'release_dates' => [
                ['certification' => '', 'release_date' => '2001-05-02T00:00:00.000Z', 'type' => 2],
                ['certification' => 'R', 'release_date' => '2001-03-01T00:00:00.000Z', 'type' => 2],
                ['certification' => '', 'release_date' => '2001-01-01T00:00:00.000Z', 'type' => 1],
            ]],
        ]],
    ])]);

    $movie = app(TmdbCatalogSource::class)->movie(5, new CatalogContext);

    expect($movie->usReleaseDate)->toBe('2001-03-01')
        ->and($movie->certification)->toBe('R')
        ->and($movie->directors)->toBe([])
        ->and($movie->cast)->toBe([]);
});

test('a US release without a certification key does not break the adapter', function () {
    Http::fake(['https://api.themoviedb.org/3/movie/6?*' => Http::response([
        'id' => 6,
        'release_dates' => ['results' => [['iso_3166_1' => 'US', 'release_dates' => [['release_date' => '2001-03-01T00:00:00.000Z', 'type' => 3]]]]],
    ])]);

    $movie = app(TmdbCatalogSource::class)->movie(6, new CatalogContext);

    expect($movie->usReleaseDate)->toBe('2001-03-01')->and($movie->certification)->toBeNull();
});

test('the US certification survives when no theatrical or premiere release exists', function () {
    Http::fake(['https://api.themoviedb.org/3/movie/8?*' => Http::response([
        'id' => 8,
        'release_dates' => ['results' => [['iso_3166_1' => 'US', 'release_dates' => [['certification' => 'PG', 'release_date' => '2001-03-01T00:00:00.000Z', 'type' => 4]]]]],
    ])]);

    $movie = app(TmdbCatalogSource::class)->movie(8, new CatalogContext);

    expect($movie->certification)->toBe('PG')->and($movie->usReleaseDate)->toBeNull();
});

test('the US digital release date is the earliest US digital release', function () {
    Http::fake(['https://api.themoviedb.org/3/movie/9?*' => Http::response([
        'id' => 9,
        'release_dates' => ['results' => [
            ['iso_3166_1' => 'GB', 'release_dates' => [['release_date' => '2026-11-10T00:00:00.000Z', 'type' => 4]]],
            ['iso_3166_1' => 'US', 'release_dates' => [
                ['release_date' => '2026-12-20T00:00:00.000Z', 'type' => 4],
                ['release_date' => '2026-11-21T00:00:00.000Z', 'type' => 4],
                ['release_date' => '2026-11-15T00:00:00.000Z', 'type' => 5],
            ]],
        ]],
    ])]);

    $movie = app(TmdbCatalogSource::class)->movie(9, new CatalogContext);

    expect($movie->usDigitalReleaseDate)->toBe('2026-11-21')->and($movie->usReleaseDate)->toBeNull();
});

test('interactive calls retry once on a server error and then succeed', function () {
    Sleep::fake();
    Http::fakeSequence('https://api.themoviedb.org/3/movie/157336?*')
        ->push([], 500)
        ->push(['id' => 157336, 'title' => 'Interstellar']);

    $movie = app(TmdbCatalogSource::class)->movie(157336, new CatalogContext);

    expect($movie->title)->toBe('Interstellar');
    Http::assertSentCount(2);
});

test('interactive calls give up after one retry', function (callable $fake) {
    Sleep::fake();
    $fake();

    expect(fn () => app(TmdbCatalogSource::class)->movie(157336, new CatalogContext))->toThrow(SourceUnavailable::class);
    Http::assertSentCount(2);
})->with([
    'server errors' => [fn () => Http::fake(['*' => Http::response([], 503)])],
    'rate limited' => [fn () => Http::fake(['*' => Http::response([], 429)])],
    'connection errors' => [fn () => Http::fake(['*' => Http::failedConnection()])],
]);

test('over the background throttle, a background request waits for the next second', function () {
    config(['catalog.guard.background_requests_per_second' => 1]);
    Sleep::fake();
    Sleep::whenFakingSleep(fn () => Cache::flush()); // The throttle's window lives in Redis: emptying it ends the second.
    Http::fake(['https://api.themoviedb.org/3/movie/*' => Http::response(['id' => 157336])]);
    $background = new CatalogContext(profile: CatalogProfile::Background);

    app(TmdbCatalogSource::class)->movie(157336, $background);
    Sleep::assertNeverSlept();
    app(TmdbCatalogSource::class)->movie(157336, $background);

    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

test('background retries take their own turns, and a request still over the throttle after its wait fails unsent', function () {
    config(['catalog.guard.background_requests_per_second' => 2, 'catalog.guard.background_wait_seconds' => 3]);
    Sleep::fake();
    Http::fakeSequence('https://api.themoviedb.org/3/movie/157336?*')
        ->push([], 500)
        ->push(['id' => 157336]);
    $background = new CatalogContext(profile: CatalogProfile::Background);

    expect(app(TmdbCatalogSource::class)->movie(157336, $background)->tmdbId)->toBe(157336)
        ->and(fn () => app(TmdbCatalogSource::class)->movie(157336, $background))->toThrow(SourceUnavailable::class);
    Http::assertSentCount(2);
    Sleep::assertSlept(fn ($duration) => $duration->totalMilliseconds == 100, 30);
});

test('interactive requests are not held by the background throttle', function () {
    config(['catalog.guard.background_requests_per_second' => 1]);
    Sleep::fake();
    Http::fake(['https://api.themoviedb.org/3/movie/*' => Http::response(['id' => 157336])]);

    app(TmdbCatalogSource::class)->movie(157336, new CatalogContext(profile: CatalogProfile::Background));
    app(TmdbCatalogSource::class)->movie(157336, new CatalogContext);

    Http::assertSentCount(2);
    Sleep::assertNeverSlept();
});

test('a 429 honours Retry-After but waits at most two seconds', function (string $retryAfter, int $expectedMicroseconds) {
    Sleep::fake();
    Http::fakeSequence('https://api.themoviedb.org/3/movie/157336?*')
        ->push([], 429, ['Retry-After' => $retryAfter])
        ->push(['id' => 157336]);

    app(TmdbCatalogSource::class)->movie(157336, new CatalogContext);

    Sleep::assertSleptTimes(1);
    Sleep::assertSlept(fn ($duration) => $duration->totalMicroseconds == $expectedMicroseconds);
})->with([
    'short' => ['1', 1_000_000],
    'capped' => ['30', 2_000_000],
    'missing falls back to the default delay' => ['', 100_000],
    'zero falls back to the default delay' => ['0', 100_000],
    'an HTTP date falls back to the default delay' => ['Wed, 21 Oct 2026 07:28:00 GMT', 100_000],
]);

test('background calls make three attempts', function () {
    Sleep::fake();
    Http::fake(['*' => Http::response([], 500)]);

    expect(fn () => app(TmdbCatalogSource::class)->movie(157336, new CatalogContext(profile: CatalogProfile::Background)))->toThrow(SourceUnavailable::class);

    Http::assertSentCount(3);
    Sleep::assertSleptTimes(2);
});

test('a 404 is an answer and is not retried', function () {
    Sleep::fake();
    Http::fake(['*' => Http::response(['status_code' => 34], 404)]);

    expect(app(TmdbCatalogSource::class)->movie(999, new CatalogContext))->toBeNull();

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

test('a 401 is raised immediately as a configuration error and is not retried', function () {
    Sleep::fake();
    Http::fake(['*' => Http::response([], 401)]);

    expect(fn () => app(TmdbCatalogSource::class)->movie(157336, new CatalogContext))->toThrow(SourceMisconfigured::class);

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

test('the ranked trailer for the recorded Interstellar fixture is "Trailer 4"', function () {
    Http::fake(['https://api.themoviedb.org/3/movie/157336?*' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/tmdb/movie_157336.json')), true, flags: JSON_THROW_ON_ERROR))]);

    $movie = app(TmdbCatalogSource::class)->movie(157336, new CatalogContext('en-US'));

    expect($movie->trailer)->toBe(['key' => 'LY19rHKAaAg', 'name' => 'Trailer 4', 'type' => 'Trailer']);
});

function trailerFor(array $videos, string $releaseDate = '2020-06-01', string $language = 'en-US'): ?array
{
    Http::fake(['https://api.themoviedb.org/3/movie/11?*' => Http::response([
        'id' => 11,
        'release_date' => $releaseDate,
        'videos' => ['results' => $videos],
    ])]);

    return app(TmdbCatalogSource::class)->movie(11, new CatalogContext($language))->trailer;
}

function video(string $key, array $overrides = []): array
{
    return array_merge(['key' => $key, 'name' => "Video {$key}", 'site' => 'YouTube', 'type' => 'Trailer', 'official' => true, 'iso_639_1' => 'en', 'published_at' => '2020-01-01T00:00:00.000Z'], $overrides);
}

test('trailer ranking prefers Trailer over Teaser, then official, then a language match', function (array $videos, string $expectedKey) {
    expect(trailerFor($videos)['key'])->toBe($expectedKey);
})->with([
    'trailer before teaser' => [[video('AAAAAAAAAAA', ['type' => 'Teaser']), video('BBBBBBBBBBB')], 'BBBBBBBBBBB'],
    'official before unofficial' => [[video('AAAAAAAAAAA', ['official' => false]), video('BBBBBBBBBBB')], 'BBBBBBBBBBB'],
    'language match before another language' => [[video('AAAAAAAAAAA', ['iso_639_1' => 'fr']), video('BBBBBBBBBBB')], 'BBBBBBBBBBB'],
    'a teaser when there is no trailer' => [[video('AAAAAAAAAAA', ['type' => 'Teaser'])], 'AAAAAAAAAAA'],
]);

test('trailer ties go to the latest published on or before the release date, else the earliest', function (array $videos, string $expectedKey) {
    expect(trailerFor($videos, '2020-06-01')['key'])->toBe($expectedKey);
})->with([
    'latest before release' => [[
        video('AAAAAAAAAAA', ['published_at' => '2020-01-01T00:00:00.000Z']),
        video('BBBBBBBBBBB', ['published_at' => '2020-05-01T00:00:00.000Z']),
        video('CCCCCCCCCCC', ['published_at' => '2020-07-01T00:00:00.000Z']),
    ], 'BBBBBBBBBBB'],
    'an undated video never counts as published before release' => [[
        video('AAAAAAAAAAA', ['published_at' => null]),
        video('BBBBBBBBBBB', ['published_at' => '2020-05-01T00:00:00.000Z']),
    ], 'BBBBBBBBBBB'],
    'all after release picks the earliest' => [[
        video('AAAAAAAAAAA', ['published_at' => '2020-09-01T00:00:00.000Z']),
        video('BBBBBBBBBBB', ['published_at' => '2020-07-01T00:00:00.000Z']),
    ], 'BBBBBBBBBBB'],
]);

test('trailers must be YouTube videos with a well-formed key', function (array $videos) {
    expect(trailerFor($videos))->toBeNull();
})->with([
    'no videos' => [[]],
    'another site' => [[video('AAAAAAAAAAA', ['site' => 'Vimeo'])]],
    'short key' => [[video('short')]],
    'unsafe key' => [[video('AAAAA"><script')]],
    'trailing newline' => [[video("AAAAAAAAAAA\n")]],
    'clips only' => [[video('AAAAAAAAAAA', ['type' => 'Clip'])]],
]);

test('Availability is mapped for the supported countries only, with flatrate as stream', function () {
    Http::fake(['https://api.themoviedb.org/3/movie/157336?*' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/tmdb/movie_157336.json')), true, flags: JSON_THROW_ON_ERROR))]);

    $availability = app(TmdbCatalogSource::class)->movie(157336, new CatalogContext('en-US'))->availability;

    expect(collect($availability)->pluck('country')->unique()->sort()->values()->all())->toBe(['AU', 'CA', 'DE', 'ES', 'FR', 'GB', 'IE', 'IT', 'NZ', 'US'])
        ->and(collect($availability)->first(fn ($offer) => $offer['country'] === 'US' && $offer['offerType'] === 'stream'))
        ->toBe(['serviceId' => 350, 'serviceName' => 'Apple TV', 'logoPath' => '/9icYBfYFcwgCbky5VdGUIKJ4C5i.png', 'country' => 'US', 'offerType' => 'stream', 'displayPriority' => 5])
        ->and(collect($availability)->where('country', 'US')->where('offerType', 'rent')->count())->toBe(8)
        ->and(collect($availability)->where('country', 'US')->where('offerType', 'buy')->count())->toBe(5);
});

test('free and ads both map to free, keeping one row per service with its best priority', function () {
    Http::fake(['https://api.themoviedb.org/3/movie/5?*' => Http::response([
        'id' => 5,
        'watch/providers' => ['results' => [
            'US' => [
                'free' => [['provider_id' => 73, 'provider_name' => 'Tubi TV', 'logo_path' => '/tubi.jpg', 'display_priority' => 8]],
                'ads' => [
                    ['provider_id' => 73, 'provider_name' => 'Tubi TV', 'logo_path' => '/tubi.jpg', 'display_priority' => 3],
                    ['provider_id' => 300, 'provider_name' => 'Pluto TV', 'logo_path' => '/pluto.jpg', 'display_priority' => 12],
                ],
            ],
            'BR' => ['flatrate' => [['provider_id' => 8, 'provider_name' => 'Netflix', 'logo_path' => '/n.jpg', 'display_priority' => 1]]],
        ]],
    ])]);

    $availability = app(TmdbCatalogSource::class)->movie(5, new CatalogContext)->availability;

    expect($availability)->toBe([
        ['serviceId' => 73, 'serviceName' => 'Tubi TV', 'logoPath' => '/tubi.jpg', 'country' => 'US', 'offerType' => 'free', 'displayPriority' => 3],
        ['serviceId' => 300, 'serviceName' => 'Pluto TV', 'logoPath' => '/pluto.jpg', 'country' => 'US', 'offerType' => 'free', 'displayPriority' => 12],
    ]);
});

test('malformed Availability entries are dropped and an unsafe logo path is cleared', function () {
    Http::fake(['https://api.themoviedb.org/3/movie/6?*' => Http::response([
        'id' => 6,
        'watch/providers' => ['results' => [
            'US' => [
                'flatrate' => [
                    ['provider_id' => 'eight', 'provider_name' => 'Bad id', 'display_priority' => 1],
                    ['provider_id' => 9, 'display_priority' => 1],
                    ['provider_id' => 10, 'provider_name' => 'No logo', 'logo_path' => 'https://evil.example/x.png', 'display_priority' => 2],
                ],
                'link' => 'https://evil.example',
            ],
        ]],
    ])]);

    expect(app(TmdbCatalogSource::class)->movie(6, new CatalogContext)->availability)->toBe([
        ['serviceId' => 10, 'serviceName' => 'No logo', 'logoPath' => null, 'country' => 'US', 'offerType' => 'stream', 'displayPriority' => 2],
    ]);
});

test('a section page is translated from the recorded Trending fixture with one call carrying no region', function () {
    config(['catalog.tmdb.token' => 'test-read-token']);
    Http::fake(['https://api.themoviedb.org/3/trending/movie/week?*' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/tmdb/trending_p2.json')), true, flags: JSON_THROW_ON_ERROR))]);

    $page = app(TmdbCatalogSource::class)->section(SectionQuery::of(SectionKind::Trending), 2, new CatalogContext('en-US', 'US'));

    expect($page->totalPages)->toBe(500)
        ->and($page->listings)->toHaveCount(20)
        ->and($page->listings[0])->toMatchArray([
            'tmdbId' => 1170608,
            'title' => 'Dune: Part Three',
            'releaseDate' => '2026-12-15',
            'posterPath' => '/glt0v4I89rMWstDNCaFXc5SNyO1.jpg',
            'backdropPath' => '/i5E9H7Ik0u61ylDDTbmUpTL3Yw.jpg',
            'voteAverage' => 0.0,
            'voteCount' => 0,
        ]);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-read-token')
        && str_contains($request->url(), 'page=2')
        && $request['language'] === 'en-US'
        && $request['include_adult'] === 'false'
        && ! isset($request['region']));
});

test('each section kind reads its own TMDB list', function (SectionKind $kind, string $path) {
    Http::fake(["https://api.themoviedb.org/3/{$path}?*" => Http::response(['page' => 1, 'total_pages' => 1, 'results' => []])]);

    expect(app(TmdbCatalogSource::class)->section(SectionQuery::of($kind), 1, new CatalogContext)->listings)->toBe([]);
    Http::assertSentCount(1);
})->with([
    'Trending' => [SectionKind::Trending, 'trending/movie/week'],
    'Popular' => [SectionKind::Popular, 'movie/popular'],
    'Top rated' => [SectionKind::TopRated, 'movie/top_rated'],
]);

/**
 * The request's query, read without `parse_str`, which turns the dots in TMDB's `release_date.gte` into underscores.
 *
 * @return array<string, string>
 */
function queryOf(Request $request): array
{
    $query = [];

    foreach (explode('&', (string) parse_url($request->url(), PHP_URL_QUERY)) as $pair) {
        [$name, $value] = array_map(urldecode(...), explode('=', $pair, 2) + [1 => '']);
        $query[$name] = $value;
    }

    return $query;
}

test('Coming soon reads TMDB\'s discover list by US theatrical and digital release dates in its window', function () {
    Http::fake(['https://api.themoviedb.org/3/discover/movie?*' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/tmdb/upcoming_us.json')), true, flags: JSON_THROW_ON_ERROR))]);

    $page = app(TmdbCatalogSource::class)->section(SectionQuery::comingSoon('2026-11-07', '2027-01-05'), 2, new CatalogContext('en-US'));

    expect($page->totalPages)->toBe(4)->and($page->listings)->toHaveCount(20);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => queryOf($request) === [
        'language' => 'en-US',
        'include_adult' => 'false',
        'page' => '2',
        'region' => 'US',
        'with_release_type' => '2|3|4',
        'release_date.gte' => '2026-11-07',
        'release_date.lte' => '2027-01-05',
        'sort_by' => 'popularity.desc',
    ]);
});

test('a genre collection reads TMDB\'s discover list for the genre by popularity, with a vote floor and no region', function () {
    Http::fake(['https://api.themoviedb.org/3/discover/movie?*' => Http::response(['page' => 1, 'total_pages' => 1, 'results' => [['id' => 1, 'title' => 'Kept', 'poster_path' => '/kept.jpg']]])]);

    $page = app(TmdbCatalogSource::class)->section(SectionQuery::genre(878, minimumVotes: 300), 1, new CatalogContext('en-US'));

    expect(array_map(fn (MovieListing $listing): int => $listing->tmdbId, $page->listings))->toBe([1]);
    Http::assertSent(fn (Request $request) => queryOf($request) === [
        'language' => 'en-US',
        'include_adult' => 'false',
        'page' => '1',
        'with_genres' => '878',
        'vote_count.gte' => '300',
        'sort_by' => 'popularity.desc',
    ]);
});

test('with TV Shows off, a search page is translated from the recorded Movie fixture, in TMDB\'s order with poster-less Movies kept', function () {
    config(['catalog.tmdb.token' => 'test-read-token', 'catalog.tv_shows.enabled' => false]);
    Http::fake(['https://api.themoviedb.org/3/search/movie?*' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/tmdb/search_inter.json')), true, flags: JSON_THROW_ON_ERROR))]);

    $results = app(TmdbCatalogSource::class)->search('inter', 1, new CatalogContext('en-US'));

    expect($results->totalPages)->toBe(208)
        ->and($results->totalResults)->toBe(4141)
        ->and($results->listings)->toHaveCount(20)
        ->and($results->listings[0])->toMatchArray(['tmdbId' => 529462, 'title' => 'Inter View', 'releaseDate' => '2011-01-26', 'voteAverage' => 1.0, 'voteCount' => 1])
        ->and(collect($results->listings)->whereNull('posterPath'))->toHaveCount(3);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-read-token') && queryOf($request) === [
        'query' => 'inter',
        'page' => '1',
        'language' => 'en-US',
        'include_adult' => 'false',
    ]);
});

test('a search fails explicitly on unsuccessful or malformed responses', function (array $body, int $status) {
    config(['catalog.tv_shows.enabled' => false]);
    Http::fake(['https://api.themoviedb.org/3/search/movie?*' => Http::response($body, $status)]);

    expect(fn () => app(TmdbCatalogSource::class)->search('inter', 1, new CatalogContext))->toThrow(SourceUnavailable::class);
})->with([
    'not found' => [[], 404],
    'server error' => [[], 500],
    'no results' => [['page' => 1, 'total_pages' => 1, 'total_results' => 0], 200],
    'no result count' => [['page' => 1, 'total_pages' => 1, 'results' => []], 200],
]);

test('adult, malformed and duplicate listings are dropped, and unsafe image paths are cleared', function () {
    Http::fake(['https://api.themoviedb.org/3/movie/popular?*' => Http::response(['page' => 1, 'total_pages' => 3, 'results' => [
        ['id' => 1, 'title' => 'Kept', 'poster_path' => '/kept.jpg', 'backdrop_path' => 'javascript:alert(1)', 'release_date' => ''],
        ['id' => 2, 'title' => 'Adult', 'adult' => true, 'poster_path' => '/adult.jpg'],
        ['id' => 'three', 'title' => 'Bad id'],
        'not a listing',
        ['id' => 4, 'title' => 'Bad score', 'vote_average' => 11],
        ['id' => 1, 'title' => 'Repeated'],
    ]])]);

    $page = app(TmdbCatalogSource::class)->section(SectionQuery::of(SectionKind::Popular), 1, new CatalogContext);

    expect($page->totalPages)->toBe(3)
        ->and(array_map(fn (MovieListing $listing): int => $listing->tmdbId, $page->listings))->toBe([1])
        ->and($page->listings[0]->posterPath)->toBe('/kept.jpg')
        ->and($page->listings[0]->backdropPath)->toBeNull()
        ->and($page->listings[0]->releaseDate)->toBeNull();
});

test('a Browse page asks for the Movie discover list by popularity, with no text query and no adult listings', function () {
    config(['catalog.tmdb.token' => 'test-read-token']);
    Http::fake(['https://api.themoviedb.org/3/discover/movie?*' => Http::response(['page' => 3, 'total_pages' => 9, 'total_results' => 170, 'results' => [
        ['id' => 1, 'title' => 'Kept', 'poster_path' => '/kept.jpg', 'release_date' => '2026-01-01', 'vote_average' => 7.5, 'vote_count' => 100],
    ]])]);

    $results = app(TmdbCatalogSource::class)->browse(TitleKind::Movie, 3, new CatalogContext('en-US'));

    expect($results->totalPages)->toBe(9)
        ->and($results->totalResults)->toBe(170)
        ->and(array_map(fn (MovieListing $listing): int => $listing->tmdbId, $results->listings))->toBe([1]);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-read-token') && queryOf($request) === [
        'page' => '3',
        'language' => 'en-US',
        'include_adult' => 'false',
        'sort_by' => 'popularity.desc',
    ]);
});

test('a Browse page drops adult, malformed and repeated listings and clears unsafe image paths', function () {
    Http::fake(['https://api.themoviedb.org/3/discover/movie?*' => Http::response(['page' => 1, 'total_pages' => 2, 'total_results' => 40, 'results' => [
        ['id' => 1, 'title' => 'Kept', 'poster_path' => '/kept.jpg', 'backdrop_path' => 'javascript:alert(1)'],
        ['id' => 2, 'title' => 'Adult', 'adult' => true],
        ['id' => 'three', 'title' => 'Bad id'],
        'not a listing',
        ['id' => 1, 'title' => 'Repeated'],
    ]])]);

    $results = app(TmdbCatalogSource::class)->browse(TitleKind::Movie, 1, new CatalogContext);

    expect(array_map(fn (MovieListing $listing): int => $listing->tmdbId, $results->listings))->toBe([1])
        ->and($results->listings[0]->backdropPath)->toBeNull();
});

test('a Browse page fails explicitly on unsuccessful or malformed responses', function (array $body, int $status) {
    Http::fake(['https://api.themoviedb.org/3/discover/movie?*' => Http::response($body, $status)]);

    expect(fn () => app(TmdbCatalogSource::class)->browse(TitleKind::Movie, 1, new CatalogContext))->toThrow(SourceUnavailable::class);
})->with([
    'not found' => [[], 404],
    'server error' => [[], 500],
    'no results' => [['page' => 1, 'total_pages' => 1, 'total_results' => 0], 200],
    'no result count' => [['page' => 1, 'total_pages' => 1, 'results' => []], 200],
]);

test('a TV Shows Browse page asks for the TV discover list by popularity and reads each Name and first-air date as a listing\'s title and date', function () {
    config(['catalog.tmdb.token' => 'test-read-token']);
    Http::fake(['https://api.themoviedb.org/3/discover/tv?*' => Http::response(['page' => 2, 'total_pages' => 7, 'total_results' => 130, 'results' => [
        ['id' => 1, 'name' => 'Chernobyl', 'first_air_date' => '2019-05-06', 'poster_path' => '/c.jpg', 'vote_average' => 8.5, 'vote_count' => 100],
        ['id' => 2, 'name' => 'Adult', 'adult' => true],
        ['id' => 'three', 'name' => 'Bad id'],
        'not a listing',
        ['id' => 1, 'name' => 'Repeated'],
    ]])]);

    $results = app(TmdbCatalogSource::class)->browse(TitleKind::TvShow, 2, new CatalogContext('en-US'));

    expect($results->totalPages)->toBe(7)
        ->and($results->totalResults)->toBe(130)
        ->and($results->listings)->toHaveCount(1)
        ->and($results->listings[0])->toMatchArray(['tmdbId' => 1, 'title' => 'Chernobyl', 'releaseDate' => '2019-05-06', 'kind' => TitleKind::TvShow, 'voteAverage' => 8.5, 'voteCount' => 100]);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-read-token') && queryOf($request) === [
        'page' => '2',
        'language' => 'en-US',
        'include_adult' => 'false',
        'sort_by' => 'popularity.desc',
    ]);
});

test('a TV Shows Browse page fails explicitly on unsuccessful or malformed responses', function (array $body, int $status) {
    Http::fake(['https://api.themoviedb.org/3/discover/tv?*' => Http::response($body, $status)]);

    expect(fn () => app(TmdbCatalogSource::class)->browse(TitleKind::TvShow, 1, new CatalogContext))->toThrow(SourceUnavailable::class);
})->with([
    'not found' => [[], 404],
    'no result count' => [['page' => 1, 'total_pages' => 1, 'results' => []], 200],
]);

test('a Browse page narrowed by a genre sends it as the genre to discover, for each kind', function (TitleKind $kind, string $path) {
    Http::fake(["https://api.themoviedb.org/3/{$path}?*" => Http::response(['page' => 1, 'total_pages' => 1, 'total_results' => 0, 'results' => []])]);

    app(TmdbCatalogSource::class)->browse($kind, 1, new CatalogContext('en-US'), new BrowseFilters(genreId: 35, requestedSort: BrowseSort::Popular));

    Http::assertSent(fn (Request $request) => queryOf($request) === [
        'page' => '1',
        'language' => 'en-US',
        'include_adult' => 'false',
        'sort_by' => 'popularity.desc',
        'with_genres' => '35',
    ]);
})->with([
    'Movies' => [TitleKind::Movie, 'discover/movie'],
    'TV Shows' => [TitleKind::TvShow, 'discover/tv'],
]);

test('the genre list is read from the list of the kind asked for', function (TitleKind $kind, string $path) {
    Http::fake(["https://api.themoviedb.org/3/{$path}?*" => Http::response(['genres' => [['id' => 18, 'name' => 'Drama']]])]);

    expect(app(TmdbCatalogSource::class)->genres(new CatalogContext('en-US'), $kind))->toBe([['id' => 18, 'name' => 'Drama']]);
    Http::assertSentCount(1);
})->with([
    'Movies' => [TitleKind::Movie, 'genre/movie/list'],
    'TV Shows' => [TitleKind::TvShow, 'genre/tv/list'],
]);

test('the TV genre list fails explicitly on an empty or unsuccessful response', function (array $body, int $status) {
    Http::fake(['https://api.themoviedb.org/3/genre/tv/list?*' => Http::response($body, $status)]);

    expect(fn () => app(TmdbCatalogSource::class)->genres(new CatalogContext, TitleKind::TvShow))->toThrow(SourceUnavailable::class);
})->with([
    'not found' => [[], 404],
    'empty list' => [['genres' => []], 200],
]);

test('a section fails explicitly on unsuccessful or malformed responses', function (array $body, int $status) {
    Http::fake(['https://api.themoviedb.org/3/movie/top_rated?*' => Http::response($body, $status)]);

    expect(fn () => app(TmdbCatalogSource::class)->section(SectionQuery::of(SectionKind::TopRated), 1, new CatalogContext))->toThrow(SourceUnavailable::class);
})->with([
    'not found' => [[], 404],
    'server error' => [[], 500],
    'no results' => [['page' => 1, 'total_pages' => 1], 200],
    'no page count' => [['page' => 1, 'results' => []], 200],
]);

test('a section raises a rejected token as a configuration error', function () {
    Http::fake(['https://api.themoviedb.org/3/movie/popular?*' => Http::response([], 401)]);

    expect(fn () => app(TmdbCatalogSource::class)->section(SectionQuery::of(SectionKind::Popular), 1, new CatalogContext))->toThrow(SourceMisconfigured::class);
});

test('the genre list is read in the configured language', function () {
    Http::fake(['https://api.themoviedb.org/3/genre/movie/list?*' => Http::response(['genres' => [['id' => 28, 'name' => 'Action'], ['id' => 878, 'name' => 'Science Fiction']]])]);

    expect(app(TmdbCatalogSource::class)->genres(new CatalogContext('en-US')))->toBe([['id' => 28, 'name' => 'Action'], ['id' => 878, 'name' => 'Science Fiction']]);
    Http::assertSent(fn (Request $request) => $request['language'] === 'en-US');
});

test('the genre list fails explicitly on unsuccessful, empty or malformed responses', function (array $body, int $status) {
    Http::fake(['https://api.themoviedb.org/3/genre/movie/list?*' => Http::response($body, $status)]);

    expect(fn () => app(TmdbCatalogSource::class)->genres(new CatalogContext))->toThrow(SourceUnavailable::class);
})->with([
    'not found' => [[], 404],
    'server error' => [[], 500],
    'no genres' => [['genres' => []], 200],
    'a nameless genre' => [['genres' => [['id' => 28]]], 200],
]);

test('a Movie\'s IMDb id is read from the recorded external ids with one small call', function () {
    config(['catalog.tmdb.token' => 'test-read-token']);
    Http::fake(['https://api.themoviedb.org/3/movie/157336/external_ids*' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/tmdb/external_ids_157336.json')), true, flags: JSON_THROW_ON_ERROR))]);

    expect(app(TmdbCatalogSource::class)->imdbId(157336, new CatalogContext))->toBe('tt0816692');
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-read-token') && $request->url() === 'https://api.themoviedb.org/3/movie/157336/external_ids');
});

test('a Movie with no usable IMDb id, or none at TMDB, has none', function (array $body, int $status) {
    Http::fake(['https://api.themoviedb.org/3/movie/9/external_ids*' => Http::response($body, $status)]);

    expect(app(TmdbCatalogSource::class)->imdbId(9, new CatalogContext))->toBeNull();
})->with([
    'null' => [['id' => 9, 'imdb_id' => null], 200],
    'empty' => [['id' => 9, 'imdb_id' => ''], 200],
    'not an IMDb title id' => [['id' => 9, 'imdb_id' => 'nm0000093'], 200],
    'a missing Movie' => [['status_code' => 34], 404],
]);

test('the external ids fail explicitly on unsuccessful or malformed responses', function (array $body, int $status) {
    Http::fake(['https://api.themoviedb.org/3/movie/9/external_ids*' => Http::response($body, $status)]);

    expect(fn () => app(TmdbCatalogSource::class)->imdbId(9, new CatalogContext))->toThrow(SourceUnavailable::class);
})->with([
    'server error' => [[], 500],
    'another Movie\'s ids' => [['id' => 10, 'imdb_id' => 'tt0000001'], 200],
    'no id' => [['imdb_id' => 'tt0000001'], 200],
    'a non-string IMDb id' => [['id' => 9, 'imdb_id' => ['tt0000001']], 200],
]);

test('a rejected token on the external ids is a configuration error', function () {
    Http::fake(['https://api.themoviedb.org/3/movie/9/external_ids*' => Http::response([], 401)]);

    expect(fn () => app(TmdbCatalogSource::class)->imdbId(9, new CatalogContext))->toThrow(SourceMisconfigured::class);
});

test('with TV Shows on, a search reads every kind, telling Movies from TV Shows by media type and dropping people', function () {
    config(['catalog.tmdb.token' => 'test-read-token', 'catalog.tv_shows.enabled' => true]);
    Http::fake(['https://api.themoviedb.org/3/search/multi?*' => Http::response(['page' => 1, 'total_pages' => 2, 'total_results' => 5, 'results' => [
        ['media_type' => 'movie', 'id' => 1, 'title' => 'Chernobyl 1', 'release_date' => '2001-02-03', 'poster_path' => '/m.jpg', 'vote_average' => 6.5, 'vote_count' => 10],
        ['media_type' => 'tv', 'id' => 1, 'name' => 'Chernobyl', 'first_air_date' => '2019-05-06', 'poster_path' => '/t.jpg', 'vote_average' => 8.5, 'vote_count' => 100],
        ['media_type' => 'person', 'id' => 7, 'name' => 'Someone'],
        ['media_type' => 'tv', 'id' => 1, 'name' => 'Repeated', 'first_air_date' => '2020-01-01'],
        ['media_type' => 'tv', 'id' => 2, 'name' => 'Adult', 'adult' => true],
    ]])]);

    $results = app(TmdbCatalogSource::class)->search('chernobyl', 1, new CatalogContext('en-US'));

    expect($results->listings)->toHaveCount(2)
        ->and($results->totalResults)->toBe(5)
        ->and($results->listings[0])->toMatchArray(['kind' => TitleKind::Movie, 'tmdbId' => 1, 'title' => 'Chernobyl 1', 'releaseDate' => '2001-02-03'])
        ->and($results->listings[1])->toMatchArray(['kind' => TitleKind::TvShow, 'tmdbId' => 1, 'title' => 'Chernobyl', 'releaseDate' => '2019-05-06', 'posterPath' => '/t.jpg', 'voteAverage' => 8.5, 'voteCount' => 100]);
    Http::assertSent(fn (Request $request) => queryOf($request) === ['query' => 'chernobyl', 'page' => '1', 'language' => 'en-US', 'include_adult' => 'false']);
});

test('a TV Show\'s IMDb id comes from the TV external ids', function () {
    config(['catalog.tmdb.token' => 'test-read-token']);
    Http::fake(['https://api.themoviedb.org/3/tv/5/external_ids*' => Http::response(['id' => 5, 'imdb_id' => 'tt0000005'])]);

    expect(app(TmdbCatalogSource::class)->tvImdbId(5, new CatalogContext('en-US')))->toBe('tt0000005');
});

test('a Browse page sends the sort to discover for each kind, with a vote floor for Top rated', function (TitleKind $kind, string $path, BrowseSort $sort, array $expected) {
    config(['catalog.search.top_rated_minimum_votes' => 250]);
    Http::fake(["https://api.themoviedb.org/3/{$path}?*" => Http::response(['page' => 1, 'total_pages' => 1, 'total_results' => 0, 'results' => []])]);

    app(TmdbCatalogSource::class)->browse($kind, 1, new CatalogContext('en-US'), new BrowseFilters(requestedSort: $sort));

    Http::assertSent(fn (Request $request) => queryOf($request) === ['page' => '1', 'language' => 'en-US', 'include_adult' => 'false', ...$expected]);
})->with([
    'Movies, Popular' => [TitleKind::Movie, 'discover/movie', BrowseSort::Popular, ['sort_by' => 'popularity.desc']],
    'Movies, Top rated' => [TitleKind::Movie, 'discover/movie', BrowseSort::TopRated, ['sort_by' => 'vote_average.desc', 'vote_count.gte' => '250']],
    'TV Shows, Popular' => [TitleKind::TvShow, 'discover/tv', BrowseSort::Popular, ['sort_by' => 'popularity.desc']],
    'TV Shows, Top rated' => [TitleKind::TvShow, 'discover/tv', BrowseSort::TopRated, ['sort_by' => 'vote_average.desc', 'vote_count.gte' => '250']],
]);

test('a Browse page with no sort chosen takes the default of its filters', function () {
    Http::fake(['https://api.themoviedb.org/3/discover/movie?*' => Http::response(['page' => 1, 'total_pages' => 1, 'total_results' => 0, 'results' => []])]);

    app(TmdbCatalogSource::class)->browse(TitleKind::Movie, 1, new CatalogContext('en-US'), new BrowseFilters(genreId: 35));

    Http::assertSent(fn (Request $request) => ($query = queryOf($request)) && $query['sort_by'] === 'vote_average.desc' && isset($query['vote_count.gte']));
});

test('a Browse page narrowed by Streaming services sends them as any-of, with the watch region and only subscription and free offers', function (TitleKind $kind, string $path) {
    Http::fake(["https://api.themoviedb.org/3/{$path}?*" => Http::response(['page' => 1, 'total_pages' => 1, 'total_results' => 0, 'results' => []])]);

    app(TmdbCatalogSource::class)->browse($kind, 1, new CatalogContext('en-US'), new BrowseFilters(streamingServiceIds: [8, 384], country: 'CA'));

    Http::assertSent(fn (Request $request) => queryOf($request) === [
        'page' => '1',
        'language' => 'en-US',
        'include_adult' => 'false',
        'sort_by' => 'vote_average.desc',
        'vote_count.gte' => '300',
        'with_watch_providers' => '8|384',
        'watch_region' => 'CA',
        'with_watch_monetization_types' => 'flatrate|free|ads',
    ]);
})->with([
    'Movies' => [TitleKind::Movie, 'discover/movie'],
    'TV Shows' => [TitleKind::TvShow, 'discover/tv'],
]);

test('a Browse page with no Streaming services sends no provider, region or offer parameters', function () {
    Http::fake(['https://api.themoviedb.org/3/discover/movie?*' => Http::response(['page' => 1, 'total_pages' => 1, 'total_results' => 0, 'results' => []])]);

    app(TmdbCatalogSource::class)->browse(TitleKind::Movie, 1, new CatalogContext('en-US'), new BrowseFilters(country: 'CA'));

    Http::assertSent(fn (Request $request) => array_intersect_key(queryOf($request), array_flip(['with_watch_providers', 'watch_region', 'with_watch_monetization_types'])) === []);
});

test('the Streaming services are read from the list of the kind asked for, in the country, most prominent there first', function (TitleKind $kind, string $path) {
    Http::fake(["https://api.themoviedb.org/3/{$path}?*" => Http::response(['results' => [
        ['provider_id' => 15, 'provider_name' => 'Hulu', 'display_priorities' => ['US' => 9, 'CA' => 1]],
        ['provider_id' => 8, 'provider_name' => 'Netflix', 'display_priorities' => ['US' => 2, 'CA' => 5]],
        ['provider_id' => 700, 'provider_name' => 'Zed', 'display_priorities' => ['CA' => 1]],
        ['provider_id' => 701, 'provider_name' => 'Alpha'],
    ]])]);

    $services = app(TmdbCatalogSource::class)->streamingServices(new CatalogContext('en-US', 'US'), $kind);

    expect($services)->toBe([['id' => 8, 'name' => 'Netflix'], ['id' => 15, 'name' => 'Hulu'], ['id' => 701, 'name' => 'Alpha'], ['id' => 700, 'name' => 'Zed']]);
    Http::assertSent(fn (Request $request) => queryOf($request) === ['language' => 'en-US', 'watch_region' => 'US']);
})->with([
    'Movies' => [TitleKind::Movie, 'watch/providers/movie'],
    'TV Shows' => [TitleKind::TvShow, 'watch/providers/tv'],
]);

test('the Streaming service list fails explicitly on an empty, malformed or unsuccessful response', function (array $body, int $status) {
    Http::fake(['https://api.themoviedb.org/3/watch/providers/movie?*' => Http::response($body, $status)]);

    expect(fn () => app(TmdbCatalogSource::class)->streamingServices(new CatalogContext('en-US', 'US'), TitleKind::Movie))->toThrow(SourceUnavailable::class);
})->with([
    'empty list' => [['results' => []], 200],
    'missing list' => [[], 200],
    'no id' => [['results' => [['provider_name' => 'Netflix']]], 200],
    'no name' => [['results' => [['provider_id' => 8]]], 200],
    'repeated id' => [['results' => [['provider_id' => 8, 'provider_name' => 'A'], ['provider_id' => 8, 'provider_name' => 'B']]], 200],
    'server error' => [['results' => []], 500],
]);

test('a Highly rated Browse filter applies the TMDB score and vote-count floor for each kind', function (TitleKind $kind, string $path) {
    config(['catalog.search.top_rated_minimum_votes' => 250]);
    Http::fake(["https://api.themoviedb.org/3/{$path}?*" => Http::response(['page' => 1, 'total_pages' => 1, 'total_results' => 0, 'results' => []])]);

    app(TmdbCatalogSource::class)->browse($kind, 1, new CatalogContext('en-US'), new BrowseFilters(requestedSort: BrowseSort::Popular, highlyRated: true));

    Http::assertSent(fn (Request $request) => queryOf($request) === [
        'page' => '1',
        'language' => 'en-US',
        'include_adult' => 'false',
        'sort_by' => 'popularity.desc',
        'vote_average.gte' => '7',
        'vote_count.gte' => '250',
    ]);
})->with([
    'Movies' => [TitleKind::Movie, 'discover/movie'],
    'TV Shows' => [TitleKind::TvShow, 'discover/tv'],
]);

test('an era Browse filter uses the kind-specific TMDB date range', function (TitleKind $kind, string $dateField, Era $era, ?string $from, string $until) {
    $path = $kind === TitleKind::TvShow ? 'discover/tv' : 'discover/movie';
    Http::fake(["https://api.themoviedb.org/3/{$path}?*" => Http::response(['page' => 1, 'total_pages' => 1, 'total_results' => 0, 'results' => []])]);

    app(TmdbCatalogSource::class)->browse($kind, 1, new CatalogContext('en-US'), new BrowseFilters(requestedSort: BrowseSort::Popular, era: $era));

    $expected = [
        'page' => '1',
        'language' => 'en-US',
        'include_adult' => 'false',
        'sort_by' => 'popularity.desc',
    ];

    if ($from !== null) {
        $expected["{$dateField}.gte"] = $from;
    }

    $expected["{$dateField}.lte"] = $until;
    Http::assertSent(fn (Request $request) => queryOf($request) === $expected);
})->with([
    'Movies 2020s' => [TitleKind::Movie, 'primary_release_date', Era::TwentyTwenties, '2020-01-01', '2029-12-31'],
    'Movies 2010s' => [TitleKind::Movie, 'primary_release_date', Era::TwentyTens, '2010-01-01', '2019-12-31'],
    'Movies 2000s' => [TitleKind::Movie, 'primary_release_date', Era::TwoThousands, '2000-01-01', '2009-12-31'],
    'Movies 1990s' => [TitleKind::Movie, 'primary_release_date', Era::NineteenNineties, '1990-01-01', '1999-12-31'],
    'Movies Older' => [TitleKind::Movie, 'primary_release_date', Era::Older, null, '1989-12-31'],
    'TV Shows 2020s' => [TitleKind::TvShow, 'first_air_date', Era::TwentyTwenties, '2020-01-01', '2029-12-31'],
    'TV Shows 2010s' => [TitleKind::TvShow, 'first_air_date', Era::TwentyTens, '2010-01-01', '2019-12-31'],
    'TV Shows 2000s' => [TitleKind::TvShow, 'first_air_date', Era::TwoThousands, '2000-01-01', '2009-12-31'],
    'TV Shows 1990s' => [TitleKind::TvShow, 'first_air_date', Era::NineteenNineties, '1990-01-01', '1999-12-31'],
    'TV Shows Older' => [TitleKind::TvShow, 'first_air_date', Era::Older, null, '1989-12-31'],
]);
