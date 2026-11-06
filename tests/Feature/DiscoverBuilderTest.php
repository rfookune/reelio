<?php

use App\Services\Catalog\Catalog;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Discover;
use App\Services\Catalog\DiscoverBuilder;
use App\Services\Catalog\DiscoverMovie;
use App\Services\Catalog\DiscoverSection;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Enums\SectionKind;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\MovieDetails;
use App\Services\Catalog\Sources\SectionPage;
use App\Services\Catalog\Sources\SourceMisconfigured;
use App\Services\Catalog\Sources\SourceUnavailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;

/** @return array<string, list<int>> each section's catalog ids, keyed by kind */
function sectionIds(Discover $discover): array
{
    return collect($discover->sections)->mapWithKeys(fn (DiscoverSection $section): array => [$section->kind->value => array_map(fn (DiscoverMovie $movie): int => $movie->tmdbId, $section->movies)])->all();
}

test('the daily build assembles the hero and ten Movies per section, each Movie only in its earliest section', function () {
    fakeDiscoverSource([
        'trending' => [movieListings(1, 12)],
        'popular' => [[...movieListings(5, 10), ...movieListings(20, 30)]],
        'top_rated' => [[...movieListings(1, 3), ...movieListings(25, 40)]],
    ], [new MovieDetails(tmdbId: 1, title: 'Movie 1', runtime: 120, imdbId: 'tt0000001', genres: [['id' => 878, 'name' => 'Science Fiction']], trailer: ['key' => 'abcdefghijk', 'name' => 'Trailer', 'type' => 'Trailer'])]);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);
    $discover = app(Catalog::class)->discover();

    expect(sectionIds($discover))->toBe([
        'trending' => range(1, 10),
        'popular' => range(20, 29),
        'top_rated' => range(30, 39),
    ])
        ->and($discover->hero->toArray())->toMatchArray([
            'tmdbId' => 1,
            'title' => 'Movie 1',
            'runtime' => 120,
            'imdbId' => 'tt0000001',
            'genres' => ['Science Fiction'],
            'overview' => 'About movie 1.',
            'backdropPath' => '/backdrop1.jpg',
            'trailer' => ['key' => 'abcdefghijk', 'name' => 'Trailer', 'type' => 'Trailer'],
        ]);
});

test('a section leaves out Movies without a poster and fills up from its next page', function () {
    $source = fakeDiscoverSource([
        'trending' => [
            [...movieListings(1, 8), movieListing(9, posterPath: null), movieListing(10, posterPath: null)],
            movieListings(11, 30),
        ],
    ]);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(sectionIds(app(Catalog::class)->discover())['trending'])->toBe([...range(1, 8), 11, 12])
        ->and(collect($source->sectionCalls)->where('kind.value', 'trending')->pluck('page')->all())->toBe([1, 2]);
});

test('a section reads no further than its configured pages, its last page or TMDB\'s 500-page cap', function (int $configuredPages, int $sourcePages, int $lastPageRead) {
    config(['catalog.discover.max_pages_per_section' => $configuredPages]);
    $source = fakeDiscoverSource(['popular' => array_fill(0, $sourcePages, [])]);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(collect($source->sectionCalls)->where('kind.value', 'popular')->max('page'))->toBe($lastPageRead);
})->with([
    'configured pages' => [3, 10, 3],
    'the list\'s last page' => [3, 2, 2],
    'TMDB\'s cap' => [600, 501, 500],
]);

test('the hero is the highest-ranked Trending Movie with a backdrop and an overview', function () {
    fakeDiscoverSource(['trending' => [[movieListing(1, backdropPath: null), movieListing(2, overview: null), movieListing(3), movieListing(4)]]]);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(app(Catalog::class)->discover()->hero->tmdbId)->toBe(3);
});

test('a section that fails keeps its previous version for up to seven days, without the Movies an earlier section now has', function () {
    $this->travelTo('2026-11-01 05:00:00');
    fakeDiscoverSource(['trending' => [movieListings(1, 10)], 'popular' => [movieListings(11, 20)], 'top_rated' => [movieListings(21, 30)]]);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    $this->travelTo('2026-11-08 05:00:00');
    fakeDiscoverSource(['trending' => [movieListings(15, 24)], 'top_rated' => [movieListings(31, 40)]], failingSections: [SectionKind::Popular]);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(sectionIds(app(Catalog::class)->discover()))->toBe(['trending' => range(15, 24), 'popular' => range(11, 14), 'top_rated' => range(31, 40)]);

    $this->travel(1)->second();

    expect(sectionIds(app(Catalog::class)->discover()))->toBe(['trending' => range(15, 24), 'top_rated' => range(31, 40)]);
});

test('a failed Trending keeps its previous hero for up to seven days, then hides it', function () {
    $this->travelTo('2026-11-01 05:00:00');
    fakeDiscoverSource(['trending' => [movieListings(1, 10)], 'popular' => [movieListings(11, 20)]]);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    $this->travelTo('2026-11-02 05:00:00');
    fakeDiscoverSource(['popular' => [movieListings(11, 20)]], failingSections: [SectionKind::Trending]);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(app(Catalog::class)->discover()->hero->tmdbId)->toBe(1)
        ->and(sectionIds(app(Catalog::class)->discover())['trending'])->toBe(range(1, 10));

    $this->travelTo('2026-11-08 05:00:01');
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(app(Catalog::class)->discover()->hero)->toBeNull()
        ->and(sectionIds(app(Catalog::class)->discover()))->toBe(['popular' => range(11, 20)]);
});

test('with every section failing and nothing earlier, Discover has nothing to show', function () {
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $discover = app(Catalog::class)->discover();

    expect($discover->hero)->toBeNull()->and($discover->isEmpty())->toBeTrue();
});

test('a visitor only reads a payload that has something to show, without asking the source', function () {
    fakeDiscoverSource(['trending' => [movieListings(1, 10)]]);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    expect(sectionIds(app(Catalog::class)->discover())['trending'])->toBe(range(1, 10))
        ->and(app(Catalog::class)->discover()->detailed)->toBeTrue();
});

test('the first visitor builds a missing payload, asking for the hero\'s details only', function () {
    fakeDiscoverSource(['trending' => [movieListings(1, 10)]], [new MovieDetails(tmdbId: 1, runtime: 120), new MovieDetails(tmdbId: 2, runtime: 95)]);

    $discover = app(Catalog::class)->discover();

    expect($discover->detailed)->toBeFalse()
        ->and($discover->hero->runtime)->toBe(120)
        ->and($discover->sections[0]->movies[1]->toArray())->toMatchArray(['tmdbId' => 2, 'runtime' => null, 'imdbId' => null]);
});

test('every listed Movie\'s TMDB score is recorded, with or without a Movie row', function () {
    fakeDiscoverSource(['trending' => [[movieListing(1, voteAverage: 8.4, voteCount: 2500), movieListing(2, posterPath: null, voteAverage: 6.1, voteCount: 40), movieListing(3, voteCount: 0)]]]);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    $this->assertDatabaseHas('ratings', ['source' => 'tmdb', 'source_id' => '1', 'value' => 8.4, 'vote_count' => 2500]);
    $this->assertDatabaseHas('ratings', ['source' => 'tmdb', 'source_id' => '2', 'value' => 6.1, 'vote_count' => 40]);
    $this->assertDatabaseMissing('ratings', ['source' => 'tmdb', 'source_id' => '3']);
    $this->assertDatabaseCount('movies', 0);
});

test('a Movie whose details fail keeps its listing, and one the source no longer has is left out', function () {
    $source = fakeDiscoverSource(['trending' => [movieListings(1, 3)]], unknown: [2]);
    $source->movies = array_values(array_filter($source->movies, fn (MovieDetails $details): bool => $details->tmdbId !== 3));
    $failing = new class($source->movies, sections: $source->sections) extends FakeCatalogSource
    {
        public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
        {
            return $tmdbId === 3 ? throw new SourceUnavailable('down') : parent::movie($tmdbId, $context);
        }
    };
    app()->instance(CatalogSource::class, $failing);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(sectionIds(app(Catalog::class)->discover())['trending'])->toBe([1, 3])
        ->and(app(Catalog::class)->discover()->sections[0]->movies[1]->title)->toBe('Movie 3');
});

test('the hero falls to the next candidate when the source no longer has the first', function () {
    fakeDiscoverSource(['trending' => [movieListings(1, 10)]], unknown: [1]);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(app(Catalog::class)->discover()->hero->tmdbId)->toBe(2);
});

test('a Movie whose details fail keeps the previous build\'s details', function () {
    fakeDiscoverSource(['trending' => [movieListings(1, 10)]], [new MovieDetails(tmdbId: 4, runtime: 101, imdbId: 'tt0000004')]);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    $source = fakeDiscoverSource(['trending' => [[movieListing(4, title: 'Renamed'), ...movieListings(5, 13)]]]);
    $failingDetails = new class($source->movies, sections: $source->sections) extends FakeCatalogSource
    {
        public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
        {
            throw new SourceUnavailable('down');
        }
    };
    app()->instance(CatalogSource::class, $failingDetails);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(app(Catalog::class)->discover()->sections[0]->movies[0]->toArray())->toMatchArray(['tmdbId' => 4, 'title' => 'Renamed', 'runtime' => 101, 'imdbId' => 'tt0000004']);
});

test('a build stops asking the source once its time budget is spent, keeping what it has', function () {
    config(['catalog.discover.build_budget_seconds.background' => 45]);
    $this->freezeTime();
    $slow = new class(sections: ['trending' => [movieListings(1, 10)], 'popular' => [movieListings(11, 20)]]) extends FakeCatalogSource
    {
        public int $detailCalls = 0;

        public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
        {
            $this->detailCalls++;
            test()->travel(20)->seconds();

            return new MovieDetails(tmdbId: $tmdbId, runtime: 90);
        }
    };
    app()->instance(CatalogSource::class, $slow);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    $discover = app(Catalog::class)->discover();
    expect($slow->detailCalls)->toBe(3)
        ->and(array_map(fn (DiscoverMovie $movie): ?int => $movie->runtime, $discover->sections[0]->movies))->toBe([90, 90, 90, null, null, null, null, null, null, null])
        ->and(collect($slow->sectionCalls)->pluck('kind.value')->all())->toBe(['trending'])
        ->and(sectionIds($discover))->toBe(['trending' => range(1, 10)]);
});

test('a rejected token is reported to the owner, and the build keeps what it can', function () {
    Exceptions::fake();
    $rejecting = new class(sections: ['popular' => [movieListings(11, 20)]]) extends FakeCatalogSource
    {
        public function section(SectionKind $kind, int $page, CatalogContext $context): SectionPage
        {
            return $kind === SectionKind::Trending ? throw new SourceMisconfigured('rejected') : parent::section($kind, $page, $context);
        }
    };
    app()->instance(CatalogSource::class, $rejecting);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    Exceptions::assertReported(SourceMisconfigured::class);
    expect(array_keys(sectionIds(app(Catalog::class)->discover())))->toBe(['popular']);
});

test('a payload that is no longer readable is rebuilt by the first visitor', function () {
    fakeDiscoverSource(['trending' => [movieListings(1, 10)]]);
    Cache::put(app(DiscoverBuilder::class)->key(new CatalogContext(config('catalog.language'))), ['sections' => [['kind' => 'unknown'], ['kind' => 'trending', 'movies' => []], ['kind' => 'popular', 'builtAt' => now()->getTimestamp(), 'movies' => [['tmdbId' => 'x']]]], 'hero' => ['builtAt' => 'yesterday']]);

    expect(sectionIds(app(Catalog::class)->discover()))->toBe(['trending' => range(1, 10)]);
});

test('the hero\'s details are asked for once, for the hero and its Trending row', function () {
    $counting = new class(sections: ['trending' => [movieListings(1, 3)], 'popular' => [movieListings(4, 6)]]) extends FakeCatalogSource
    {
        /** @var list<int> */
        public array $detailCalls = [];

        public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
        {
            $this->detailCalls[] = $tmdbId;

            return new MovieDetails(tmdbId: $tmdbId, runtime: 100);
        }
    };
    app()->instance(CatalogSource::class, $counting);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect($counting->detailCalls)->toBe([1, 2, 3, 4, 5, 6]);
});

test('a Trending list with no hero candidate still builds its row', function () {
    fakeDiscoverSource(['trending' => [[movieListing(1, backdropPath: null), movieListing(2, overview: null)]]]);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(app(Catalog::class)->discover()->hero)->toBeNull()
        ->and(sectionIds(app(Catalog::class)->discover()))->toBe(['trending' => [1, 2]]);
});
