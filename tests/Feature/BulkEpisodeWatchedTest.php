<?php

use App\Models\Episode;
use App\Models\Season;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserEpisode;
use App\Models\UserTitle;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\EpisodeDetails;
use App\Services\Catalog\Sources\Details\SeasonDetails;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;

function seasonWatchedUrl(Season $season): string
{
    return "/tv-shows/{$season->title_id}/seasons/{$season->id}/watched";
}

function tvShowWatchedUrl(TvShow $tvShow): string
{
    return "/tv-shows/tmdb/{$tvShow->tmdb_id}/watched";
}

function undoUrl(TvShow $tvShow): string
{
    return "/tv-shows/tmdb/{$tvShow->tmdb_id}/undo";
}

/** @return array{TvShow, Season, Season} */
function showWithTwoSeasons(): array
{
    $tvShow = TvShow::factory()->create();
    $one = Season::factory()->for($tvShow, 'title')->create(['season_number' => 1]);
    $two = Season::factory()->for($tvShow, 'title')->create(['season_number' => 2]);
    foreach ([$one, $two] as $season) {
        Episode::factory()->count(2)->for($season)->sequence(['episode_number' => 1], ['episode_number' => 2])->create();
    }

    return [$tvShow, $one, $two];
}

test('a Season mark changes only eligible Episodes and keeps earlier Watched dates', function () {
    [$tvShow, $one] = showWithTwoSeasons();
    $aired = $one->episodes()->orderBy('episode_number')->get();
    Episode::factory()->for($one)->create(['episode_number' => 3, 'air_date' => today()->addDay()]);
    Episode::factory()->for($one)->create(['episode_number' => 4, 'air_date' => null]);
    Episode::factory()->for($one)->create(['episode_number' => 5, 'removed_at' => now()]);
    $user = User::factory()->create();
    UserEpisode::factory()->for($user)->for($aired[0])->create(['watched_at' => '2026-01-01 10:00:00']);
    $this->travelTo('2026-11-08 12:00:00');

    $this->actingAs($user)->putJson(seasonWatchedUrl($one))->assertOk()
        ->assertJsonPath('progress.watched', 2)->assertJsonPath('progress.state', 'In progress');

    $this->assertDatabaseHas('user_episodes', ['episode_id' => $aired[0]->id, 'watched_at' => '2026-01-01 10:00:00']);
    $this->assertDatabaseHas('user_episodes', ['episode_id' => $aired[1]->id, 'watched_at' => '2026-11-08 12:00:00']);
    expect(UserEpisode::query()->count())->toBe(2);
});

test('a Season clear removes only that Season\'s marks for this user', function () {
    [$tvShow, $one, $two] = showWithTwoSeasons();
    $user = User::factory()->create();
    $other = User::factory()->create();
    foreach ($tvShow->episodes as $episode) {
        UserEpisode::factory()->for($user)->for($episode)->create();
        UserEpisode::factory()->for($other)->for($episode)->create();
    }

    $this->actingAs($user)->deleteJson(seasonWatchedUrl($one))->assertOk()->assertJsonPath('progress.watched', 2);

    expect(UserEpisode::query()->where('user_id', $user->id)->pluck('episode_id')->all())->toEqualCanonicalizing($two->episodes->pluck('id')->all());
    expect(UserEpisode::query()->where('user_id', $other->id)->count())->toBe(4);
});

test('Season actions reject a Season of another TV Show, a Removed Season and a Removed TV Show', function (string $case, int $status) {
    [$tvShow, $one] = showWithTwoSeasons();
    $url = seasonWatchedUrl($one);
    match ($case) {
        'foreign' => $url = str_replace("/tv-shows/{$tvShow->id}/", '/tv-shows/'.TvShow::factory()->create()->id.'/', $url),
        'removed season' => $one->update(['removed_at' => now()]),
        'removed show' => $tvShow->update(['removed_at' => now()]),
    };

    $this->actingAs(User::factory()->create())->putJson($url)->assertStatus($status);
    $this->assertDatabaseCount('user_episodes', 0);
})->with([['foreign', 404], ['removed season', 404], ['removed show', 410]]);

test('a whole-TV Show mark marks aired numbered-Season Episodes, completes it and leaves the Watchlist', function () {
    [$tvShow, $one] = showWithTwoSeasons();
    Episode::factory()->for($one)->create(['episode_number' => 9, 'air_date' => today()->addDay()]);
    $user = User::factory()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => '2026-10-01 12:00:00']);

    $this->actingAs($user)->putJson(tvShowWatchedUrl($tvShow))->assertOk()
        ->assertJsonPath('progress.state', 'Completed')->assertJsonPath('progress.watched', 4)->assertJsonPath('watchlisted', false);

    $this->assertDatabaseMissing('user_titles', ['user_id' => $user->id, 'title_id' => $tvShow->id]);
});

test('a new Episode later does not put a Completed TV Show back on the Watchlist', function () {
    [$tvShow, $one] = showWithTwoSeasons();
    $user = User::factory()->create();
    $this->actingAs($user)->putJson(tvShowWatchedUrl($tvShow))->assertOk();

    $new = Episode::factory()->for($one)->create(['episode_number' => 3]);

    $this->putJson(seasonWatchedUrl($one))->assertOk()->assertJsonPath('progress.state', 'Completed');
    $this->deleteJson("/tv-shows/{$tvShow->id}/seasons/{$one->id}/episodes/{$new->id}/watched")->assertOk()->assertJsonPath('progress.state', 'In progress');
    $this->assertDatabaseMissing('user_titles', ['user_id' => $user->id, 'title_id' => $tvShow->id]);
});

test('a whole-TV Show clear removes every mark including Specials and does not re-add the Watchlist', function () {
    [$tvShow] = showWithTwoSeasons();
    $specials = Season::factory()->for($tvShow, 'title')->create(['season_number' => 0]);
    Episode::factory()->for($specials)->create();
    $user = User::factory()->create();
    foreach ($tvShow->episodes()->get() as $episode) {
        UserEpisode::factory()->for($user)->for($episode)->create();
    }

    $this->actingAs($user)->deleteJson(tvShowWatchedUrl($tvShow))->assertOk()->assertJsonPath('progress.state', 'Not started');

    expect(UserEpisode::query()->count())->toBe(0);
    $this->assertDatabaseMissing('user_titles', ['user_id' => $user->id]);
});

test('one Undo restores a bulk mark\'s Episode dates and Watchlist exactly', function () {
    [$tvShow, $one] = showWithTwoSeasons();
    $user = User::factory()->create();
    $first = $one->episodes()->orderBy('episode_number')->first();
    UserEpisode::factory()->for($user)->for($first)->create(['watched_at' => '2026-01-01 10:00:00.123456']);
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => '2026-10-01 12:00:00']);

    $token = $this->actingAs($user)->putJson(tvShowWatchedUrl($tvShow))->assertOk()->json('undoToken');
    expect(UserEpisode::query()->count())->toBe(4);

    $this->postJson(undoUrl($tvShow), ['token' => $token])->assertOk()->assertJsonPath('watchlisted', true);

    expect(UserEpisode::query()->count())->toBe(1);
    expect(UserEpisode::query()->sole()->watched_at->format('Y-m-d H:i:s.u'))->toBe('2026-01-01 10:00:00.123456');
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'watchlisted_at' => '2026-10-01 12:00:00']);
});

test('Undo of a bulk clear restores the cleared dates and refuses when state has since changed', function () {
    [$tvShow, $one] = showWithTwoSeasons();
    $user = User::factory()->create();
    foreach ($one->episodes as $episode) {
        UserEpisode::factory()->for($user)->for($episode)->create(['watched_at' => '2026-02-02 10:00:00']);
    }
    $this->actingAs($user);
    $token = $this->deleteJson(seasonWatchedUrl($one))->assertOk()->json('undoToken');
    UserEpisode::factory()->for($user)->for($one->episodes->first())->create();

    $this->postJson(undoUrl($tvShow), ['token' => $token])->assertUnprocessable()->assertJsonPath('errors.undo.0', "That can't be undone any more");
    expect(UserEpisode::query()->count())->toBe(1);

    $again = $this->deleteJson(seasonWatchedUrl($one))->json('undoToken');
    $this->postJson(undoUrl($tvShow), ['token' => $again])->assertOk();
    expect(UserEpisode::query()->count())->toBe(1);
});

test('a whole-TV Show mark with no Episodes stored refreshes first', function () {
    $tvShow = TvShow::factory()->create(['tmdb_id' => 87108, 'synced_at' => null]);
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(
        87108, title: 'Chernobyl', seasons: [new SeasonDetails(100, 1, episodes: [new EpisodeDetails(200, 1, 1, airDate: '2019-05-06')])],
    )]));

    $this->actingAs(User::factory()->create())->putJson(tvShowWatchedUrl($tvShow))->assertOk()
        ->assertJsonPath('progress.state', 'Completed')->assertJsonPath('progress.watched', 1);
});

test('a whole-TV Show mark whose refresh fails changes nothing', function () {
    $tvShow = TvShow::factory()->create(['tmdb_id' => 87108, 'synced_at' => null]);
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));
    $user = User::factory()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => now()]);

    $this->actingAs($user)->putJson(tvShowWatchedUrl($tvShow))->assertStatus(503);

    $this->assertDatabaseCount('user_episodes', 0);
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $tvShow->id]);
});

test('a guest Season or whole-TV Show action saves nothing and replays once after sign-in', function (string $kind) {
    [$tvShow, $one] = showWithTwoSeasons();
    $user = User::factory()->create();
    $this->get('/about')->assertOk();

    $this->put($kind === 'season' ? seasonWatchedUrl($one) : tvShowWatchedUrl($tvShow))->assertRedirect(route('login'));
    $this->assertDatabaseCount('user_episodes', 0);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/about')->assertSessionMissing('user_movie_actions.pending');
    expect(UserEpisode::query()->where('user_id', $user->id)->count())->toBe($kind === 'season' ? 2 : 4);
})->with(['season', 'tv show']);

test('a guest action on a Removed Season is refused at replay without blocking sign-in', function () {
    [, $one] = showWithTwoSeasons();
    $user = User::factory()->create();
    $this->put(seasonWatchedUrl($one))->assertRedirect(route('login'));
    $one->update(['removed_at' => now()]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('user_episodes', 0);
});

test('a guest cannot queue a mark on a Removed TV Show', function () {
    [$tvShow] = showWithTwoSeasons();
    $tvShow->update(['removed_at' => now()]);

    $this->putJson(tvShowWatchedUrl($tvShow))->assertStatus(410)->assertSessionMissing('user_movie_actions.pending');
});

test('TV Show Watched changes and Undo preserve the current User rating', function (string $scope, string $method, ?int $rating) {
    $this->travelTo('2026-11-09 12:00:00');
    $user = User::factory()->create();
    $tvShow = TvShow::factory()->create();
    $watchlistedAt = $method === 'putJson' ? '2026-11-01 12:00:00' : null;
    $episode = episodeOf($tvShow, $method === 'deleteJson' ? $user : null, '2026-11-02 12:00:00');
    UserTitle::query()->create([
        'user_id' => $user->id, 'title_id' => $tvShow->id, 'rating' => 5, 'watchlisted_at' => $watchlistedAt,
    ]);
    $url = match ($scope) {
        'episode' => route('tv-shows.episodes.watched.update', ['tvShow' => $tvShow->id, 'season' => $episode->season_id, 'episode' => $episode->id]),
        'season' => route('tv-shows.seasons.watched.update', ['tvShow' => $tvShow->id, 'season' => $episode->season_id]),
        'TV Show' => route('tv-shows.watched.update', ['tmdbId' => $tvShow->tmdb_id]),
    };
    $this->actingAs($user);

    $token = $this->{$method}($url)->assertOk()->assertJsonPath('progress.state', $method === 'putJson' ? 'Completed' : 'Not started')->json('undoToken');

    $this->assertDatabaseHas('user_titles', [
        'user_id' => $user->id, 'title_id' => $tvShow->id, 'rating' => 5,
        'watchlisted_at' => null, 'watched_at' => null,
    ]);
    $this->putJson(route('titles.rating.update', ['title' => $tvShow->id]), ['rating' => $rating])->assertNoContent();

    $this->postJson(route('tv-shows.undo', ['tmdbId' => $tvShow->tmdb_id]), ['token' => $token])
        ->assertOk()->assertJsonPath('watchlisted', $watchlistedAt !== null)
        ->assertJsonPath('progress.state', $method === 'putJson' ? 'Not started' : 'Completed');

    if ($rating === null && $watchlistedAt === null) {
        $this->assertDatabaseMissing('user_titles', ['user_id' => $user->id, 'title_id' => $tvShow->id]);
    } else {
        $this->assertDatabaseHas('user_titles', [
            'user_id' => $user->id, 'title_id' => $tvShow->id, 'rating' => $rating,
            'watchlisted_at' => $watchlistedAt, 'watched_at' => null,
        ]);
    }
    if ($method === 'putJson') {
        $this->assertDatabaseMissing('user_episodes', ['user_id' => $user->id, 'episode_id' => $episode->id]);
    } else {
        $this->assertDatabaseHas('user_episodes', [
            'user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => '2026-11-02 12:00:00',
        ]);
    }
})->with(['episode', 'season', 'TV Show'])->with(['putJson', 'deleteJson'])->with(['changed rating' => 9, 'cleared rating' => null]);
