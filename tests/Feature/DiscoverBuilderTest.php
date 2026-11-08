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
use App\Services\Catalog\Sources\MovieListing;
use App\Services\Catalog\Sources\SectionPage;
use App\Services\Catalog\Sources\SectionQuery;
use App\Services\Catalog\Sources\SourceMisconfigured;
use App\Services\Catalog\Sources\SourceUnavailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;

/** @return array<string, list<int>> each section's catalog ids, keyed by section */
function sectionIds(Discover $discover): array
{
    return collect($discover->sections)->mapWithKeys(fn (DiscoverSection $section): array => [$section->key() => array_map(fn (DiscoverMovie $movie): int => $movie->tmdbId, $section->movies)])->all();
}

/** @return list<string> the titles of the day's genre collections, in page order */
function genreTitles(Discover $discover): array
{
    return collect($discover->sections)->where('kind', SectionKind::Genre)->map(fn (DiscoverSection $section): string => $section->title())->values()->all();
}

/**
 * Installs a fake source whose detail calls fail for the given catalog ids.
 *
 * @param  array<string, list<list<MovieListing>>>  $sections
 * @param  list<MovieDetails>  $movies
 * @param  list<int>  $failing
 */
function installFailingDetails(array $sections, array $movies, array $failing): void
{
    app()->instance(CatalogSource::class, new class($movies, sections: $sections, failingIds: $failing) extends FakeCatalogSource
    {
        /** @param  list<int>  $failingIds */
        public function __construct(array $movies, array $sections, private array $failingIds)
        {
            parent::__construct($movies, sections: $sections);
        }

        public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
        {
            return in_array($tmdbId, $this->failingIds, true) ? throw new SourceUnavailable('down') : parent::movie($tmdbId, $context);
        }
    });
}

/** Details whose US date is the given theatrical date, else the given digital one. */
function detailsOpening(int $tmdbId, ?string $theatrical = null, ?string $digital = null): MovieDetails
{
    return new MovieDetails(tmdbId: $tmdbId, title: "Movie {$tmdbId}", usReleaseDate: $theatrical, usDigitalReleaseDate: $digital);
}

test('the daily build assembles the hero and ten Movies per section, each Movie only in its earliest section', function () {
    $this->travelTo('2026-11-06 05:00:00');
    fakeDiscoverSource([
        'trending' => [movieListings(1, 12)],
        'popular' => [[...movieListings(5, 10), ...movieListings(20, 30)]],
        'coming_soon' => [[...movieListings(28, 29), ...movieListings(50, 60)]],
        'top_rated' => [[...movieListings(1, 3), ...movieListings(25, 40), movieListing(50)]],
        'genre-35' => [[...movieListings(30, 35), ...movieListings(70, 80)]],
        'genre-18' => [[...movieListings(70, 75), ...movieListings(81, 90)]],
        'genre-878' => [[movieListing(1), movieListing(55), ...movieListings(91, 100)]],
    ], [...array_map(fn (int $tmdbId): MovieDetails => detailsOpening($tmdbId, '2026-11-20'), [28, 29, ...range(50, 60)]), new MovieDetails(tmdbId: 1, title: 'Movie 1', runtime: 120, imdbId: 'tt0000001', genres: [['id' => 878, 'name' => 'Science Fiction']], trailer: ['key' => 'abcdefghijk', 'name' => 'Trailer', 'type' => 'Trailer'])]);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);
    $discover = app(Catalog::class)->discover();

    expect(sectionIds($discover))->toBe([
        'trending' => range(1, 10),
        'popular' => range(20, 29),
        'coming_soon' => range(50, 59),
        'top_rated' => range(30, 39),
        'genre-35' => range(70, 79),
        'genre-18' => range(81, 90),
        'genre-878' => range(91, 100),
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

test('Coming soon keeps Movies whose US date is from tomorrow to 60 days out, reading TMDB for that window', function () {
    $this->travelTo('2026-11-06 05:00:00');
    $source = fakeDiscoverSource(['coming_soon' => [movieListings(1, 8)]], [
        detailsOpening(1, theatrical: '2026-11-06'),
        detailsOpening(2, theatrical: '2026-11-07'),
        detailsOpening(3, theatrical: '2027-01-05'),
        detailsOpening(4, theatrical: '2027-01-06'),
        detailsOpening(5),
        detailsOpening(6, digital: '2026-11-21'),
        detailsOpening(7, theatrical: '2026-10-01', digital: '2026-11-20'),
        detailsOpening(8, theatrical: '2026-12-01', digital: '2026-11-10'),
    ]);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    $comingSoon = collect(app(Catalog::class)->discover()->sections)->firstWhere('kind', SectionKind::ComingSoon);
    $query = collect($source->sectionCalls)->firstWhere('key', 'coming_soon')['query'];
    expect(collect($comingSoon->movies)->mapWithKeys(fn (DiscoverMovie $movie): array => [$movie->tmdbId => $movie->opensOn])->all())->toBe([
        2 => '2026-11-07',
        3 => '2027-01-05',
        6 => '2026-11-21',
        8 => '2026-12-01',
    ])
        ->and([$query->releasedFrom, $query->releasedUntil])->toBe(['2026-11-07', '2027-01-05']);
});

test('Coming soon fills up from its next page and looks up no more US dates than configured', function () {
    $this->travelTo('2026-11-06 05:00:00');
    config(['catalog.discover.coming_soon.max_lookups' => 4]);
    $source = fakeDiscoverSource(['coming_soon' => [[movieListing(1), movieListing(2)], movieListings(3, 10), movieListings(11, 12)]], [
        detailsOpening(1, theatrical: '2026-10-01'),
        ...array_map(fn (int $tmdbId): MovieDetails => detailsOpening($tmdbId, theatrical: '2026-11-20'), range(2, 12)),
    ]);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(sectionIds(app(Catalog::class)->discover())['coming_soon'])->toBe([2, 3, 4])
        ->and(collect($source->sectionCalls)->where('key', 'coming_soon')->pluck('page')->all())->toBe([1, 2]);
});

test('a Coming soon Movie whose details fail keeps its previous US date', function () {
    $this->travelTo('2026-11-06 05:00:00');
    fakeDiscoverSource(['coming_soon' => [[movieListing(1)]]], [detailsOpening(1, theatrical: '2026-11-20')]);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);
    $this->travelTo('2026-11-07 05:00:00');
    installFailingDetails(['coming_soon' => [[movieListing(1), movieListing(2)]]], [detailsOpening(2, theatrical: '2026-11-25')], failing: [1]);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(sectionIds(app(Catalog::class)->discover())['coming_soon'])->toBe([1, 2]);
});

test('Coming soon keeps its previous version when a failed lookup leaves it empty', function (array $details, array $failing) {
    $this->travelTo('2026-11-06 05:00:00');
    fakeDiscoverSource(['coming_soon' => [[movieListing(1)]]], [detailsOpening(1, theatrical: '2026-11-20')]);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);
    $this->travelTo('2026-11-07 05:00:00');
    installFailingDetails(['coming_soon' => [[movieListing(3), movieListing(4)]]], $details, $failing);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(sectionIds(app(Catalog::class)->discover())['coming_soon'])->toBe([1]);
})->with([
    'every lookup failing' => [[], [3, 4]],
    'the rest outside the window' => [[detailsOpening(4, theatrical: '2027-03-01')], [3]],
]);

test('a Coming soon Movie is no longer shown once its US date has come', function () {
    $this->travelTo('2026-11-06 05:00:00');
    fakeDiscoverSource(['coming_soon' => [movieListings(1, 2)]], [detailsOpening(1, theatrical: '2026-11-07'), detailsOpening(2, theatrical: '2026-11-20')]);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    $this->travelTo('2026-11-07 00:00:00');

    expect(sectionIds(app(Catalog::class)->discover())['coming_soon'])->toBe([2]);
});

/** @return list<array{int, int}> the genre id and vote floor of each genre collection the build read */
function genreQueries(FakeCatalogSource $source): array
{
    return collect($source->sectionCalls)->where('kind', SectionKind::Genre)->map(fn (array $call): array => [$call['query']->genreId, $call['query']->minimumVotes])->values()->all();
}

test('three genre collections are picked from the config list by the day, by popularity above the vote floor', function (string $time, array $picks) {
    $this->travelTo($time);
    $source = fakeDiscoverSource([]);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(genreQueries($source))->toBe($picks);
})->with([
    'early on 6 Nov' => ['2026-11-06 00:00:00', [[35, 300], [18, 300], [878, 300]]],
    'late on 6 Nov' => ['2026-11-06 23:59:59', [[35, 300], [18, 300], [878, 300]]],
    '7 Nov' => ['2026-11-07 05:00:00', [[27, 300], [16, 300], [53, 300]]],
]);

test('a genre collection is titled by its genre', function () {
    $this->travelTo('2026-11-06 05:00:00');
    fakeDiscoverSource(['genre-35' => [movieListings(1, 10)], 'genre-18' => [movieListings(11, 20)], 'genre-878' => [movieListings(21, 30)]]);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(genreTitles(app(Catalog::class)->discover()))->toBe(['Comedy', 'Drama', 'Science Fiction']);
});

test('a genre collection that fails keeps the previous build\'s collection in its place', function () {
    $this->travelTo('2026-11-06 05:00:00');
    fakeDiscoverSource(['genre-35' => [movieListings(1, 10)], 'genre-18' => [movieListings(11, 20)], 'genre-878' => [movieListings(21, 30)]]);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    $this->travelTo('2026-11-07 05:00:00');
    fakeDiscoverSource(['genre-27' => [movieListings(31, 40)], 'genre-53' => [movieListings(41, 50)]], failingSections: ['genre-16']);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(genreTitles(app(Catalog::class)->discover()))->toBe(['Horror', 'Drama', 'Thriller'])
        ->and(sectionIds(app(Catalog::class)->discover())['genre-18'])->toBe(range(11, 20));
});

test('a failed genre collection keeps the previous one for its own genre first, and never repeats a genre the day shows', function () {
    config(['catalog.discover.genre_collections.genres' => [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D']]);
    $this->travelTo('2026-11-06 05:00:00');
    fakeDiscoverSource(['genre-2' => [movieListings(1, 10)], 'genre-3' => [movieListings(11, 20)], 'genre-4' => [movieListings(21, 30)]]);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    $this->travelTo('2026-11-07 05:00:00');
    fakeDiscoverSource(['genre-3' => [movieListings(31, 40)]], failingSections: ['genre-1', 'genre-2']);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    expect(genreTitles(app(Catalog::class)->discover()))->toBe(['B', 'C'])
        ->and(sectionIds(app(Catalog::class)->discover()))->toBe(['genre-2' => range(1, 10), 'genre-3' => range(31, 40)]);
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

test('the first visitor builds a missing payload, asking for the hero\'s details only and leaving Coming soon to the full build', function () {
    $source = fakeDiscoverSource(['trending' => [movieListings(1, 10)], 'coming_soon' => [movieListings(11, 20)]], [new MovieDetails(tmdbId: 1, runtime: 120), new MovieDetails(tmdbId: 2, runtime: 95)]);

    $discover = app(Catalog::class)->discover();

    expect($discover->detailed)->toBeFalse()
        ->and(collect($source->sectionCalls)->pluck('key'))->not->toContain('coming_soon')
        ->and($discover->hero->runtime)->toBe(120)
        ->and($discover->sections[0]->movies[1]->toArray())->toMatchArray(['tmdbId' => 2, 'runtime' => null, 'imdbId' => null]);
});

test('every listed Movie\'s TMDB score is recorded, with or without a Movie row', function () {
    fakeDiscoverSource(['trending' => [[movieListing(1, voteAverage: 8.4, voteCount: 2500), movieListing(2, posterPath: null, voteAverage: 6.1, voteCount: 40), movieListing(3, voteCount: 0)]]]);

    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    $this->assertDatabaseHas('ratings', ['source' => 'tmdb', 'source_id' => 'movie:1', 'value' => 8.4, 'vote_count' => 2500]);
    $this->assertDatabaseHas('ratings', ['source' => 'tmdb', 'source_id' => 'movie:2', 'value' => 6.1, 'vote_count' => 40]);
    $this->assertDatabaseMissing('ratings', ['source' => 'tmdb', 'source_id' => 'movie:3']);
    $this->assertDatabaseCount('titles', 0);
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
        public function section(SectionQuery $query, int $page, CatalogContext $context): SectionPage
        {
            return $query->kind === SectionKind::Trending ? throw new SourceMisconfigured('rejected') : parent::section($query, $page, $context);
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
