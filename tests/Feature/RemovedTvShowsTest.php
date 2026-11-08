<?php

use App\Models\Episode;
use App\Models\Season;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserEpisode;
use App\Models\UserTitle;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Inertia\Testing\AssertableInertia as Assert;

test('a Removed TV Show renders a private title-less 410 without fetching or recording a view', function (bool $signedIn) {
    $tvShow = TvShow::factory()->removed()->create(['last_viewed_at' => '2025-01-01']);
    $user = User::factory()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => now()]);
    $season = Season::factory()->create(['title_id' => $tvShow->id]);
    $episode = Episode::factory()->create(['season_id' => $season->id]);
    UserEpisode::query()->create(['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => now()]);
    $source = new FakeCatalogSource;
    app()->instance(CatalogSource::class, $source);
    if ($signedIn) {
        $this->actingAs($user);
    }

    $this->get($tvShow->canonicalUrl())->assertGone()->assertHeader('X-Robots-Tag', 'noindex')->assertInertia(fn (Assert $page) => $page
        ->component('Error')->where('title', 'This TV show is no longer available.')
        ->where('data.kind', 'tv')->where('data.watchlisted', $signedIn)->where('data.watchedCount', $signedIn ? 1 : 0)
        ->missing('tvShow')->missing('progress'));
    expect($source->tvShowCalls)->toBe([]);
    expect($tvShow->fresh()->last_viewed_at->toDateString())->toBe('2025-01-01');
})->with([true, false]);

test('Removed TV Shows join the unpaginated group and facts for each filter', function (string $list, string $date) {
    $user = User::factory()->create();
    $tvShow = TvShow::factory()->removed()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => '2026-01-01']);
    $episode = Episode::factory()->create(['season_id' => Season::factory()->create(['title_id' => $tvShow->id])->id]);
    UserEpisode::query()->create(['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => '2026-02-01']);
    $this->actingAs($user);

    foreach (['all', 'tv-shows'] as $filter) {
        $this->get(route($list, ['filter' => $filter]))->assertInertia(fn (Assert $page) => $page
            ->has('unavailableTitles', 1)->where('unavailableTitles.0.kind', 'tv')->where('unavailableTitles.0.title', 'Removed TV show')
            ->where('unavailableTitles.0.date', $date)->where('unavailableTitles.0.posterPath', null)
            ->where('userTvShows.'.$tvShow->tmdb_id.'.watchlisted', true)->where('userTvShows.'.$tvShow->tmdb_id.'.watched', true)
            ->where('titles.data', [])->where('trending', []));
    }
    $this->get(route($list, ['filter' => 'movies']))->assertInertia(fn (Assert $page) => $page->where('unavailableTitles', []));
})->with([['watchlist', '2026-01-01T00:00:00.000000Z'], ['watched', '2026-02-01T00:00:00.000000Z']]);

test('Removed TV Show cleanup has one Undo restoring all original dates', function (string $list) {
    $user = User::factory()->create();
    $show = TvShow::factory()->removed()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $show->id, 'watchlisted_at' => '2026-01-01']);
    $episode = Episode::factory()->create(['season_id' => Season::factory()->create(['title_id' => $show->id, 'season_number' => 0])->id]);
    UserEpisode::query()->create(['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => '2026-02-01']);
    $this->actingAs($user);

    $result = $this->deleteJson(route('tv-shows.'.$list.'.destroy', ['tmdbId' => $show->tmdb_id]), ['source' => 'watchlist'])->assertOk();
    $this->get(route($list))->assertInertia(fn (Assert $page) => $page->where('unavailableTitles', []));
    $this->postJson(route('tv-shows.undo', ['tmdbId' => $show->tmdb_id]), ['token' => $result->json('undoToken')])->assertOk();
    $this->get(route($list))->assertInertia(fn (Assert $page) => $page->has('unavailableTitles', 1)
        ->where('unavailableTitles.0.date', $list === 'watchlist' ? '2026-01-01T00:00:00.000000Z' : '2026-02-01T00:00:00.000000Z'));
})->with(['watchlist', 'watched']);

test('setting a fact on a Removed TV Show gives the shared 410', function (string $scope) {
    $user = User::factory()->create();
    $show = TvShow::factory()->removed()->create();
    $season = Season::factory()->create(['title_id' => $show->id]);
    $episode = Episode::factory()->create(['season_id' => $season->id]);
    $url = match ($scope) {
        'watchlist', 'watched' => route('tv-shows.'.$scope.'.update', ['tmdbId' => $show->tmdb_id]),
        'season' => route('tv-shows.seasons.watched.update', ['tvShow' => $show->id, 'season' => $season->id]),
        'episode' => route('tv-shows.episodes.watched.update', ['tvShow' => $show->id, 'season' => $season->id, 'episode' => $episode->id]),
    };

    $this->actingAs($user)->put($url, $scope === 'watchlist' ? ['source' => 'tv-show'] : [])->assertGone()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('title', 'This TV show is no longer available.'));
    $this->assertDatabaseCount('user_episodes', 0);
    $this->assertDatabaseCount('user_titles', 0);
})->with(['watchlist', 'watched', 'season', 'episode']);

test('a cap-cleared TV Show whose refresh fails renders the 503 with facts and no view', function () {
    $show = TvShow::factory()->capCleared()->create(['last_viewed_at' => '2025-01-01']);
    $user = User::factory()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $show->id, 'watchlisted_at' => now()]);
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->actingAs($user)->get($show->canonicalUrl())->assertServiceUnavailable()->assertHeader('Retry-After', '30')
        ->assertHeader('X-Robots-Tag', 'noindex')->assertInertia(fn (Assert $page) => $page->component('Error')
        ->where('data.kind', 'tv')->where('data.watchlisted', true)->missing('tvShow'));
    expect($show->fresh()->last_viewed_at->toDateString())->toBe('2025-01-01');
});

test('a refused stale action keeps the signed-in viewers cleanup facts on the 410', function (string $scope) {
    $user = User::factory()->create();
    $show = $scope === 'refresh' ? TvShow::factory()->create() : TvShow::factory()->removed()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $show->id, 'watchlisted_at' => '2026-01-01']);
    if ($scope === 'refresh') {
        $url = route('tv-shows.watched.update', ['tmdbId' => $show->tmdb_id]);
    } else {
        $season = Season::factory()->create(['title_id' => $show->id]);
        $episode = Episode::factory()->create(['season_id' => $season->id]);
        UserEpisode::query()->create(['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => '2026-02-01']);
        $url = $scope === 'season' ? route('tv-shows.seasons.watched.update', ['tvShow' => $show->id, 'season' => $season->id])
            : route('tv-shows.episodes.watched.update', ['tvShow' => $show->id, 'season' => $season->id, 'episode' => $episode->id]);
    }

    $this->actingAs($user)->put($url)->assertGone()->assertInertia(fn (Assert $page) => $page
        ->where('data.watchlisted', true)->where('data.watchedCount', $scope === 'refresh' ? 0 : 1));
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $show->id, 'watchlisted_at' => '2026-01-01']);
})->with(['season', 'episode', 'refresh']);
