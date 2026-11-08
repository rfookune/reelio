<?php

use App\Enums\TitleKind;
use App\Models\Genre;
use App\Models\Movie;
use App\Services\Catalog\CacheKey;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Maintenance\CatalogCleanup;
use App\Services\Catalog\Search\ImdbIdLookup;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

/** Binds a fake source that knows the given IMDb ids, and returns it. */
function fakeImdbIds(array $imdbIds, bool $failing = false): FakeCatalogSource
{
    $source = new FakeCatalogSource(failing: $failing, imdbIds: $imdbIds);
    app()->instance(CatalogSource::class, $source);

    return $source;
}

test('a lookup asks the source only for the ids it has not cached, and remembers the answers, none included', function () {
    $source = fakeImdbIds([1 => 'tt0000001', 2 => null]);

    expect(app(ImdbIdLookup::class)->lookUpImdbIds([1, 2]))->toBe([1 => 'tt0000001']);
    expect(app(ImdbIdLookup::class)->lookUpImdbIds([1, 2, 3]))->toBe([1 => 'tt0000001'])
        ->and(array_column($source->imdbIdCalls, 'tmdbId'))->toBe([1, 2, 3])
        ->and(app(ImdbIdLookup::class)->cachedImdbIds([1, 2, 3]))->toBe([1 => 'tt0000001']);
});

test('a failed lookup is not remembered, and stops the ones after it', function () {
    $source = fakeImdbIds([1 => 'tt0000001', 2 => 'tt0000002'], failing: true);

    expect(app(ImdbIdLookup::class)->lookUpImdbIds([1, 2]))->toBe([])
        ->and($source->imdbIdCalls)->toHaveCount(1);

    fakeImdbIds([1 => 'tt0000001', 2 => 'tt0000002']);

    expect(app(ImdbIdLookup::class)->lookUpImdbIds([1, 2]))->toBe([1 => 'tt0000001', 2 => 'tt0000002']);
});

test('a found id is remembered for 30 days and a missing one for a day', function () {
    fakeImdbIds([1 => 'tt0000001', 2 => null]);
    app(ImdbIdLookup::class)->lookUpImdbIds([1, 2]);

    /** Redis expires keys on the real clock, which `travelTo()` can't move, so the lifetimes are read from Redis. */
    $secondsLeft = function (int $tmdbId): int {
        $key = app(CacheKey::class)->build('imdb-id', ['generation' => 0, 'tmdb_id' => $tmdbId], new CatalogContext(config('catalog.language')));

        return Cache::getStore()->connection()->ttl(Cache::getStore()->getPrefix().$key);
    };

    expect($secondsLeft(1))->toBeBetween(30 * 86400 - 10, 30 * 86400)
        ->and($secondsLeft(2))->toBeBetween(86400 - 10, 86400);
});

test('a request looks up at most the configured number of ids', function () {
    config(['catalog.imdb_lookup.max_per_request' => 2]);
    $source = fakeImdbIds([1 => 'tt1', 2 => 'tt2', 3 => 'tt3']);

    expect(app(ImdbIdLookup::class)->lookUpImdbIds([1, 2, 3]))->toBe([1 => 'tt1', 2 => 'tt2'])
        ->and($source->imdbIdCalls)->toHaveCount(2);
});

test('lookups share their own per-second ceiling, below the live one, and wait for the next second within the budget', function () {
    config(['catalog.imdb_lookup.requests_per_second' => 2]);
    Sleep::fake();
    $throttle = app(CacheKey::class)->build('imdb-lookup-throttle', [], new CatalogContext(config('catalog.language')));
    Sleep::whenFakingSleep(fn () => RateLimiter::clear($throttle));
    $source = fakeImdbIds([1 => 'tt1', 2 => 'tt2', 3 => 'tt3']);

    expect(app(ImdbIdLookup::class)->lookUpImdbIds([1, 2, 3]))->toBe([1 => 'tt1', 2 => 'tt2', 3 => 'tt3'])
        ->and($source->imdbIdCalls)->toHaveCount(3);
    Sleep::assertSleptTimes(1);
});

test('a lookup still over the ceiling when the budget is spent is skipped, with the ones after it', function () {
    config(['catalog.imdb_lookup.requests_per_second' => 1, 'catalog.imdb_lookup.budget_seconds' => 1]);
    Sleep::fake();
    $source = fakeImdbIds([1 => 'tt1', 2 => 'tt2', 3 => 'tt3']);

    expect(app(ImdbIdLookup::class)->lookUpImdbIds([1, 2, 3]))->toBe([1 => 'tt1'])
        ->and($source->imdbIdCalls)->toHaveCount(1);
    Sleep::assertSleptTimes(10);
});

test('a request stops looking up once its time budget is spent', function () {
    config(['catalog.imdb_lookup.budget_seconds' => 6]);
    $this->travelTo('2026-11-07 12:00:00');
    $source = new class extends FakeCatalogSource
    {
        public function imdbId(int $tmdbId, CatalogContext $context): ?string
        {
            CarbonImmutable::setTestNow(now()->addSeconds(4));

            return parent::imdbId($tmdbId, $context);
        }
    };
    app()->instance(CatalogSource::class, $source);

    app(ImdbIdLookup::class)->lookUpImdbIds([1, 2, 3, 4]);

    expect($source->imdbIdCalls)->toHaveCount(2);
});

test('a refresh remembers the Movie\'s IMDb id, or that it has none, with no call of its own', function () {
    $withId = Movie::factory()->create(['tmdb_id' => 1]);
    $without = Movie::factory()->create(['tmdb_id' => 2]);
    $source = fakeCatalogKnowing(new MovieDetails(tmdbId: 1, title: 'One', imdbId: 'tt0000001'), new MovieDetails(tmdbId: 2, title: 'Two'));

    app(Catalog::class)->refresh($withId, new CatalogContext);
    app(Catalog::class)->refresh($without, new CatalogContext);

    expect(app(ImdbIdLookup::class)->lookUpImdbIds([1, 2]))->toBe([1 => 'tt0000001'])
        ->and($source->imdbIdCalls)->toBe([]);
});

test('creating a Movie from its catalog id remembers its IMDb id too', function () {
    fakeCatalogKnowing(new MovieDetails(tmdbId: 1, title: 'One', imdbId: 'tt0000001'));

    app(Catalog::class)->findOrCreate(1, 'test');

    expect(app(ImdbIdLookup::class)->cachedImdbIds([1]))->toBe([1 => 'tt0000001']);
});

test('a Movie that is removed, or cleared at the cap, has its remembered IMDb id forgotten', function (string $how) {
    $movie = Movie::factory()->create(['tmdb_id' => 1, 'synced_at' => now()->subDays(171)]);
    $movie->genres()->attach(Genre::factory()->create());
    fakeCatalogKnowing(new MovieDetails(tmdbId: 1, title: 'One', imdbId: 'tt0000001'));
    app(Catalog::class)->refresh($movie, new CatalogContext);
    expect(app(ImdbIdLookup::class)->cachedImdbIds([1]))->toBe([1 => 'tt0000001']);

    if ($how === 'removal') {
        fakeCatalogKnowing();
        app(Catalog::class)->refresh($movie->fresh(), new CatalogContext);
    } else {
        $this->travelTo(now()->addDays(171));
        app(CatalogCleanup::class)->cleanUp();
    }

    expect(app(ImdbIdLookup::class)->cachedImdbIds([1]))->toBe([]);
})->with(['removal' => 'removal', 'the cap' => 'cap']);

test('an unviewed Movie\'s purge forgets its remembered IMDb id', function () {
    $movie = Movie::factory()->create(['tmdb_id' => 1, 'last_viewed_at' => now()->subDays(31)]);
    fakeCatalogKnowing(new MovieDetails(tmdbId: 1, title: 'One', imdbId: 'tt0000001'));
    app(Catalog::class)->refresh($movie, new CatalogContext);

    app(CatalogCleanup::class)->cleanUp();

    $this->assertModelMissing($movie);
    expect(app(ImdbIdLookup::class)->cachedImdbIds([1]))->toBe([]);
});

test('catalog:purge forgets every remembered IMDb id, Movie row or not', function () {
    fakeImdbIds([1 => 'tt0000001']);
    app(ImdbIdLookup::class)->lookUpImdbIds([1]);

    $this->artisan('catalog:purge --force')->assertSuccessful();

    expect(app(ImdbIdLookup::class)->cachedImdbIds([1]))->toBe([]);
});

test('the purge generation never expires, so a later purge can never make an earlier one\'s ids reachable again', function () {
    fakeImdbIds([1 => 'tt0000001']);
    $this->artisan('catalog:purge --force')->assertSuccessful();
    $key = app(CacheKey::class)->build('purge-generation', [], new CatalogContext(config('catalog.language')));

    expect((int) Cache::get($key))->toBe(1)
        ->and(Cache::getStore()->connection()->ttl(Cache::getStore()->getPrefix().$key))->toBe(-1);
});

test('TV Show ids are looked up and remembered apart from Movies that share their catalog id', function () {
    $source = new FakeCatalogSource(imdbIds: [1 => 'tt0000001'], tvImdbIds: [1 => 'tt0009999']);
    app()->instance(CatalogSource::class, $source);
    $lookup = app(ImdbIdLookup::class);

    expect($lookup->lookUpImdbIds([1], TitleKind::TvShow))->toBe([1 => 'tt0009999'])
        ->and($lookup->cachedImdbIds([1]))->toBe([])
        ->and($lookup->cachedImdbIds([1], TitleKind::TvShow))->toBe([1 => 'tt0009999'])
        ->and($lookup->lookUpImdbIds([1]))->toBe([1 => 'tt0000001'])
        ->and($lookup->lookUpImdbIds([1], TitleKind::TvShow))->toBe([1 => 'tt0009999'])
        ->and($source->tvImdbIdCalls)->toHaveCount(1)
        ->and($source->imdbIdCalls)->toHaveCount(1);
});
