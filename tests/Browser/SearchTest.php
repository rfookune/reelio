<?php

use App\Enums\TitleKind;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\Listings\MovieListing;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Binds a fake source that finds the given pages and knows every Movie on them.
 *
 * @param  array<string, list<list<MovieListing>>>  $searches
 * @param  list<list<MovieListing>>  $browsePages
 * @param  list<list<MovieListing>>  $tvBrowsePages
 * @param  list<array{id: int, name: string}>  $genres
 * @param  list<array{id: int, name: string}>  $tvGenres
 */
function fakeBrowserSearch(array $searches, array $browsePages = [], array $tvBrowsePages = [], array $genres = [], array $tvGenres = []): FakeCatalogSource
{
    config(['catalog.image_base_url' => '/test-images/']);
    $movies = collect([...$searches, ...$browsePages, ...$tvBrowsePages])->flatten(2)->map(fn (MovieListing $listing): MovieDetails => new MovieDetails(tmdbId: $listing->tmdbId, title: $listing->title))->unique('tmdbId')->values()->all();
    $source = new FakeCatalogSource($movies, searches: $searches, browsePages: $browsePages, tvBrowsePages: $tvBrowsePages, genres: $genres, tvGenres: $tvGenres);
    app()->instance(CatalogSource::class, $source);

    return $source;
}

/**
 * Holds back, in the browser, the sending of each search request for the given query by the given milliseconds,
 * so it is in flight while the test types on. The server can't be the one to wait: it runs in the test's own
 * process, and would hold up the typing too. A request cancelled meanwhile is never sent.
 */
function delaySearchRequests(string $query, int $milliseconds): string
{
    return sprintf(<<<'JS'
        (() => {
            const open = XMLHttpRequest.prototype.open;
            const send = XMLHttpRequest.prototype.send;
            const abort = XMLHttpRequest.prototype.abort;
            XMLHttpRequest.prototype.abort = function () {
                this.__aborted = true;
                return abort.call(this);
            };
            XMLHttpRequest.prototype.open = function (method, url, ...rest) {
                this.__delayed = new URL(url, location.href).searchParams.get('q') === %s;
                return open.call(this, method, url, ...rest);
            };
            XMLHttpRequest.prototype.send = function (...body) {
                if (!this.__delayed) {
                    return send.apply(this, body);
                }
                setTimeout(() => {
                    if (!this.__aborted) {
                        send.apply(this, body);
                    }
                }, %d);
            };
            return true;
        })()
        JS, json_encode($query), $milliseconds);
}

/** Records, in the page, the catalog id of every result card ever shown. */
function recordShownResults(): string
{
    return <<<'JS'
        (() => {
            window.__shownResults = new Set();
            const record = () => document.querySelectorAll('[data-slot=search-results] [data-tmdb-id]').forEach((card) => window.__shownResults.add(Number(card.dataset.tmdbId)));
            new MutationObserver(record).observe(document.body, { subtree: true, childList: true });
            return true;
        })()
        JS;
}

it('lists Popular Movies while nothing is typed, and a search by Name replaces them', function () {
    fakeBrowserSearch(['inter' => [[movieListing(7, title: 'Inter movie')]]], [movieListings(1, 20), movieListings(21, 25)]);

    $page = visitWithCsp('/search');

    $page->assertSeeIn('[data-slot=search-results]', 'Popular movies')
        ->assertDontSee('Trending this week');
    expect($page->script('document.querySelectorAll("[data-slot=search-results] [data-slot=poster-card]").length'))->toBe(20);

    $page->typeSlowly('#search-query', 'inter', 60)
        ->assertSeeIn('[data-slot=search-results]', '1 result')
        ->assertDontSee('Popular movies')
        ->assertNoJavaScriptErrors();
});

it('browses TV Shows from the other tab, keeping the tab in the URL, and a search by Name leaves it', function () {
    fakeBrowserSearch(['inter' => [[movieListing(7, title: 'Inter movie')]]], [movieListings(1, 3)], [[movieListing(51, title: 'Show 51', kind: TitleKind::TvShow), movieListing(52, title: 'Show 52', kind: TitleKind::TvShow)]]);

    $page = visitWithCsp('/search');

    $page->assertSeeIn('[data-slot=search-results]', 'Popular movies')
        ->assertAttribute('[data-browse-tab=movie]', 'aria-pressed', 'true')
        ->click('[data-browse-tab=tv]')
        ->assertSeeIn('[data-slot=search-results]', 'Popular TV shows')
        ->assertAttribute('[data-browse-tab=tv]', 'aria-pressed', 'true')
        ->assertNoJavaScriptErrors();

    expect($page->url())->toEndWith('/search?tab=tv')
        ->and($page->script('document.querySelectorAll("[data-slot=search-results] [data-slot=poster-card]").length'))->toBe(2);

    $page->typeSlowly('#search-query', 'inter', 60)
        ->assertSeeIn('[data-slot=search-results]', '1 result')
        ->assertMissing('[data-browse-tab]');
    expect($page->url())->toEndWith('/search?q=inter&tab=tv');

    $page->fill('#search-query', '')
        ->assertSeeIn('[data-slot=search-results]', 'Popular TV shows')
        ->assertAttribute('[data-browse-tab=tv]', 'aria-pressed', 'true')
        ->assertNoJavaScriptErrors();
    expect($page->url())->toEndWith('/search?tab=tv');
});

it('lists Browse cards with no rating placeholder, since only a search by Name looks IMDb ratings up', function () {
    config(['ratings.sources.imdb.enabled' => true]);
    fakeBrowserSearch([], [movieListings(1, 3)]);

    visitWithCsp('/search')->assertPresent('[data-slot=search-results] [data-slot=poster-card]')
        ->assertNotPresent('[data-slot=ratings-pending]')
        ->assertNoJavaScriptErrors();
});

it('narrows Browse by genre, counts and clears the filter, and Back restores the previous one', function () {
    $source = fakeBrowserSearch([], [movieListings(1, 3)], [], [['id' => 28, 'name' => 'Action'], ['id' => 35, 'name' => 'Comedy']]);

    $page = visitWithCsp('/search');

    $page->assertPresent('#browse-genre')->assertMissing('[data-slot=search-filter-count]');
    $page->select('#browse-genre', '35')
        ->assertSeeIn('[data-slot=search-filter-count]', '1 filter active')
        ->assertSeeIn('[data-slot=search-results]', 'Popular movies')
        ->assertNoJavaScriptErrors();

    expect($page->url())->toEndWith('/search?genre=35')
        ->and($page->script('document.querySelector("#browse-genre").value'))->toBe('35')
        ->and(array_map(fn ($filters) => $filters->genreId, $source->browseFilters))->toBe([null, 35]);

    $page->select('#browse-genre', '28')->wait(1);
    expect($page->url())->toEndWith('/search?genre=28');

    $page->script('history.back()');
    $page->wait(1);
    expect($page->url())->toEndWith('/search?genre=35')
        ->and($page->script('document.querySelector("#browse-genre").value'))->toBe('35');

    $page->click('[data-slot=search-clear-filters]')
        ->assertMissing('[data-slot=search-filter-count]')
        ->assertNoJavaScriptErrors();
    expect($page->url())->toEndWith('/search')
        ->and($page->script('document.querySelector("#browse-genre").value'))->toBe('');
});

it('keeps a genre across tabs only when the other tab has one of that name, and a Name clears the filters', function () {
    fakeBrowserSearch(
        ['inter' => [[movieListing(7, title: 'Inter movie')]]],
        [movieListings(1, 3)],
        [[movieListing(51, title: 'Show 51', kind: TitleKind::TvShow)]],
        [['id' => 28, 'name' => 'Action'], ['id' => 35, 'name' => 'Comedy']],
        [['id' => 9035, 'name' => 'Comedy'], ['id' => 10759, 'name' => 'Action & Adventure']],
    );

    $page = visitWithCsp('/search?genre=35');

    $page->click('[data-browse-tab=tv]')->assertSeeIn('[data-slot=search-results]', 'Popular TV shows');
    expect($page->script('document.querySelector("#browse-genre").value'))->toBe('9035')
        ->and($page->script('[...document.querySelectorAll("#browse-genre option")].map((option) => option.textContent.trim())'))->toBe(['Any genre', 'Comedy', 'Action & Adventure']);

    $page->select('#browse-genre', '10759')->assertSeeIn('[data-slot=search-filter-count]', '1 filter active');
    $page->click('[data-browse-tab=movie]')->assertSeeIn('[data-slot=search-results]', 'Popular movies')
        ->assertMissing('[data-slot=search-filter-count]');
    expect($page->script('document.querySelector("#browse-genre").value'))->toBe('');

    $page->select('#browse-genre', '35')->assertSeeIn('[data-slot=search-filter-count]', '1 filter active');
    $page->typeSlowly('#search-query', 'inter', 60)
        ->assertSeeIn('[data-slot=search-results]', '1 result')
        ->assertMissing('[data-slot=search-filter-count]');
    expect($page->url())->toEndWith('/search?q=inter')
        ->and($page->script('document.querySelector("#browse-genre").value'))->toBe('');

    $page->fill('#search-query', '')->assertSeeIn('[data-slot=search-results]', 'Popular movies')
        ->assertMissing('[data-slot=search-filter-count]');
});

it('choosing a genre replaces a typed Name with Browse', function () {
    fakeBrowserSearch(['inter' => [[movieListing(7, title: 'Inter movie')]]], [movieListings(1, 3)], [], [['id' => 35, 'name' => 'Comedy']]);

    $page = visitWithCsp('/search?q=inter');

    $page->assertSeeIn('[data-slot=search-results]', '1 result')->assertPresent('#browse-genre');
    $page->select('#browse-genre', '35')
        ->assertSeeIn('[data-slot=search-filter-count]', '1 filter active')
        ->assertSeeIn('[data-slot=search-results]', 'Popular movies');
    expect($page->script('document.querySelector("#search-query").value'))->toBe('')
        ->and($page->url())->toEndWith('/search?genre=35');
});

it('shows a no-match message with a way out when the genre matches nothing', function () {
    fakeBrowserSearch([], [movieListings(1, 3)], [], [['id' => 35, 'name' => 'Comedy']]);
    $page = visitWithCsp('/search');
    app()->instance(CatalogSource::class, new FakeCatalogSource(browsePages: [[]], genres: [['id' => 35, 'name' => 'Comedy']]));

    $page->select('#browse-genre', '35')
        ->assertSee('No movies match these filters')
        ->click('[data-slot=search-no-matches] button')
        ->assertMissing('[data-slot=search-no-matches]')
        ->assertNoJavaScriptErrors();
});

it('searches once the typing pauses, not on every keystroke', function () {
    $source = fakeBrowserSearch(['inter' => [movieListings(1, 20), movieListings(21, 25)]]);

    $page = visitWithCsp('/search');

    expect($page->script('document.activeElement.id'))->toBe('search-query');

    $page->typeSlowly('#search-query', 'inter', 60)
        ->assertSeeIn('[data-slot=search-results]', '25 results')
        ->assertNoJavaScriptErrors();

    expect($source->searchCalls)->toBe([['query' => 'inter', 'page' => 1]])
        ->and($page->url())->toEndWith('/search?q=inter')
        ->and($page->script('document.querySelectorAll("[data-slot=search-results] [data-slot=poster-card]").length'))->toBe(20)
        ->and($page->script('document.querySelector("[role=status][aria-live=polite]").textContent.trim()'))->toBe("25 results for 'inter'");
});

it('drops a search in flight when the query changes, so its results never show', function () {
    $source = fakeBrowserSearch([
        'slow' => [[movieListing(1, title: 'Slow movie')]],
        'slower' => [[movieListing(2, title: 'Slower movie')]],
    ]);

    $page = visitWithCsp('/search');
    $page->script(delaySearchRequests('slow', 1500));
    $page->script(recordShownResults());

    $page->typeSlowly('#search-query', 'slow', 20)
        ->wait(0.6)
        ->typeSlowly('#search-query', 'er', 20)
        ->assertSeeIn('[data-slot=search-results]', 'Slower movie')
        ->wait(1.5)
        ->assertDontSee('Slow movie')
        ->assertNoJavaScriptErrors();

    expect($page->script('[...window.__shownResults]'))->toBe([2])
        ->and($source->searchCalls)->toBe([['query' => 'slower', 'page' => 1]]);
});

it('restores the query, the loaded pages and the scroll position on Back from a result', function () {
    fakeBrowserSearch(['inter' => [movieListings(1, 20), movieListings(21, 25)]]);

    $page = visitWithCsp('/search?q=inter');

    $page->assertSeeIn('[data-slot=search-results]', '25 results')
        ->assertNoAccessibilityIssues(level: 1)
        ->click('Show more results')
        ->assertSee('Movie 25');
    $page->script('window.scrollTo(0, document.body.scrollHeight)');
    $page->wait(0.3);
    $scrolledTo = $page->script('window.scrollY');

    $page->click('[data-tmdb-id="22"] a:not([aria-hidden])')
        ->assertPathBeginsWith('/movies/movie-22-')
        ->back()
        ->assertSee('Movie 25');

    $page->assertNoJavaScriptErrors();

    expect($page->value('#search-query'))->toBe('inter')
        ->and($page->script('document.querySelectorAll("[data-slot=search-results] [data-slot=poster-card]").length'))->toBe(25)
        ->and($scrolledTo)->toBeGreaterThan(0)
        ->and($page->script('window.scrollY'))->toBe($scrolledTo);
});

it('drops a search in flight when the query goes back to the one shown', function () {
    fakeBrowserSearch([
        'inter' => [[movieListing(1, title: 'Inter movie')]],
        'interx' => [[movieListing(2, title: 'Interx movie')]],
    ]);

    $page = visitWithCsp('/search?q=inter');
    $page->assertPresent('[data-slot=search-results] [data-tmdb-id="1"]');
    $page->script(delaySearchRequests('interx', 1500));
    $page->script(recordShownResults());

    $page->typeSlowly('#search-query', 'x', 20)
        ->wait(0.6)
        ->keys('#search-query', 'Backspace')
        ->wait(2)
        ->assertPresent('[data-slot=search-results] [data-tmdb-id="1"]')
        ->assertNoJavaScriptErrors();

    expect($page->script('[...window.__shownResults]'))->toBe([1])
        ->and($page->script('document.querySelector("[data-slot=search-results]").getAttribute("aria-busy")'))->toBe('false');
});

it('says inline when searches come too fast, and Retry searches again from the input', function () {
    fakeBrowserSearch(['inter' => [movieListings(1, 3)]]);

    $page = visitWithCsp('/search');

    for ($attempt = 1; $attempt <= 59; $attempt++) {
        $this->get('/search')->assertOk();
    }

    $page->typeSlowly('#search-query', 'inter', 20)
        ->assertSeeIn('[data-slot=search-unavailable]', "You're going too fast. Try again in a moment.")
        ->assertNotPresent('[data-slot=search-loading]');

    expect($page->text('[role="status"][aria-live="polite"]'))->toBe("You're going too fast. Try again in a moment.");

    RateLimiter::clear(md5('search'.'ip:127.0.0.1'));
    $page->click('Retry')
        ->assertSeeIn('[data-slot=search-results]', '3 results')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.activeElement.id'))->toBe('search-query');
});

/** Holds back, in the browser, the sending of each request for a page's IMDb ratings by the given milliseconds. */
function delayRatingsRequests(int $milliseconds): string
{
    return sprintf(<<<'JS'
        (() => {
            const open = XMLHttpRequest.prototype.open;
            const send = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.open = function (method, url, ...rest) {
                this.__delayed = new URL(url, location.href).pathname === '/search/ratings';
                return open.call(this, method, url, ...rest);
            };
            XMLHttpRequest.prototype.send = function (...body) {
                return this.__delayed ? setTimeout(() => send.apply(this, body), %d) : send.apply(this, body);
            };
            return true;
        })()
        JS, $milliseconds);
}

it('shows a card\'s TMDB rating as a placeholder while its IMDb rating is looked up, then the IMDb rating', function () {
    config(['ratings.sources.imdb.enabled' => true]);
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0000001', 8.1, 5000);
    $source = fakeBrowserSearch(['inter' => [[movieListing(1, voteAverage: 7.5), movieListing(2, voteAverage: 6.5)]]]);
    $source->imdbIds = [1 => 'tt0000001'];

    $page = visitWithCsp('/search');
    $page->script(delayRatingsRequests(1500));

    $page->typeSlowly('#search-query', 'inter', 20)
        ->assertPresent('[data-tmdb-id="1"] [data-slot="ratings-pending"]')
        ->assertPresent('[data-tmdb-id="2"] [data-slot="ratings-pending"]')
        ->wait(2)
        ->assertSeeIn('[data-tmdb-id="1"] [data-slot="external-rating"]', 'IMDb')
        ->assertSeeIn('[data-tmdb-id="1"] [data-slot="external-rating"]', '8.1')
        ->assertSeeIn('[data-tmdb-id="1"] [data-slot="imdb-credit"]', 'Information courtesy of IMDb. Used with permission.')
        ->assertDontSeeIn('[data-tmdb-id="1"] [data-slot="imdb-credit"]', 'https://www.imdb.com')
        ->assertMissing('[data-tmdb-id="1"] [data-slot="ratings-pending"]')
        ->assertSeeIn('[data-tmdb-id="2"] [data-slot="external-rating"]', 'TMDB')
        ->assertSeeIn('[data-tmdb-id="2"] [data-slot="external-rating"]', '6.5')
        ->assertMissing('[data-slot="ratings-pending"]')
        ->assertNoJavaScriptErrors();

    expect(array_column($source->imdbIdCalls, 'tmdbId'))->toBe([1, 2]);
});

it('looks up the IMDb ratings of each further page as it is shown, and none a second time', function () {
    config(['ratings.sources.imdb.enabled' => true, 'catalog.imdb_lookup.requests_per_second' => 100]);
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0000021', 7.7, 300);
    $source = fakeBrowserSearch(['inter' => [movieListings(1, 20), [movieListing(21, voteAverage: 5.5)]]]);
    $source->imdbIds = [21 => 'tt0000021'];

    $page = visitWithCsp('/search');
    $page->typeSlowly('#search-query', 'inter', 20)
        ->assertSeeIn('[data-slot=search-results]', '21 results')
        ->wait(1)
        ->click('Show more results')
        ->assertSeeIn('[data-tmdb-id="21"] [data-slot="external-rating"]', 'IMDb')
        ->assertSeeIn('[data-tmdb-id="21"] [data-slot="external-rating"]', '7.7')
        ->assertNoJavaScriptErrors();

    expect(array_count_values(array_column($source->imdbIdCalls, 'tmdbId')))->each->toBe(1)
        ->and(array_column($source->imdbIdCalls, 'tmdbId'))->toContain(21)->toHaveCount(21);
});

it('shows TV Shows beside Movies, filters them with the chips, and fills in a TV Show\'s IMDb rating after render', function () {
    config(['ratings.sources.imdb.enabled' => true]);
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0000099', 9.2, 700);
    $source = fakeBrowserSearch(['inter' => [[
        movieListing(1),
        movieListing(2, title: 'Chernobyl', kind: TitleKind::TvShow, voteAverage: 8.5),
        movieListing(3, title: 'Unrated', kind: TitleKind::TvShow, voteAverage: null, voteCount: 0),
    ]]]);
    $source->tvImdbIds = [2 => 'tt0000099'];

    $page = visitWithCsp('/search');
    $page->typeSlowly('#search-query', 'inter', 20)
        ->wait(2)
        ->assertSeeIn('[data-kind-chip="all"]', '3')
        ->assertSeeIn('[data-kind-chip="movie"]', '1')
        ->assertSeeIn('[data-kind-chip="tv"]', '2')
        ->assertSeeIn('[data-tmdb-id="2"] [data-slot="tv-badge"]', 'TV')
        ->assertSeeIn('[data-tmdb-id="2"] [data-slot="external-rating"]', 'IMDb')
        ->assertSeeIn('[data-tmdb-id="2"] [data-slot="external-rating"]', '9.2')
        ->assertSeeIn('[data-tmdb-id="3"]', 'Not yet rated')
        ->click('[data-kind-chip="movie"]')
        ->assertPresent('[data-tmdb-id="1"]')
        ->assertMissing('[data-tmdb-id="2"]')
        ->click('[data-kind-chip="tv"]')
        ->assertMissing('[data-tmdb-id="1"]')
        ->assertPresent('[data-tmdb-id="2"]')
        ->click('[data-kind-chip="all"]')
        ->assertPresent('[data-tmdb-id="1"]')
        ->assertNoJavaScriptErrors();
});
