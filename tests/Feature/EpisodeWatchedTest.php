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
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

function episodeWatchedUrl(Episode $episode): string
{
    return "/tv-shows/{$episode->title_id}/seasons/{$episode->season_id}/episodes/{$episode->id}/watched";
}

test('marking an Episode records the current Watched date and marking again records a rewatch', function () {
    config(['catalog.tv_shows.enabled' => true]);
    $episode = Episode::factory()->create();
    $user = User::factory()->create();
    $this->travelTo('2026-11-08 12:00:00');
    $this->actingAs($user)->putJson(episodeWatchedUrl($episode))->assertOk()
        ->assertJsonPath('progress.state', 'Completed')->assertJsonPath('progress.watched', 1);
    $this->assertDatabaseHas('user_episodes', ['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => '2026-11-08 12:00:00']);
    $this->travelTo('2026-11-09 12:00:00');
    $this->putJson(episodeWatchedUrl($episode))->assertOk();
    $this->assertDatabaseHas('user_episodes', ['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => '2026-11-09 12:00:00']);
});

test('Episode actions reject mismatched parents, removed inventory, and unaired Episodes', function (string $case, int $status) {
    config(['catalog.tv_shows.enabled' => true]);
    $episode = Episode::factory()->create();
    $url = episodeWatchedUrl($episode);
    match ($case) {
        'season' => $url = str_replace("/seasons/{$episode->season_id}/", '/seasons/'.Episode::factory()->create()->season_id.'/', $url),
        'tv show' => $url = str_replace("/tv-shows/{$episode->title_id}/", '/tv-shows/'.Episode::factory()->create()->title_id.'/', $url),
        'removed episode' => $episode->update(['removed_at' => now()]),
        'removed season' => $episode->season->update(['removed_at' => now()]),
        'removed tv show' => $episode->title->update(['removed_at' => now()]),
        'unaired' => $episode->update(['air_date' => today()->addDay()]),
        'undated' => $episode->update(['air_date' => null]),
        'flag off' => config(['catalog.tv_shows.enabled' => false]),
    };
    $response = $this->actingAs(User::factory()->create())->putJson($url)->assertStatus($status);
    if ($status === 422) {
        $response->assertJsonPath('errors.episode.0', 'This Episode has not aired yet.');
    }
    $this->assertDatabaseCount('user_episodes', 0);
})->with(['season' => ['season', 404], 'tv show' => ['tv show', 404], 'removed episode' => ['removed episode', 404],
    'removed season' => ['removed season', 404], 'removed tv show' => ['removed tv show', 404], 'unaired' => ['unaired', 422], 'undated' => ['undated', 422], 'flag off' => ['flag off', 404]]);

test('unmarking only clears the signed-in user and Undo restores the precise earlier date', function () {
    config(['catalog.tv_shows.enabled' => true]);
    $episode = Episode::factory()->create();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($other)->putJson(episodeWatchedUrl($episode))->assertOk();
    $this->travelTo('2026-11-01 12:34:56');
    $this->actingAs($user)->putJson(episodeWatchedUrl($episode))->assertOk();
    $this->travelTo('2026-11-08 12:00:00');
    $response = $this->deleteJson(episodeWatchedUrl($episode))->assertOk()->assertJsonPath('progress.state', 'Not started');
    $this->assertDatabaseMissing('user_episodes', ['user_id' => $user->id]);
    $this->assertDatabaseHas('user_episodes', ['user_id' => $other->id]);
    $this->postJson("/tv-shows/tmdb/{$episode->title->tmdb_id}/undo", ['token' => $response->json('undoToken')])->assertOk();
    $this->assertDatabaseHas('user_episodes', ['user_id' => $user->id, 'watched_at' => '2026-11-01 12:34:56']);
});

test('Undo refuses an older action and changed facts without overwriting them', function () {
    config(['catalog.tv_shows.enabled' => true]);
    $episode = Episode::factory()->create();
    $this->actingAs(User::factory()->create());
    $first = $this->putJson(episodeWatchedUrl($episode))->assertOk()->json('undoToken');
    $second = $this->deleteJson(episodeWatchedUrl($episode))->assertOk()->json('undoToken');
    $url = "/tv-shows/tmdb/{$episode->title->tmdb_id}/undo";
    $this->postJson($url, ['token' => $first])->assertUnprocessable()->assertJsonPath('errors.undo.0', "That can't be undone any more");
    $this->postJson($url, ['token' => $second])->assertOk();
    $this->postJson($url, ['token' => $second])->assertUnprocessable();
    $third = $this->deleteJson(episodeWatchedUrl($episode))->json('undoToken');
    UserEpisode::factory()->for(auth()->user())->for($episode)->create();
    $this->postJson($url, ['token' => $third])->assertUnprocessable();
    $this->assertDatabaseCount('user_episodes', 1);
});

test('completion ignores ineligible inventory, removes the Watchlist fact, and Undo restores it', function () {
    config(['catalog.tv_shows.enabled' => true]);
    $episode = Episode::factory()->create();
    $tvShow = TvShow::query()->findOrFail($episode->title_id);
    Episode::factory()->for($episode->season)->create(['air_date' => null]);
    Episode::factory()->for($episode->season)->create(['air_date' => today()->addDay()]);
    Episode::factory()->for($episode->season)->create(['removed_at' => now()]);
    Episode::factory()->for(Season::factory()->for($tvShow, 'title')->state(['season_number' => 0]))->create();
    Episode::factory()->for(Season::factory()->for($tvShow, 'title')->state(['removed_at' => now()]))->create();
    $user = User::factory()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => '2026-10-01 12:00:00']);
    $response = $this->actingAs($user)->putJson(episodeWatchedUrl($episode))->assertOk()
        ->assertJsonPath('progress.total', 1)->assertJsonPath('progress.state', 'Completed');
    $this->assertDatabaseMissing('user_titles', ['user_id' => $user->id, 'title_id' => $tvShow->id]);
    $this->postJson("/tv-shows/tmdb/{$tvShow->tmdb_id}/undo", ['token' => $response->json('undoToken')])->assertOk();
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'watchlisted_at' => '2026-10-01 12:00:00']);
});

test('a guest Episode action saves nothing and replays once after sign-in', function () {
    config(['catalog.tv_shows.enabled' => true]);
    $episode = Episode::factory()->create();
    $user = User::factory()->create();
    $this->get('/about')->assertOk();
    $this->put(episodeWatchedUrl($episode))->assertRedirect(route('login'));
    $this->assertDatabaseCount('user_episodes', 0);
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/about')->assertSessionMissing('user_movie_actions.pending');
    $this->assertDatabaseHas('user_episodes', ['user_id' => $user->id, 'episode_id' => $episode->id]);
});

test('a Completed TV Show queued for a rewatch stays on the Watchlist when marking an Episode again', function () {
    config(['catalog.tv_shows.enabled' => true]);
    $episode = Episode::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user)->putJson(episodeWatchedUrl($episode))->assertOk();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $episode->title_id, 'watchlisted_at' => now()]);
    $this->putJson(episodeWatchedUrl($episode))->assertOk();
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $episode->title_id]);
});

test('the page derives progress and opens the next Season, with Specials last and excluded', function () {
    config(['catalog.tv_shows.enabled' => true]);
    $source = new FakeCatalogSource(tvShows: [new TvShowDetails(
        87108, title: 'Chernobyl', seasons: [
            new SeasonDetails(100, 0, episodes: [new EpisodeDetails(200, 0, 1, airDate: '2019-01-01')]),
            new SeasonDetails(101, 1, episodes: [new EpisodeDetails(201, 1, 1, airDate: '2019-01-01')]),
            new SeasonDetails(102, 2, episodes: [new EpisodeDetails(202, 2, 1, airDate: '2019-01-01')]),
        ],
    )]);
    app()->instance(CatalogSource::class, $source);
    $this->get('/tv-shows/tmdb/87108')->assertStatus(301);
    $tvShow = TvShow::query()->sole();
    $episodes = $tvShow->episodes()->orderBy('season_number')->get();
    $this->actingAs(User::factory()->create());
    $this->get($tvShow->canonicalUrl())->assertInertia(fn (AssertableInertia $page) => $page
        ->where('progress.state', 'Not started')->where('progress.defaultOpenSeasonId', $episodes[1]->season_id)
        ->where('progress.total', 2)->where('progress.seasons.2.number', 0));
    $this->putJson(episodeWatchedUrl($episodes[0]))->assertOk()->assertJsonPath('progress.watched', 0);
    $this->putJson(episodeWatchedUrl($episodes[1]))->assertOk()->assertJsonPath('progress.state', 'In progress')
        ->assertJsonPath('progress.defaultOpenSeasonId', $episodes[2]->season_id);
    $this->putJson(episodeWatchedUrl($episodes[2]))->assertOk()->assertJsonPath('progress.state', 'Completed')
        ->assertJsonPath('progress.defaultOpenSeasonId', $episodes[2]->season_id);
    $this->travel(1)->days();
    $new = Episode::factory()->for($episodes[2]->season)->create(['air_date' => today()]);
    $this->deleteJson(episodeWatchedUrl($episodes[0]))->assertOk()->assertJsonPath('progress.state', 'In progress')
        ->assertJsonPath('progress.watched', 2)->assertJsonPath('progress.total', 3);
    $this->assertDatabaseMissing('user_titles', ['title_id' => $tvShow->id]);
});

test('a guest action expires or refuses removed inventory and never blocks sign-in', function (string $change) {
    config(['catalog.tv_shows.enabled' => true]);
    $episode = Episode::factory()->create();
    $user = User::factory()->create();
    $this->put(episodeWatchedUrl($episode))->assertRedirect(route('login'));
    match ($change) {
        'expired' => $this->travel(31)->minutes(),
        'removed' => $episode->update(['removed_at' => now()]),
        'disabled' => config(['catalog.tv_shows.enabled' => false]),
    };
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect()->assertSessionMissing('user_movie_actions.pending');
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('user_episodes', 0);
})->with(['expired', 'removed', 'disabled']);

test('Undo refuses another user, a wrong token and an expired action', function () {
    $episode = Episode::factory()->create();
    $user = User::factory()->create();
    $token = $this->actingAs($user)->putJson(episodeWatchedUrl($episode))->assertOk()->json('undoToken');
    $url = "/tv-shows/tmdb/{$episode->title->tmdb_id}/undo";
    $this->actingAs(User::factory()->create())->postJson($url, ['token' => $token])->assertUnprocessable();
    $this->actingAs($user)->postJson($url, ['token' => (string) Str::uuid()])->assertUnprocessable();
    $this->travel(31)->minutes();
    $this->postJson($url, ['token' => $token])->assertUnprocessable();
    $this->assertDatabaseHas('user_episodes', ['user_id' => $user->id, 'episode_id' => $episode->id]);
});
