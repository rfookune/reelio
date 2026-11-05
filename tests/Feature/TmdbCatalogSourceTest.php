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
