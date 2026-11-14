<?php

use App\Enums\Surface;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Season;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserMovie;
use App\Models\UserTitle;
use App\Services\Catalog\Discover\DiscoverBuilder;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\TvShows\WatchedProgress;
use Inertia\Testing\AssertableInertia as Assert;

test('the Watchlist lists TV Shows beside Movies in every sort, a TV Show\'s release date being its first-air date and undated ones last', function (string $sort, array $expected) {
    $user = User::factory()->create();
    foreach ([
        ['zebra', '2026-03-02', '2026-01-03'],
        ['Éclair', null, '2026-01-01'],
        ['eclair', '2026-03-01', '2026-01-01'],
        ['Apple', '2025-01-01', '2026-01-02'],
    ] as $index => [$title, $releaseDate, $dateAdded]) {
        $index % 2 === 0
            ? UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => $index + 1, 'title' => $title, 'release_date' => $releaseDate]))->create(['watchlisted_at' => $dateAdded])
            : listedTvShow($user, $index + 1, $title, $releaseDate, $dateAdded);
    }

    $this->actingAs($user)->get(route('watchlist', ['sort' => $sort]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('titles.data', fn ($cards) => collect($cards)->pluck('key')->all() === $expected));
})->with([
    'recently added' => ['recently-added', ['1', 'tv:4', '3', 'tv:2']],
    'oldest added' => ['oldest-added', ['tv:2', '3', 'tv:4', '1']],
    'title' => ['title', ['tv:4', 'tv:2', '3', '1']],
    'newest release' => ['newest-release', ['1', '3', 'tv:4', 'tv:2']],
    'oldest release' => ['oldest-release', ['tv:4', '3', '1', 'tv:2']],
]);

test('a TV Show card has its kind, key, Name, first-air year, canonical link and one rating, and no Season count', function () {
    $user = User::factory()->create();
    $tvShow = listedTvShow($user, 7, 'Chernobyl', '2019-05-06', '2026-01-01');
    $tvShow->update(['poster_path' => '/chernobyl.jpg']);

    $this->actingAs($user)->get(route('watchlist'))->assertInertia(fn (Assert $page) => $page
        ->where('titles.data.0', [
            'kind' => 'tv',
            'key' => 'tv:7',
            'tmdbId' => 7,
            'title' => 'Chernobyl',
            'year' => 2019,
            'posterPath' => '/chernobyl.jpg',
            'ratings' => [],
            'href' => $tvShow->canonicalUrl(Surface::Watchlist),
            'userRating' => null,
        ]));
});

test('a Movie and a TV Show with one catalog id are two cards', function () {
    $user = User::factory()->create();
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 5]))->create();
    listedTvShow($user, 5, 'Same id', null, '2026-01-01');

    $this->actingAs($user)->get(route('watchlist'))->assertInertia(fn (Assert $page) => $page
        ->where('titles.data', fn ($cards) => collect($cards)->pluck('key')->sort()->values()->all() === ['5', 'tv:5'])
    );
});

test('the Watchlist includes TV Shows by default and supports bookmarked TV Shows filters', function () {
    $user = User::factory()->create();
    UserMovie::factory()->for($user)->create();
    listedTvShow($user, 5, 'Show', null, '2026-01-01');

    $this->actingAs($user)->get(route('watchlist'))->assertInertia(fn (Assert $page) => $page
        ->has('titles.data', 2)->has('userTvShows.5'));
    $this->get(route('watchlist', ['filter' => 'tv-shows']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filter', 'tv-shows')->has('titles.data', 1)->where('titles.data.0.kind', 'tv'));
});

test('the filter keeps one kind, defaults to both, and unknown or non-text filters are rejected', function () {
    $user = User::factory()->create();
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 1]))->create();
    listedTvShow($user, 2, 'Show', null, '2026-01-01');
    $this->actingAs($user);

    $this->get(route('watchlist', ['filter' => 'movies']))->assertInertia(fn (Assert $page) => $page
        ->where('filter', 'movies')->where('titles.data', fn ($cards) => collect($cards)->pluck('key')->all() === ['1']));
    $this->get(route('watchlist', ['filter' => 'tv-shows']))->assertInertia(fn (Assert $page) => $page
        ->where('filter', 'tv-shows')->where('titles.data', fn ($cards) => collect($cards)->pluck('key')->all() === ['tv:2']));
    $this->get(route('watchlist'))->assertInertia(fn (Assert $page) => $page->where('filter', 'all')->has('titles.data', 2));
    $this->get(route('watchlist', ['filter' => 'documentaries']))->assertUnprocessable();
    $this->get(route('watchlist', ['filter' => ['movies']]))->assertUnprocessable();
});

test('the next page keeps the filter and the sort, and no card is repeated or skipped across kinds', function () {
    $user = User::factory()->create();
    foreach (range(1, 30) as $tmdbId) {
        UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => $tmdbId]))->create(['watchlisted_at' => '2026-01-01']);
        listedTvShow($user, $tmdbId, "Show {$tmdbId}", null, '2026-01-01');
    }
    $this->actingAs($user);

    $first = $this->get(route('watchlist', ['sort' => 'oldest-added']))->assertOk();
    $nextUrl = $first->viewData('page')['props']['titles']['next_page_url'];
    $second = $this->get($nextUrl)->assertOk();
    $keys = collect($first->viewData('page')['props']['titles']['data'])->pluck('key')->merge(collect($second->viewData('page')['props']['titles']['data'])->pluck('key'));

    expect($nextUrl)->toContain('sort=oldest-added')
        ->and($keys)->toHaveCount(60)->and($keys->unique())->toHaveCount(60);

    $filtered = $this->get(route('watchlist', ['filter' => 'tv-shows']))->assertOk();
    expect($filtered->viewData('page')['props']['titles']['data'])->toHaveCount(30)
        ->and($filtered->viewData('page')['props']['titles']['next_page_url'])->toBeNull();
});

test('Watched lists a TV Show once an Episode is watched, ordered by its latest Episode Watched date beside Movies', function () {
    $user = User::factory()->create();
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 1]))->watched()->create(['watched_at' => '2026-02-01']);
    $first = listedTvShow($user, 2, 'Two', null);
    episodeOf($first, $user, '2026-01-01');
    episodeOf($first, $user, '2026-03-01');
    $second = listedTvShow($user, 3, 'Three', null);
    episodeOf($second, $user, '2026-02-15');
    $unwatched = listedTvShow($user, 4, 'Four', null, '2026-01-01');
    episodeOf($unwatched);
    $other = listedTvShow($user, 5, 'Five', null);
    episodeOf($other, User::factory()->create(), '2026-04-01');

    $this->actingAs($user)->get(route('watched'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('titles.data', fn ($cards) => collect($cards)->pluck('key')->all() === ['tv:2', 'tv:3', '1'])
        ->where('titles.data.0.href', $first->canonicalUrl(Surface::Watched)));
    $this->get(route('watched', ['sort' => 'oldest-watched']))->assertInertia(fn (Assert $page) => $page
        ->where('titles.data', fn ($cards) => collect($cards)->pluck('key')->all() === ['1', 'tv:3', 'tv:2']));
});

test('a TV Show with only a Special watched is on Watched, and one only on the Watchlist is not', function () {
    $user = User::factory()->create();
    $tvShow = listedTvShow($user, 2, 'Specials only', null);
    episodeOf($tvShow, $user, '2026-01-01', seasonNumber: 0);
    episodeOf($tvShow);
    listedTvShow($user, 3, 'Watchlist only', null, '2026-01-01');

    $this->actingAs($user)->get(route('watched'))->assertInertia(fn (Assert $page) => $page
        ->where('titles.data', fn ($cards) => collect($cards)->pluck('key')->all() === ['tv:2'])
        ->where('userTvShows.2.watched', true)
        ->where('userTvShows.2.state', 'Not started')
        ->where('userTvShows.2.total', 1)
        ->where('userTvShows.2.watchedCount', 0));
});

test('a TV Show on the Watchlist and Watched appears on both, and Completed leaves the Watchlist only', function () {
    $user = User::factory()->create();
    $tvShow = listedTvShow($user, 2, 'Both', null, '2026-01-01');
    episodeOf($tvShow, $user, '2026-01-05');
    episodeOf($tvShow);
    $this->actingAs($user);

    $this->get(route('watchlist'))->assertInertia(fn (Assert $page) => $page->has('titles.data', 1));
    $this->get(route('watched'))->assertInertia(fn (Assert $page) => $page->has('titles.data', 1)
        ->where('userTvShows.2', ['watchlisted' => true, 'watched' => true, 'watchedDate' => '2026-01-05T00:00:00.000000Z', 'state' => 'In progress', 'watchedCount' => 1, 'total' => 2]));
});

test('the TV facts match the progress the TV Show page shows, for Specials, unaired, removed and other Episodes', function () {
    $user = User::factory()->create();
    $tvShow = listedTvShow($user, 2, 'Mixed', null, '2026-01-01');
    $season = Season::factory()->create(['title_id' => $tvShow->id, 'season_number' => 1]);
    $gone = Season::factory()->create(['title_id' => $tvShow->id, 'season_number' => 2, 'removed_at' => now()]);
    episodeOf($tvShow, $user, '2026-01-02', season: $season);
    episodeOf($tvShow, null, null, season: $season);
    episodeOf($tvShow, $user, '2026-01-03', season: $season, airDate: now()->addYear()->toDateString());
    episodeOf($tvShow, $user, '2026-01-04', season: $gone);
    episodeOf($tvShow, $user, '2026-01-05', seasonNumber: 0);
    Episode::factory()->create(['season_id' => $season->id, 'title_id' => $tvShow->id, 'removed_at' => now(), 'air_date' => '2025-01-01', 'episode_number' => 9]);
    $this->actingAs($user);

    $progress = app(WatchedProgress::class)->for($tvShow, $user);

    $this->get(route('watchlist'))->assertInertia(fn (Assert $page) => $page
        ->where('userTvShows.2.watchedCount', $progress['watched'])
        ->where('userTvShows.2.total', $progress['total'])
        ->where('userTvShows.2.state', $progress['state']->value)
        ->where('userTvShows.2.watchedDate', '2026-01-05T00:00:00.000000Z'));
});

test('a TV Show without details appears as a stub and in the facts', function () {
    $user = User::factory()->create();
    $tvShow = TvShow::factory()->removed()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => now()]);

    $this->actingAs($user)->get(route('watchlist'))->assertInertia(fn (Assert $page) => $page
        ->where('titles.data', [])->has('userTvShows', 1)->has('unavailableTitles', 1));
    $this->get(route('watched'))->assertInertia(fn (Assert $page) => $page->where('titles.data', []));
});

test('a TV Show without details counts toward a nonempty Watchlist', function () {
    $user = User::factory()->create();
    $tvShow = TvShow::factory()->removed()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => now()]);
    fakeDiscoverSource(['trending' => [movieListings(901, 903)]]);
    app(DiscoverBuilder::class)->build(CatalogProfile::Background);

    $this->actingAs($user)->get(route('watchlist'))->assertInertia(fn (Assert $page) => $page->where('trending', []));
});

test('an empty Watchlist keeps its suggestions, but an empty filter does not need them', function () {
    $user = User::factory()->create();
    listedTvShow($user, 2, 'Show', null, '2026-01-01');

    $this->actingAs($user)->get(route('watchlist', ['filter' => 'movies']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('titles.data', [])->where('trending', []));
});

test('a restored Movie is found with its own kind, whatever TV Show shares its id', function () {
    $user = User::factory()->create();
    listedTvShow($user, 9, 'Show', null, '2026-01-01');
    $movie = UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 9]))->create(['watchlisted_at' => '2025-01-01']);
    $this->actingAs($user);

    $response = $this->get(route('watchlist', ['restore' => 9, 'sort' => 'oldest-added']))->assertRedirect();
    $this->get($response->headers->get('Location'))->assertInertia(fn (Assert $page) => $page->where('titles.data.0.key', '9'));
    expect($movie->movie->canonicalUrl(Surface::Watchlist))->not->toBeEmpty();
});

test('a restored Movie keeps the filter of the page it is revealed on', function () {
    $user = User::factory()->create();
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 9]))->create();
    $this->actingAs($user);

    $location = $this->get(route('watchlist', ['restore' => 9, 'filter' => 'movies']))->assertRedirect()->headers->get('Location');

    expect($location)->toContain('filter=movies')->toContain('cursor=');
});
