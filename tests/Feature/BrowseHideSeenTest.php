<?php

use App\Enums\TitleKind;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use App\Models\UserTitle;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\Listings\MovieListing;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/** Installs a fake source whose Movie Browse has the given pages and whose TV Show Browse is catalog ids 51 to 56. */
function fakeSeenBrowse(array $moviePages): FakeCatalogSource
{
    $source = new FakeCatalogSource(
        browsePages: $moviePages,
        tvBrowsePages: [array_map(fn (int $id): MovieListing => movieListing($id, kind: TitleKind::TvShow), range(51, 56))],
    );
    app()->instance(CatalogSource::class, $source);

    return $source;
}

/** @return list<string> the keys of the cards on the page */
function shownKeys(TestResponse $response): array
{
    return array_column($response->viewData('page')['props']['results']['data'], 'key');
}

/** Movies 1 to 6 and TV Shows 51 to 56, each in a different standing with the user. */
function userWhoHasSeenSome(): User
{
    $user = User::factory()->create();

    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 1]))->watched()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => Movie::factory()->create(['tmdb_id' => 2])->id, 'rating' => 7]);
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 3]))->create();
    Movie::factory()->create(['tmdb_id' => 4]);

    $completed = listedTvShow($user, 51, 'Completed', null);
    episodeOf($completed, $user, '2026-01-01');
    episodeOf($completed, $user, '2026-01-02');
    $inProgress = listedTvShow($user, 52, 'In progress', null);
    episodeOf($inProgress, $user, '2026-01-01');
    episodeOf($inProgress);
    $notStarted = listedTvShow($user, 53, 'Not started', null);
    episodeOf($notStarted);
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => listedTvShow($user, 54, 'Rated', null)->id, 'rating' => 9]);
    listedTvShow($user, 55, 'Watchlisted', null, '2026-01-01');

    return $user;
}

test('a signed-in user\'s Browse hides Watched Movies, Completed TV Shows and rated Titles, and keeps the rest', function (string $tab, array $kept) {
    fakeSeenBrowse([movieListings(1, 6)]);

    $response = $this->actingAs(userWhoHasSeenSome())->get(route('search', ['tab' => $tab]));

    expect(shownKeys($response))->toBe($kept);
})->with([
    'Movies' => ['movie', ['3', '4', '5', '6']],
    'TV Shows' => ['tv', ['tv:52', 'tv:53', 'tv:55', 'tv:56']],
]);

test('another user\'s facts hide nothing', function () {
    fakeSeenBrowse([movieListings(1, 6)]);
    userWhoHasSeenSome();

    $response = $this->actingAs(User::factory()->create())->get(route('search'));

    expect(shownKeys($response))->toBe(['1', '2', '3', '4', '5', '6']);
});

test('Show seen turns the hiding off, and is kept as a prop for the toggle', function () {
    fakeSeenBrowse([movieListings(1, 6)]);
    $user = userWhoHasSeenSome();

    $hidden = $this->actingAs($user)->get(route('search'));
    $shown = $this->actingAs($user)->get(route('search', ['show_seen' => '1']));

    expect(shownKeys($shown))->toBe(['1', '2', '3', '4', '5', '6']);
    $hidden->assertInertia(fn (Assert $page) => $page->where('canHideSeen', true)->where('showSeen', false));
    $shown->assertInertia(fn (Assert $page) => $page->where('canHideSeen', true)->where('showSeen', true));
});

test('only show_seen=1 turns it off', function (mixed $value) {
    fakeSeenBrowse([movieListings(1, 6)]);

    $response = $this->actingAs(userWhoHasSeenSome())->get(route('search', ['show_seen' => $value]));

    expect(shownKeys($response))->toBe(['3', '4', '5', '6']);
})->with([['0'], ['true'], [''], [['1']]]);

test('a guest sees no hiding and no toggle, whatever the URL says', function (array $query) {
    fakeSeenBrowse([movieListings(1, 6)]);
    userWhoHasSeenSome();

    $response = $this->get(route('search', $query));

    expect(shownKeys($response))->toBe(['1', '2', '3', '4', '5', '6']);
    $response->assertInertia(fn (Assert $page) => $page->where('canHideSeen', false)->where('showSeen', false));
})->with([[[]], [['show_seen' => '1']]]);

test('a search by Name hides nothing', function () {
    $source = fakeSeenBrowse([movieListings(1, 6)]);
    $source->searches = ['inter' => [movieListings(1, 2)]];

    $response = $this->actingAs(userWhoHasSeenSome())->get(route('search', ['q' => 'inter']));

    expect(shownKeys($response))->toBe(['1', '2']);
});

test('the viewer\'s facts are still returned for the Titles shown', function () {
    fakeSeenBrowse([movieListings(1, 6)]);

    $this->actingAs(userWhoHasSeenSome())->get(route('search'))->assertInertia(fn (Assert $page) => $page
        ->where('userMovies.3.watchlisted', true)
        ->where('userMovies.4.watchlisted', false)
        ->missing('userMovies.1'));
});

test('a short page is filled from the next source pages until it reaches the fill target, then the next page is the one after', function () {
    config(['catalog.search.browse_fill_target' => 3, 'catalog.search.maximum_browse_source_pages' => 5]);
    $source = fakeSeenBrowse([movieListings(1, 2), movieListings(3, 4), movieListings(5, 6), movieListings(7, 8)]);
    $user = User::factory()->create();
    foreach ([1, 2, 3] as $id) {
        UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => $id]))->watched()->create();
    }

    $response = $this->actingAs($user)->get(route('search'));

    expect(shownKeys($response))->toBe(['4', '5', '6'])
        ->and(array_column($source->browseCalls, 'page'))->toBe([1, 2, 3])
        ->and($response->viewData('page')['scrollProps']['results'])->toMatchArray(['previousPage' => null, 'nextPage' => 4, 'currentPage' => 1]);
});

test('the next page starts where the last one stopped reading', function () {
    config(['catalog.search.browse_fill_target' => 3, 'catalog.search.maximum_browse_source_pages' => 5]);
    $source = fakeSeenBrowse([movieListings(1, 2), movieListings(3, 4), movieListings(5, 6), movieListings(7, 8)]);

    $response = $this->actingAs(User::factory()->create())->get(route('search', ['page' => 3]));

    expect(shownKeys($response))->toBe(['5', '6', '7', '8'])
        ->and(array_column($source->browseCalls, 'page'))->toBe([3, 4])
        ->and($response->viewData('page')['scrollProps']['results'])->toMatchArray(['previousPage' => null, 'nextPage' => null, 'currentPage' => 3]);
});

test('a page that cannot be filled within the cap is short and still reports that more pages exist', function () {
    config(['catalog.search.browse_fill_target' => 3, 'catalog.search.maximum_browse_source_pages' => 2]);
    $source = fakeSeenBrowse([movieListings(1, 2), movieListings(3, 4), movieListings(5, 6), movieListings(7, 8)]);
    $user = User::factory()->create();
    foreach ([1, 2, 3, 4] as $id) {
        UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => $id]))->watched()->create();
    }

    $response = $this->actingAs($user)->get(route('search'));

    expect(shownKeys($response))->toBe([])
        ->and(array_column($source->browseCalls, 'page'))->toBe([1, 2])
        ->and($response->viewData('page')['scrollProps']['results'])->toMatchArray(['nextPage' => 3, 'currentPage' => 1]);
});

test('when the source runs out there is no next page', function () {
    config(['catalog.search.browse_fill_target' => 5, 'catalog.search.maximum_browse_source_pages' => 5]);
    $source = fakeSeenBrowse([movieListings(1, 2), movieListings(3, 4)]);

    $response = $this->actingAs(User::factory()->create())->get(route('search'));

    expect(shownKeys($response))->toBe(['1', '2', '3', '4'])
        ->and(array_column($source->browseCalls, 'page'))->toBe([1, 2])
        ->and($response->viewData('page')['scrollProps']['results'])->toMatchArray(['nextPage' => null]);
});

test('a guest and a signed-in user who shows seen read one source page', function () {
    $source = fakeSeenBrowse([movieListings(1, 2), movieListings(3, 4)]);

    $this->get(route('search'))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('search', ['show_seen' => '1']))->assertOk();

    expect(array_column($source->browseCalls, 'page'))->toBe([1]);
});

test('a source that fails on a later page keeps what was read and points the next page at the one that failed', function () {
    config(['catalog.search.browse_fill_target' => 5, 'catalog.search.maximum_browse_source_pages' => 5]);
    $source = fakeSeenBrowse([movieListings(1, 2), movieListings(3, 4)]);
    $source->failingBrowsePages = [2];
    $user = User::factory()->create();
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 1]))->watched()->create();

    $response = $this->actingAs($user)->get(route('search'));

    expect(shownKeys($response))->toBe(['2'])
        ->and($response->viewData('page')['scrollProps']['results'])->toMatchArray(['nextPage' => 2, 'currentPage' => 1]);
});

test('an unavailable first source page is an unavailable page', function () {
    $source = fakeSeenBrowse([movieListings(1, 2)]);
    $source->failing = true;

    $this->actingAs(User::factory()->create())->get(route('search'))->assertInertia(fn (Assert $page) => $page
        ->where('status', 'unavailable')
        ->where('results.data', []));
});

test('a Title that is rated stays hidden even when it is on the Watchlist', function () {
    fakeSeenBrowse([movieListings(1, 3)]);
    $user = User::factory()->create();
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 1]))->create(['rating' => 8]);

    expect(shownKeys($this->actingAs($user)->get(route('search'))))->toBe(['2', '3']);
});

test('a TV Show with no Episodes stored, or none aired yet, is not Completed and stays', function () {
    fakeSeenBrowse([movieListings(1, 2)]);
    $user = User::factory()->create();
    listedTvShow($user, 51, 'No Episodes', null);
    episodeOf(listedTvShow($user, 52, 'Unaired', null), null, null, 1, '2999-01-01');

    expect(shownKeys($this->actingAs($user)->get(route('search', ['tab' => 'tv']))))->toContain('tv:51', 'tv:52');
});

test('reloading the viewer\'s facts alone covers every Title of the cached source pages, even one seen since', function () {
    config(['catalog.search.browse_fill_target' => 2, 'catalog.search.maximum_browse_source_pages' => 5]);
    $source = fakeSeenBrowse([movieListings(1, 2), movieListings(3, 4), movieListings(5, 6)]);
    $user = User::factory()->create();
    foreach ([1, 2] as $id) {
        UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => $id]))->watched()->create();
    }
    $this->actingAs($user)->freezeTime();
    $first = $this->get(route('search'));
    $version = (string) $first->viewData('page')['version'];
    expect(shownKeys($first))->toBe(['3', '4'])
        ->and($first->viewData('page')['scrollProps']['results']['nextPage'])->toBe(3);
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 3]))->watched()->create();
    $reads = count($source->browseCalls);
    $partial = ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'Search', 'X-Inertia-Partial-Data' => 'userMovies'];

    $this->get(route('search'), $partial)->assertOk()->assertJsonPath('props.userMovies.3.watched', true)->assertJsonPath('props.userMovies.4.watched', false);

    expect(count($source->browseCalls))->toBe($reads);
});
