<?php

use App\Catalog\CacheKey;
use App\Catalog\CatalogContext;
use App\Catalog\CatalogProfile;
use App\Catalog\CatalogSource;
use App\Catalog\GuardedCatalogSource;
use App\Catalog\MovieDetails;
use App\Catalog\SourceMisconfigured;
use App\Catalog\SourceUnavailable;

/**
 * An inner source that counts calls and fails while told to.
 */
function countingSource(): object
{
    return new class implements CatalogSource
    {
        public int $calls = 0;

        public bool $failing = false;

        public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
        {
            $this->calls++;

            return $this->failing ? throw new SourceUnavailable('down') : new MovieDetails(tmdbId: $tmdbId);
        }
    };
}

function failOnce(GuardedCatalogSource $guarded): void
{
    try {
        $guarded->movie(1, new CatalogContext);
    } catch (SourceUnavailable) {
    }
}

test('five consecutive interactive failures open the breaker and later calls fail without reaching the source', function () {
    $inner = countingSource();
    $inner->failing = true;
    $guarded = new GuardedCatalogSource($inner, new CacheKey);

    foreach (range(1, 5) as $ignored) {
        failOnce($guarded);
    }
    $inner->failing = false;

    expect(fn () => $guarded->movie(1, new CatalogContext))->toThrow(SourceUnavailable::class)
        ->and($inner->calls)->toBe(5);
});

test('a success resets the consecutive failure count', function () {
    $inner = countingSource();
    $guarded = new GuardedCatalogSource($inner, new CacheKey);

    $inner->failing = true;
    foreach (range(1, 4) as $ignored) {
        failOnce($guarded);
    }
    $inner->failing = false;
    $guarded->movie(1, new CatalogContext);
    $inner->failing = true;
    foreach (range(1, 4) as $ignored) {
        failOnce($guarded);
    }
    $inner->failing = false;

    expect($guarded->movie(1, new CatalogContext))->toBeInstanceOf(MovieDetails::class);
});

test('live calls share a 15 per second throttle and over it fail without waiting', function () {
    $inner = countingSource();
    $guarded = new GuardedCatalogSource($inner, new CacheKey);

    foreach (range(1, 15) as $ignored) {
        $guarded->movie(1, new CatalogContext);
    }

    expect(fn () => $guarded->movie(1, new CatalogContext))->toThrow(SourceUnavailable::class)
        ->and($inner->calls)->toBe(15);
});

test('background calls bypass the breaker and the live throttle', function () {
    $inner = countingSource();
    $inner->failing = true;
    $guarded = new GuardedCatalogSource($inner, new CacheKey);

    foreach (range(1, 5) as $ignored) {
        failOnce($guarded);
    }
    $inner->failing = false;

    expect($guarded->movie(1, new CatalogContext(profile: CatalogProfile::Background)))->toBeInstanceOf(MovieDetails::class);
});

test('an answer of not found does not count as a failure', function () {
    $inner = new class implements CatalogSource
    {
        public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
        {
            return null;
        }
    };
    $guarded = new GuardedCatalogSource($inner, new CacheKey);

    foreach (range(1, 6) as $ignored) {
        expect($guarded->movie(1, new CatalogContext))->toBeNull();
    }
});

test('a rejected token does not trip the breaker, so the configuration error keeps surfacing', function () {
    $inner = new class implements CatalogSource
    {
        public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
        {
            throw new SourceMisconfigured('rejected');
        }
    };
    $guarded = new GuardedCatalogSource($inner, new CacheKey);

    foreach (range(1, 7) as $ignored) {
        expect(fn () => $guarded->movie(1, new CatalogContext))->toThrow(SourceMisconfigured::class);
    }
});
