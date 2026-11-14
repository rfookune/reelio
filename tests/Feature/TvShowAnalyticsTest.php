<?php

use App\Jobs\RecordAnalyticsEvent;
use App\Models\Episode;
use App\Models\Season;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserEpisode;
use App\Models\UserTitle;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Illuminate\Support\Facades\Queue;

test('TV Watchlist actions and Undo record their documented properties once', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $tvShow = TvShow::factory()->create();
    $user = User::factory()->create();
    $url = "/tv-shows/tmdb/{$tvShow->tmdb_id}";

    $this->actingAs($user)->putJson("{$url}/watchlist", ['source' => 'search'])->assertOk();
    $response = $this->deleteJson("{$url}/watchlist", ['source' => 'watchlist'])->assertOk();
    $this->postJson("{$url}/undo", ['token' => $response->json('undoToken')])->assertOk();
    $this->postJson("{$url}/undo", ['token' => $response->json('undoToken')])->assertUnprocessable();

    expect(Queue::pushed(RecordAnalyticsEvent::class)->map(fn ($job) => [$job->name, $job->properties])->all())->toBe([
        ['tv_show_watchlisted', ['tv_show_id' => $tvShow->id, 'source' => 'search']],
        ['tv_show_unwatchlisted', ['tv_show_id' => $tvShow->id, 'source' => 'watchlist']],
        ['tv_show_action_undone', ['tv_show_id' => $tvShow->id, 'action' => 'remove_from_watchlist']],
    ]);
});

test('Episode marks, completion, clear and Undo record local ids and the prior Watchlist state', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $episode = Episode::factory()->create();
    $tvShow = TvShow::query()->findOrFail($episode->title_id);
    $user = User::factory()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => now()]);
    $url = "/tv-shows/{$tvShow->id}/seasons/{$episode->season_id}/episodes/{$episode->id}/watched";

    $this->actingAs($user)->putJson($url)->assertOk()->assertJsonPath('progress.state', 'Completed');
    $response = $this->deleteJson($url)->assertOk()->assertJsonPath('progress.watched', 0);
    $this->postJson("/tv-shows/tmdb/{$tvShow->tmdb_id}/undo", ['token' => $response->json('undoToken')])->assertOk();

    expect(Queue::pushed(RecordAnalyticsEvent::class)->map(fn ($job) => [$job->name, $job->properties])->all())->toBe([
        ['episode_marked_watched', ['tv_show_id' => $tvShow->id, 'season_id' => $episode->season_id, 'episode_id' => $episode->id, 'source' => 'tv-show', 'was_on_watchlist' => true]],
        ['tv_show_completed', ['tv_show_id' => $tvShow->id]],
        ['episode_unmarked_watched', ['tv_show_id' => $tvShow->id, 'season_id' => $episode->season_id, 'episode_id' => $episode->id, 'source' => 'tv-show', 'was_on_watchlist' => false]],
        ['tv_show_action_undone', ['tv_show_id' => $tvShow->id, 'action' => 'clear_watched', 'episode_id' => $episode->id]],
    ]);
});

test('bulk actions emit one count of changed Episodes and scoped Undo properties', function (string $scope) {
    Queue::fake([RecordAnalyticsEvent::class]);
    $season = Season::factory()->create();
    $episodes = Episode::factory()->count(3)->for($season)->sequence(['episode_number' => 1], ['episode_number' => 2], ['episode_number' => 3])->create();
    $tvShow = TvShow::query()->findOrFail($season->title_id);
    $user = User::factory()->create();
    UserEpisode::factory()->for($user)->for($episodes[0])->create();
    $url = $scope === 'season' ? "/tv-shows/{$tvShow->id}/seasons/{$season->id}/watched" : "/tv-shows/tmdb/{$tvShow->tmdb_id}/watched";
    $properties = $scope === 'season' ? ['season_id' => $season->id] : ['source' => 'tv-show'];

    $this->actingAs($user)->putJson($url)->assertOk()->assertJsonPath('progress.watched', 3);
    $response = $this->deleteJson($url)->assertOk()->assertJsonPath('progress.watched', 0);
    $this->postJson("/tv-shows/tmdb/{$tvShow->tmdb_id}/undo", ['token' => $response->json('undoToken')])->assertOk();

    expect(Queue::pushed(RecordAnalyticsEvent::class)->map(fn ($job) => [$job->name, $job->properties])->all())->toBe([
        ["{$scope}_marked_watched", ['tv_show_id' => $tvShow->id, ...$properties, 'episode_count' => 2]],
        ['tv_show_completed', ['tv_show_id' => $tvShow->id]],
        ["{$scope}_unmarked_watched", ['tv_show_id' => $tvShow->id, ...$properties, 'episode_count' => 3]],
        ['tv_show_action_undone', ['tv_show_id' => $tvShow->id, 'action' => 'clear_watched', ...($scope === 'season' ? ['season_id' => $season->id] : [])]],
    ]);
})->with(['season', 'tv_show']);

test('a signed-in TV view records a safe referrer once and partial reloads record nothing', function (mixed $referrer, string $expected) {
    Queue::fake([RecordAnalyticsEvent::class]);
    $tvShow = TvShow::factory()->create();
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));
    $this->actingAs(User::factory()->create());
    $url = route('tv-shows.show', ['slug' => $tvShow->slug, 'id' => $tvShow->id, 'referrer' => $referrer]);
    $version = (string) $this->get($url)->assertOk()->viewData('page')['version'];
    $this->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'tv-shows/Show', 'X-Inertia-Partial-Data' => 'watchlisted'])->assertOk();

    expect(Queue::pushed(RecordAnalyticsEvent::class)->map(fn ($job) => [$job->name, $job->properties])->all())->toBe([
        ['tv_show_viewed', ['tv_show_id' => $tvShow->id, 'referrer' => $expected]],
    ]);
})->with([['search', 'search'], ['picker', 'direct'], [['search'], 'direct'], [null, 'direct']]);

test('TV views record no event for guests, Removed Titles, cap failures', function (string $case, int $status) {
    Queue::fake([RecordAnalyticsEvent::class]);
    $tvShow = match ($case) {
        'removed' => TvShow::factory()->removed()->create(),
        'cap' => TvShow::factory()->capCleared()->create(),
        default => TvShow::factory()->create(),
    };
    if ($case !== 'guest') {
        $this->actingAs(User::factory()->create());
    }
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));
    $this->get(route('tv-shows.show', ['slug' => $tvShow->slug ?? 'tv-show', 'id' => $tvShow->id]))->assertStatus($status);
    Queue::assertNotPushed(RecordAnalyticsEvent::class);
})->with([['guest', 200], ['removed', 410], ['cap', 503]]);

test('a guest action emits nothing until replay and replay records once', function (string $scope) {
    Queue::fake([RecordAnalyticsEvent::class]);
    $episode = Episode::factory()->create();
    $tvShow = TvShow::query()->findOrFail($episode->title_id);
    $user = User::factory()->create();
    $url = match ($scope) {
        'episode' => "/tv-shows/{$tvShow->id}/seasons/{$episode->season_id}/episodes/{$episode->id}/watched",
        'season' => "/tv-shows/{$tvShow->id}/seasons/{$episode->season_id}/watched",
        'tv_show' => "/tv-shows/tmdb/{$tvShow->tmdb_id}/watched",
        'watchlist' => "/tv-shows/tmdb/{$tvShow->tmdb_id}/watchlist",
    };
    $this->put($url, ['source' => 'search'])->assertRedirect(route('login'));
    Queue::assertNotPushed(RecordAnalyticsEvent::class);
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
    $this->get('/about')->assertOk();
    $name = $scope === 'watchlist' ? 'tv_show_watchlisted' : "{$scope}_marked_watched";
    expect(Queue::pushed(RecordAnalyticsEvent::class)->where('name', $name))->toHaveCount(1);
    Queue::assertPushed(RecordAnalyticsEvent::class, fn ($job) => $job->name === $name && $job->userId === $user->id && ($scope !== 'watchlist' || $job->properties['source'] === 'search'));
})->with(['episode', 'season', 'tv_show', 'watchlist']);

test('the TV entry redirect preserves a safe referrer for the view event', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $tvShow = TvShow::factory()->create();
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));
    $response = $this->actingAs(User::factory()->create())->get(route('tv-shows.entry', ['tmdbId' => $tvShow->tmdb_id, 'referrer' => 'search']))->assertStatus(301);
    $this->get($response->headers->get('Location'))->assertOk();
    Queue::assertPushed(RecordAnalyticsEvent::class, fn ($job) => $job->name === 'tv_show_viewed' && $job->properties === ['tv_show_id' => $tvShow->id, 'referrer' => 'search']);
});

test('invalid TV identifiers emit no action events', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $episode = Episode::factory()->create();
    $tvShow = TvShow::query()->findOrFail($episode->title_id);
    $this->actingAs(User::factory()->create());
    $token = $this->putJson("/tv-shows/tmdb/{$tvShow->tmdb_id}/watchlist", ['source' => 'search'])->assertOk()->json('undoToken');
    Queue::fake([RecordAnalyticsEvent::class]);

    foreach (['/tv-shows/tmdb/invalid/watchlist', '/tv-shows/tmdb/invalid/watched', "/tv-shows/999999/seasons/{$episode->season_id}/watched", "/tv-shows/999999/seasons/{$episode->season_id}/episodes/{$episode->id}/watched"] as $url) {
        $this->putJson($url, ['source' => 'search'])->assertNotFound();
        $this->deleteJson($url, ['source' => 'search'])->assertNotFound();
    }
    $this->postJson('/tv-shows/tmdb/invalid/undo', ['token' => $token])->assertNotFound();
    $this->get(route('tv-shows.watch', ['tmdbId' => 'invalid', 'country' => 'US']))->assertNotFound();
    Queue::assertNotPushed(RecordAnalyticsEvent::class);
});

test('whole-TV Show actions retain the originating surface through direct actions and guest replay', function (string $source, bool $guest) {
    Queue::fake([RecordAnalyticsEvent::class]);
    $episode = Episode::factory()->create();
    $tvShow = TvShow::query()->findOrFail($episode->title_id);
    $user = User::factory()->create();
    if (! $guest) {
        $this->actingAs($user);
    }
    $this->putJson("/tv-shows/tmdb/{$tvShow->tmdb_id}/watched", ['source' => $source])->assertOk();
    if ($guest) {
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
    }
    Queue::assertPushed(RecordAnalyticsEvent::class, fn ($job) => $job->name === 'tv_show_marked_watched' && $job->properties === ['tv_show_id' => $tvShow->id, 'source' => $source, 'episode_count' => 1]);
})->with(['watchlist', 'watched', 'tv-show'])->with([false, true]);

test('whole-TV Show actions reject an invalid source without analytics or pending actions', function (mixed $source, string $message) {
    Queue::fake([RecordAnalyticsEvent::class]);
    $this->putJson('/tv-shows/tmdb/87108/watched', ['source' => $source])->assertUnprocessable()
        ->assertJsonPath('errors.source.0', $message)->assertSessionMissing('user_movie_actions.pending');
    Queue::assertNotPushed(RecordAnalyticsEvent::class);
})->with([
    ['picker', 'The selected source is invalid.'],
    [null, 'The source field is required.'],
    [123, 'The source field must be a string.'],
]);
