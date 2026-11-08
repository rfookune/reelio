<?php

use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\SourceMisconfigured;
use App\Services\Catalog\Sources\SourceUnavailable;
use App\Services\Catalog\Sources\Tmdb\TmdbCatalogSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/** @return array<string, mixed> */
function tvFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/tmdb/{$name}.json")), true, flags: JSON_THROW_ON_ERROR);
}

function fakeTvFixture(int $id): void
{
    Http::preventStrayRequests();
    $details = tvFixture("tv_{$id}");
    $responses = ["https://api.themoviedb.org/3/tv/{$id}?*" => Http::response($details)];
    foreach ($details['seasons'] as $season) {
        $number = $season['season_number'];
        $responses["https://api.themoviedb.org/3/tv/{$id}/season/{$number}?*"] = Http::response(tvFixture("tv_{$id}_season_{$number}"));
    }
    Http::fake($responses);
}

test('returns an ended TV Show with neutral metadata and every Episode', function () {
    config(['catalog.tmdb.token' => 'tv-token']);
    fakeTvFixture(87108);

    $tvShow = app(TmdbCatalogSource::class)->tvShow(87108, new CatalogContext('en-US'));

    expect($tvShow)->toMatchArray([
        'tmdbId' => 87108, 'title' => 'Chernobyl', 'originalTitle' => 'Chernobyl',
        'firstAirDate' => '2019-05-06', 'lastAirDate' => '2019-06-03',
        'status' => 'Ended', 'imdbId' => 'tt7366338', 'certification' => 'TV-MA',
        'creators' => ['Craig Mazin'], 'networks' => ['HBO'],
    ]);
    expect($tvShow->cast[0])->toBe(['id' => 15440, 'name' => 'Jared Harris', 'character' => 'Valery Legasov', 'profilePath' => '/r9nIq0jPz1oBm4WwtlZhqlsX8p0.jpg', 'episodeCount' => 5]);
    expect($tvShow->crew)->not->toBeEmpty()->each->toHaveKeys(['id', 'name', 'job']);
    expect(collect($tvShow->crew)->pluck('job')->unique()->diff(MovieDetails::CREW_JOBS))->toBeEmpty();
    expect($tvShow->seasons)->toHaveCount(1);
    expect($tvShow->seasons[0])->toMatchArray(['tmdbId' => 119454, 'number' => 1, 'episodeCount' => 5]);
    expect($tvShow->seasons[0]->episodes)->toHaveCount(5);
    expect($tvShow->seasons[0]->episodes[0])->toMatchArray([
        'tmdbId' => 1725580, 'number' => 1, 'seasonNumber' => 1, 'name' => '1:23:45',
        'airDate' => '2019-05-06', 'runtime' => 61, 'episodeType' => 'standard',
    ]);
    expect(get_object_vars($tvShow->seasons[0]->episodes[0]))->not->toHaveKeys(['still_path', 'stillPath']);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer tv-token')
        && $request['language'] === 'en-US'
        && ($request['append_to_response'] ?? null) === 'external_ids,content_ratings,videos,aggregate_credits,watch/providers');
});

test('keeps Specials and upcoming Episodes and translates TV Availability', function () {
    fakeTvFixture(153312);

    $tvShow = app(TmdbCatalogSource::class)->tvShow(153312, new CatalogContext('en-US'));

    expect($tvShow->status)->toBe('Returning Series');
    expect($tvShow->seasons)->toHaveCount(5);
    expect($tvShow->seasons[0])->toMatchArray(['number' => 0, 'name' => 'Specials']);
    expect($tvShow->seasons[4]->episodes[4])->toMatchArray(['airDate' => '2026-11-13', 'runtime' => null]);
    expect($tvShow->seasons[4]->episodes[9]->episodeType)->toBe('finale');
    expect(collect($tvShow->availability)->where('country', 'US')->where('offerType', 'free')->pluck('serviceName')->all())->toContain('Paramount Plus Essential');
    expect(collect($tvShow->availability)->where('country', 'NZ')->where('offerType', 'free')->pluck('serviceName')->all())->toContain('TVNZ+');
    Http::assertSentCount(6);
});

test('keeps an announced undated Season with no Episodes', function () {
    Http::preventStrayRequests();
    $details = tvFixture('tv_95396');
    $details['seasons'] = array_values(array_filter($details['seasons'], fn (array $season) => $season['season_number'] === 3));
    $details['number_of_seasons'] = 1;
    Http::fake([
        'https://api.themoviedb.org/3/tv/95396?*' => Http::response($details),
        'https://api.themoviedb.org/3/tv/95396/season/3?*' => Http::response(tvFixture('tv_95396_season_3')),
    ]);

    $tvShow = app(TmdbCatalogSource::class)->tvShow(95396, new CatalogContext('en-US'));

    expect($tvShow->seasons[0])->toMatchArray(['number' => 3, 'airDate' => null, 'episodes' => []]);
    Http::assertSentCount(2);
});

test('keeps undated Episodes without inventing an air date', function () {
    Http::preventStrayRequests();
    $season = tvFixture('tv_87108_season_1');
    $season['episodes'][0]['air_date'] = null;
    $season['episodes'][1]['air_date'] = '';
    Http::fake([
        'https://api.themoviedb.org/3/tv/87108?*' => Http::response(tvFixture('tv_87108')),
        'https://api.themoviedb.org/3/tv/87108/season/1?*' => Http::response($season),
    ]);

    $tvShow = app(TmdbCatalogSource::class)->tvShow(87108, new CatalogContext('en-US'));

    expect($tvShow->seasons[0]->episodes[0]->airDate)->toBeNull();
    expect($tvShow->seasons[0]->episodes[1]->airDate)->toBeNull();
});

test('reports a details 404 as a removed TV Show', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.themoviedb.org/3/tv/87108?*' => Http::response([], 404)]);

    expect(app(TmdbCatalogSource::class)->tvShow(87108, new CatalogContext('en-US')))->toBeNull();
    Http::assertSentCount(1);
});

test('rejects the entire fetch when a Season fails and writes nothing', function (string $failure) {
    Http::preventStrayRequests();
    $season = tvFixture('tv_87108_season_1');
    $season['episodes'] = [];
    $response = match ($failure) {
        'connection' => Http::failedConnection(),
        'malformed' => Http::response(['id' => 119454]),
        'partial' => Http::response($season),
        default => Http::response([], (int) $failure),
    };
    Http::fake([
        'https://api.themoviedb.org/3/tv/87108?*' => Http::response(tvFixture('tv_87108')),
        'https://api.themoviedb.org/3/tv/87108/season/1?*' => $response,
    ]);

    expect(fn () => app(TmdbCatalogSource::class)->tvShow(87108, new CatalogContext('en-US')))
        ->toThrow(SourceUnavailable::class);
    $this->assertDatabaseCount('titles', 0);
    Http::assertSentCount($failure === 'connection' ? 1 : 2);
})->with(['404', '500', 'connection', 'malformed', 'partial']);

test('rejects one failed Season among successful Season responses', function () {
    Http::preventStrayRequests();
    $details = tvFixture('tv_153312');
    $responses = ['https://api.themoviedb.org/3/tv/153312?*' => Http::response($details)];
    foreach ($details['seasons'] as $summary) {
        $number = $summary['season_number'];
        $responses["https://api.themoviedb.org/3/tv/153312/season/{$number}?*"] = $number === 2
            ? Http::response([], 500)
            : Http::response(tvFixture("tv_153312_season_{$number}"));
    }
    Http::fake($responses);

    expect(fn () => app(TmdbCatalogSource::class)->tvShow(153312, new CatalogContext('en-US')))->toThrow(SourceUnavailable::class);
    $this->assertDatabaseCount('titles', 0);
    $this->assertDatabaseCount('seasons', 0);
    $this->assertDatabaseCount('episodes', 0);
    Http::assertSentCount(6);
});

test('rejects unsuccessful or malformed TV Show details before fetching Seasons', function (string $failure) {
    Http::preventStrayRequests();
    Http::fake(['https://api.themoviedb.org/3/tv/87108?*' => match ($failure) {
        'connection' => Http::failedConnection(),
        'malformed' => Http::response(['id' => 87108]),
        default => Http::response([], (int) $failure),
    }]);

    expect(fn () => app(TmdbCatalogSource::class)->tvShow(87108, new CatalogContext('en-US')))
        ->toThrow($failure === '401' ? SourceMisconfigured::class : SourceUnavailable::class);
})->with(['401', '500', 'connection', 'malformed']);

test('shares the interactive timeout between details and pooled Season calls', function () {
    Http::preventStrayRequests();
    $timeouts = [];
    Http::fake([
        'https://api.themoviedb.org/3/tv/87108?*' => function (Request $request, array $options) use (&$timeouts) {
            $timeouts[] = $options['timeout'];

            return Http::response(tvFixture('tv_87108'));
        },
        'https://api.themoviedb.org/3/tv/87108/season/1?*' => function (Request $request, array $options) use (&$timeouts) {
            $timeouts[] = $options['timeout'];

            return Http::response(tvFixture('tv_87108_season_1'));
        },
    ]);

    app(TmdbCatalogSource::class)->tvShow(87108, new CatalogContext('en-US'));

    expect($timeouts)->toHaveCount(2);
    expect($timeouts[0])->toBeLessThanOrEqual(3)->toBeGreaterThan(0);
    expect($timeouts[1])->toBeLessThan($timeouts[0])->toBeGreaterThan(0);
});

test('rejects missing or failed appended resources instead of returning partial metadata', function (string $resource, bool $failed) {
    Http::preventStrayRequests();
    $details = tvFixture('tv_87108');
    if ($failed) {
        $details[$resource] = ['success' => false, 'status_code' => 11, 'status_message' => 'Internal error'];
    } else {
        unset($details[$resource]);
    }
    Http::fake([
        'https://api.themoviedb.org/3/tv/87108?*' => Http::response($details),
        'https://api.themoviedb.org/3/tv/87108/season/1?*' => Http::response(tvFixture('tv_87108_season_1')),
    ]);

    expect(fn () => app(TmdbCatalogSource::class)->tvShow(87108, new CatalogContext('en-US')))->toThrow(SourceUnavailable::class);
    Http::assertSentCount(1);
})->with(['external_ids', 'content_ratings', 'videos', 'aggregate_credits', 'watch/providers'])->with([false, true]);

test('rejects an incomplete numbered Season inventory', function () {
    Http::preventStrayRequests();
    $details = tvFixture('tv_87108');
    $details['seasons'] = [];
    Http::fake(['https://api.themoviedb.org/3/tv/87108?*' => Http::response($details)]);

    expect(fn () => app(TmdbCatalogSource::class)->tvShow(87108, new CatalogContext('en-US')))->toThrow(SourceUnavailable::class);
    Http::assertSentCount(1);
});

test('background TV retries throttle every attempt including a pooled Season retry', function () {
    config(['catalog.guard.background_requests_per_second' => 2]);
    Sleep::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.themoviedb.org/3/tv/87108?*' => Http::response(tvFixture('tv_87108')),
        'https://api.themoviedb.org/3/tv/87108/season/1?*' => Http::sequence()->push([], 500)->push(tvFixture('tv_87108_season_1')),
    ]);
    $context = new CatalogContext('en-US', profile: CatalogProfile::Background);

    expect(fn () => app(TmdbCatalogSource::class)->tvShow(87108, $context))->toThrow(SourceUnavailable::class);
    Http::assertSentCount(2);
    Sleep::assertSlept(fn ($duration) => $duration->totalMilliseconds === 100.0, 30);
});

test('background TV fetch recovers from a transient pooled Season failure', function () {
    config(['catalog.guard.background_requests_per_second' => 3]);
    Sleep::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.themoviedb.org/3/tv/87108?*' => Http::response(tvFixture('tv_87108')),
        'https://api.themoviedb.org/3/tv/87108/season/1?*' => Http::sequence()->push([], 500)->push(tvFixture('tv_87108_season_1')),
    ]);

    $tvShow = app(TmdbCatalogSource::class)->tvShow(87108, new CatalogContext('en-US', profile: CatalogProfile::Background));

    expect($tvShow->seasons[0]->episodes)->toHaveCount(5);
    Http::assertSentCount(3);
});

test('a series-wide cast entry carries every role the actor played and the total Episode count', function () {
    Http::preventStrayRequests();
    $details = tvFixture('tv_87108');
    $details['aggregate_credits'] = ['cast' => [
        ['id' => 2, 'name' => 'Second Billed', 'profile_path' => null, 'order' => 1, 'total_episode_count' => 1, 'roles' => [['character' => 'Guard', 'episode_count' => 1]]],
        ['id' => 1, 'name' => 'First Billed', 'profile_path' => null, 'order' => 0, 'total_episode_count' => 42, 'roles' => [
            ['character' => 'Dr. Smith', 'episode_count' => 40], ['character' => '', 'episode_count' => 1], ['character' => 'Twin', 'episode_count' => 2],
        ]],
    ], 'crew' => [
        ['id' => 9, 'name' => 'Pat Producer', 'profile_path' => null, 'total_episode_count' => 9, 'jobs' => [['job' => 'Executive Producer', 'episode_count' => 5], ['job' => 'Gaffer', 'episode_count' => 4], ['job' => 'Director', 'episode_count' => 1]]],
    ]];
    Http::fake([
        'https://api.themoviedb.org/3/tv/87108?*' => Http::response($details),
        'https://api.themoviedb.org/3/tv/87108/season/1?*' => Http::response(tvFixture('tv_87108_season_1')),
    ]);

    $tvShow = app(TmdbCatalogSource::class)->tvShow(87108, new CatalogContext('en-US'));

    expect($tvShow->cast)->toBe([
        ['id' => 1, 'name' => 'First Billed', 'character' => 'Dr. Smith, Twin', 'profilePath' => null, 'episodeCount' => 42],
        ['id' => 2, 'name' => 'Second Billed', 'character' => 'Guard', 'profilePath' => null, 'episodeCount' => 1],
    ]);
    expect($tvShow->crew)->toBe([
        ['id' => 9, 'name' => 'Pat Producer', 'job' => 'Director'],
        ['id' => 9, 'name' => 'Pat Producer', 'job' => 'Executive Producer'],
    ]);
});
