<?php

use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Enums\SectionKind;
use App\Services\Catalog\Sources\MovieListing;
use App\Services\Catalog\Sources\SourceMisconfigured;
use App\Services\Catalog\Sources\SourceUnavailable;
use App\Services\Catalog\Sources\TmdbCatalogSource;
use Illuminate\Http\Client\Request;
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
        ->and($movie->cast)->toHaveCount(12)
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

    $page = app(TmdbCatalogSource::class)->section(SectionKind::Trending, 2, new CatalogContext('en-US', 'US'));

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

    expect(app(TmdbCatalogSource::class)->section($kind, 1, new CatalogContext)->listings)->toBe([]);
    Http::assertSentCount(1);
})->with([
    'Trending' => [SectionKind::Trending, 'trending/movie/week'],
    'Popular' => [SectionKind::Popular, 'movie/popular'],
    'Top rated' => [SectionKind::TopRated, 'movie/top_rated'],
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

    $page = app(TmdbCatalogSource::class)->section(SectionKind::Popular, 1, new CatalogContext);

    expect($page->totalPages)->toBe(3)
        ->and(array_map(fn (MovieListing $listing): int => $listing->tmdbId, $page->listings))->toBe([1])
        ->and($page->listings[0]->posterPath)->toBe('/kept.jpg')
        ->and($page->listings[0]->backdropPath)->toBeNull()
        ->and($page->listings[0]->releaseDate)->toBeNull();
});

test('a section fails explicitly on unsuccessful or malformed responses', function (array $body, int $status) {
    Http::fake(['https://api.themoviedb.org/3/movie/top_rated?*' => Http::response($body, $status)]);

    expect(fn () => app(TmdbCatalogSource::class)->section(SectionKind::TopRated, 1, new CatalogContext))->toThrow(SourceUnavailable::class);
})->with([
    'not found' => [[], 404],
    'server error' => [[], 500],
    'no results' => [['page' => 1, 'total_pages' => 1], 200],
    'no page count' => [['page' => 1, 'results' => []], 200],
]);

test('a section raises a rejected token as a configuration error', function () {
    Http::fake(['https://api.themoviedb.org/3/movie/popular?*' => Http::response([], 401)]);

    expect(fn () => app(TmdbCatalogSource::class)->section(SectionKind::Popular, 1, new CatalogContext))->toThrow(SourceMisconfigured::class);
});
