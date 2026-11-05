<?php

use App\Catalog\CatalogContext;
use App\Catalog\SourceUnavailable;
use App\Catalog\TmdbCatalogSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

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
