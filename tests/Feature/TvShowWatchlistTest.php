<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserEpisode;
use App\Models\UserTitle;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;

test('a signed-in user adds a TV Show without a local row by catalog id', function () {
    $this->travelTo('2026-11-08 12:00:00');
    $user = User::factory()->create();
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(87108, title: 'Chernobyl')]));

    $this->actingAs($user)->putJson('/tv-shows/tmdb/87108/watchlist', ['source' => 'tv-show'])
        ->assertOk()->assertJsonPath('watchlisted', true);

    $tvShow = TvShow::query()->sole();
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => '2026-11-08 12:00:00', 'watched_at' => null]);
});

test('removing a TV Show and Undo restore its exact Date added without changing Episode marks', function () {
    $this->travelTo('2026-11-08 12:00:00');
    $episode = Episode::factory()->create();
    $tvShow = TvShow::query()->findOrFail($episode->title_id);
    $user = User::factory()->create();
    UserEpisode::factory()->for($user)->for($episode)->create(['watched_at' => '2026-10-01 08:00:00']);
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => '2026-10-02 09:00:00']);

    $response = $this->actingAs($user)->deleteJson("/tv-shows/tmdb/{$tvShow->tmdb_id}/watchlist", ['source' => 'tv-show'])
        ->assertOk()->assertJsonPath('watchlisted', false);
    $this->assertDatabaseMissing('user_titles', ['user_id' => $user->id]);
    $this->postJson("/tv-shows/tmdb/{$tvShow->tmdb_id}/undo", ['token' => $response->json('undoToken')])
        ->assertOk()->assertJsonPath('watchlisted', true);

    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => '2026-10-02 09:00:00']);
    $this->assertDatabaseHas('user_episodes', ['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => '2026-10-01 08:00:00']);
});

test('a guest add creates nothing and replays once after signing in', function () {
    $user = User::factory()->unverified()->create();
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(87108, title: 'Chernobyl')]));
    $this->get('/about')->assertOk();
    $this->put('/tv-shows/tmdb/87108/watchlist', ['source' => 'tv-show'])->assertRedirect(route('login'));
    $this->assertDatabaseCount('titles', 0);
    $this->assertDatabaseCount('user_titles', 0);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/about')->assertSessionMissing('user_movie_actions.pending');

    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => TvShow::query()->sole()->id]);
});

test('a Completed TV Show can be queued for rewatch with a fresh Date added and unchanged Episode dates', function () {
    $this->travelTo('2026-11-08 12:00:00');
    $mark = UserEpisode::factory()->create(['watched_at' => '2026-10-01 08:00:00']);
    $tvShow = TvShow::query()->findOrFail($mark->episode->title_id);
    $url = "/tv-shows/tmdb/{$tvShow->tmdb_id}/watchlist";
    $this->actingAs($mark->user)->putJson($url, ['source' => 'tv-show'])->assertOk()->assertJsonPath('progress.state', 'Completed');
    $this->assertDatabaseHas('user_titles', ['user_id' => $mark->user_id, 'title_id' => $tvShow->id, 'watchlisted_at' => '2026-11-08 12:00:00']);
    $this->travel(1)->days();
    $this->putJson($url, ['source' => 'tv-show'])->assertOk();
    $this->assertDatabaseHas('user_titles', ['user_id' => $mark->user_id, 'watchlisted_at' => '2026-11-08 12:00:00']);
    $this->deleteJson($url, ['source' => 'tv-show'])->assertOk();
    $this->putJson($url, ['source' => 'tv-show'])->assertOk();
    $this->assertDatabaseHas('user_titles', ['user_id' => $mark->user_id, 'watchlisted_at' => '2026-11-09 12:00:00']);
    $this->assertDatabaseHas('user_episodes', ['id' => $mark->id, 'watched_at' => '2026-10-01 08:00:00']);
});

test('removing an unknown TV Show creates nothing, does not fetch, and offers a harmless Undo', function () {
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));
    $response = $this->actingAs(User::factory()->create())->deleteJson('/tv-shows/tmdb/87108/watchlist', ['source' => 'search'])
        ->assertOk()->assertJsonPath('watchlisted', false);
    $this->postJson('/tv-shows/tmdb/87108/undo', ['token' => $response->json('undoToken')])->assertOk()->assertJsonPath('watchlisted', false);
    $this->assertDatabaseCount('titles', 0);
    $this->assertDatabaseCount('user_titles', 0);
});

test('a Removed TV Show cannot be added but can be removed and restored by Undo', function () {
    $tvShow = TvShow::factory()->removed()->create();
    $user = User::factory()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => '2026-10-01 08:00:00']);
    $url = "/tv-shows/tmdb/{$tvShow->tmdb_id}/watchlist";
    $this->actingAs($user)->putJson($url, ['source' => 'watchlist'])->assertGone();
    $response = $this->deleteJson($url, ['source' => 'watchlist'])->assertOk();
    $this->assertDatabaseMissing('user_titles', ['user_id' => $user->id]);
    $this->postJson("/tv-shows/tmdb/{$tvShow->tmdb_id}/undo", ['token' => $response->json('undoToken')])->assertOk();
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'watchlisted_at' => '2026-10-01 08:00:00']);
});

test('Watchlist actions never touch another user or a Movie with the same TMDB id', function () {
    $tvShow = TvShow::factory()->create(['tmdb_id' => 87108]);
    $movie = Movie::factory()->create(['tmdb_id' => 87108]);
    $other = User::factory()->create();
    $user = User::factory()->create();
    UserTitle::query()->create(['user_id' => $other->id, 'title_id' => $tvShow->id, 'watchlisted_at' => now()]);
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $movie->id, 'watchlisted_at' => now()]);
    $this->actingAs($user)->deleteJson('/tv-shows/tmdb/87108/watchlist', ['source' => 'tv-show', 'user_id' => $other->id])->assertOk();
    $this->assertDatabaseHas('user_titles', ['user_id' => $other->id, 'title_id' => $tvShow->id]);
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $movie->id]);
    $this->putJson('/tv-shows/tmdb/87108/watchlist', ['source' => 'tv-show'])->assertOk();
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $tvShow->id]);
});

test('Episode and Watchlist actions invalidate older TV Show Undo tokens', function (bool $episodeFirst) {
    $episode = Episode::factory()->create();
    $tvShow = TvShow::query()->findOrFail($episode->title_id);
    $user = User::factory()->create();
    $episodeUrl = "/tv-shows/{$tvShow->id}/seasons/{$episode->season_id}/episodes/{$episode->id}/watched";
    $watchlistUrl = "/tv-shows/tmdb/{$tvShow->tmdb_id}/watchlist";
    $this->actingAs($user);
    $old = $this->putJson($episodeFirst ? $episodeUrl : $watchlistUrl, ['source' => 'tv-show'])->assertOk()->json('undoToken');
    $latest = $this->putJson($episodeFirst ? $watchlistUrl : $episodeUrl, ['source' => 'tv-show'])->assertOk()->json('undoToken');
    $undoUrl = "/tv-shows/tmdb/{$tvShow->tmdb_id}/undo";
    $this->postJson($undoUrl, ['token' => $old])->assertUnprocessable()->assertJsonPath('errors.undo.0', "That can't be undone any more");
    $this->postJson($undoUrl, ['token' => $latest])->assertOk();
    $this->assertDatabaseHas($episodeFirst ? 'user_episodes' : 'user_titles', ['user_id' => $user->id]);
    $this->assertDatabaseMissing($episodeFirst ? 'user_titles' : 'user_episodes', ['user_id' => $user->id]);
})->with([true, false]);

test('TV Watchlist creation shares the Movie entry budget', function () {
    $this->freezeTime();
    config(['catalog.creation_limit.attempts' => 1]);
    $this->actingAs(User::factory()->create());
    $this->get('/movies/tmdb/123')->assertNotFound();
    $this->putJson('/tv-shows/tmdb/87108/watchlist', ['source' => 'search'])->assertTooManyRequests()->assertHeader('Retry-After');
    $this->assertDatabaseCount('titles', 0);
});

test('TV and Movie Watchlist actions share sixty requests per minute', function () {
    $this->freezeTime();
    $tvShow = TvShow::factory()->create();
    $movie = Movie::factory()->create();
    $this->actingAs(User::factory()->create());
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie'])->assertRedirect();
        $this->putJson("/tv-shows/tmdb/{$tvShow->tmdb_id}/watchlist", ['source' => 'tv-show'])->assertOk();
    }
    $this->deleteJson("/tv-shows/tmdb/{$tvShow->tmdb_id}/watchlist", ['source' => 'tv-show'])->assertTooManyRequests()->assertHeader('Retry-After');
});

test('invalid TV catalog ids cannot be stored as pending guest actions', function (string $id) {
    $this->putJson("/tv-shows/tmdb/{$id}/watchlist", ['source' => 'tv-show'])->assertNotFound()->assertSessionMissing('user_movie_actions.pending');
    $this->assertDatabaseCount('titles', 0);
})->with(['0', '-1', 'nope', '999999999999999999999999']);

test('a Watchlist action requires a known TV source', function (array $data, string $message) {
    $this->putJson('/tv-shows/tmdb/87108/watchlist', $data)->assertUnprocessable()->assertJsonPath('errors.source.0', $message)
        ->assertSessionMissing('user_movie_actions.pending');
    $this->assertDatabaseCount('user_titles', 0);
})->with([
    'missing' => [[], 'The source field is required.'],
    'unknown' => [['source' => 'homepage'], 'The selected source is invalid.'],
]);

test('the disabled flag makes Watchlist actions inert', function (string $method) {
    config(['catalog.tv_shows.enabled' => false]);
    $this->{$method}('/tv-shows/tmdb/87108/watchlist', ['source' => 'tv-show'])->assertNotFound()->assertSessionMissing('user_movie_actions.pending');
    $this->assertDatabaseCount('titles', 0);
})->with(['putJson', 'deleteJson']);

test('catalog outages and unknown TV Shows leave no Watchlist facts', function (bool $outage) {
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: $outage));
    $this->actingAs(User::factory()->create())->putJson('/tv-shows/tmdb/87108/watchlist', ['source' => 'search'])->assertStatus($outage ? 503 : 404);
    $this->assertDatabaseCount('titles', 0);
    $this->assertDatabaseCount('user_titles', 0);
})->with([true, false]);

test('Undo recreates a purged TV Show under the shared catalog rules and restores its Date added', function () {
    $tvShow = TvShow::factory()->create(['tmdb_id' => 87108]);
    $user = User::factory()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => '2026-10-01 08:00:00']);
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(87108, title: 'Chernobyl')]));
    $token = $this->actingAs($user)->deleteJson('/tv-shows/tmdb/87108/watchlist', ['source' => 'watchlist'])->assertOk()->json('undoToken');
    $tvShow->delete();

    $this->postJson('/tv-shows/tmdb/87108/undo', ['token' => $token])->assertOk()->assertJsonPath('watchlisted', true);

    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => TvShow::query()->sole()->id, 'watchlisted_at' => '2026-10-01 08:00:00']);
});

test('failed or expired TV Watchlist guest replay leaves no facts and still signs in', function (string $failure) {
    $user = User::factory()->create();
    $source = new FakeCatalogSource(tvShows: [new TvShowDetails(87108, title: 'Chernobyl')]);
    app()->instance(CatalogSource::class, $source);
    $this->put('/tv-shows/tmdb/87108/watchlist', ['source' => 'tv-show'])->assertRedirect(route('login'));
    match ($failure) {
        'expired' => $this->travel(31)->minutes(),
        'outage' => $source->failing = true,
        'disabled' => config(['catalog.tv_shows.enabled' => false]),
        'removed' => TvShow::factory()->removed()->create(['tmdb_id' => 87108]),
    };

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect()->assertSessionMissing('user_movie_actions.pending');

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('user_titles', 0);
})->with(['expired', 'outage', 'disabled', 'removed']);

test('TV Show Watchlist Undo preserves the current User rating', function (string $method, ?string $watchlistedAt, ?int $rating) {
    $this->travelTo('2026-11-09 12:00:00');
    $user = User::factory()->create();
    $tvShow = TvShow::factory()->create();
    UserTitle::query()->create([
        'user_id' => $user->id, 'title_id' => $tvShow->id, 'rating' => 5, 'watchlisted_at' => $watchlistedAt,
    ]);
    $this->actingAs($user);
    $token = $this->{$method}("/tv-shows/tmdb/{$tvShow->tmdb_id}/watchlist", ['source' => 'tv-show'])->assertOk()->json('undoToken');
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $tvShow->id, 'rating' => 5]);
    $this->putJson(route('titles.rating.update', ['title' => $tvShow->id]), ['rating' => $rating])->assertNoContent();

    $this->postJson(route('tv-shows.undo', ['tmdbId' => $tvShow->tmdb_id]), ['token' => $token])
        ->assertOk()->assertJsonPath('watchlisted', $watchlistedAt !== null);

    if ($rating === null && $watchlistedAt === null) {
        $this->assertDatabaseMissing('user_titles', ['user_id' => $user->id, 'title_id' => $tvShow->id]);
    } else {
        $this->assertDatabaseHas('user_titles', [
            'user_id' => $user->id, 'title_id' => $tvShow->id, 'rating' => $rating,
            'watchlisted_at' => $watchlistedAt, 'watched_at' => null,
        ]);
    }
})->with(['add' => ['putJson', null], 'remove' => ['deleteJson', '2026-11-01 12:00:00']])
    ->with(['changed rating' => 9, 'cleared rating' => null]);
