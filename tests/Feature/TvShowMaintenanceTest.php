<?php

use App\Jobs\CleanUpCatalog;
use App\Jobs\PlanTitleRefreshes;
use App\Jobs\RefreshTvShow;
use App\Models\Episode;
use App\Models\Season;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserEpisode;
use App\Models\UserTitle;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\EpisodeDetails;
use App\Services\Catalog\Sources\Details\SeasonDetails;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use App\Services\TvShows\WatchedProgress;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

test('the planner schedules held TV Shows by status with one unique refresh per TV Show', function (string $status, int $days, bool $watchedOnly) {
    $this->travelTo('2026-11-08 05:00:00');
    $due = TvShow::factory()->create(['synced_at' => now()->subDays($days)->subMinute()]);
    $due->details()->update(['status' => $status]);
    $recent = TvShow::factory()->create(['synced_at' => now()->subDays($days)]);
    $recent->details()->update(['status' => $status]);
    $unheld = TvShow::factory()->create(['synced_at' => now()->subDays(100)]);
    $user = User::factory()->create();
    foreach ([$due, $recent] as $show) {
        if ($watchedOnly) {
            $season = Season::factory()->create(['title_id' => $show->id, 'season_number' => 0, 'removed_at' => now()]);
            $episode = Episode::factory()->create(['season_id' => $season->id, 'removed_at' => now()]);
            UserEpisode::query()->create(['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => now()]);
        } else {
            UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $show->id, 'watchlisted_at' => now()]);
        }
    }
    Queue::fake();

    app()->call([new PlanTitleRefreshes, 'handle']);
    app()->call([new PlanTitleRefreshes, 'handle']);

    Queue::assertPushed(RefreshTvShow::class, 1);
    Queue::assertPushed(RefreshTvShow::class, fn (RefreshTvShow $job): bool => $job->tvShowId === $due->id);
})->with([
    ['Returning Series', 3, false], ['In Production', 3, true], ['Pilot', 3, false],
    ['Ended', 30, true], ['Canceled', 30, false],
]);

/** Complete TV data for exercising refresh, clearing and restoration through the Catalog boundary. */
function maintenanceTvDetails(): TvShowDetails
{
    return new TvShowDetails(
        900, title: 'A TV Show', originalTitle: 'Original', overview: 'Overview', tagline: 'Tagline', firstAirDate: '2025-01-01', lastAirDate: '2025-02-01',
        status: 'Returning Series', seasonCount: 1, episodeCount: 1, posterPath: '/poster.jpg', backdropPath: '/backdrop.jpg', originalLanguage: 'en', imdbId: 'tt0000900',
        voteAverage: 8.0, voteCount: 100, genres: [['id' => 18, 'name' => 'Drama']], creators: ['Creator'],
        availability: [['serviceId' => 8, 'serviceName' => 'Netflix', 'logoPath' => '/logo.jpg', 'country' => 'US', 'offerType' => 'stream', 'displayPriority' => 1]],
        seasons: [new SeasonDetails(901, 1, name: 'First Season', overview: 'Season overview', posterPath: '/season.jpg', airDate: '2025-01-01', episodeCount: 1,
            episodes: [new EpisodeDetails(902, 1, 1, name: 'First Episode', overview: 'Episode overview', airDate: '2025-01-01', runtime: 45, episodeType: 'standard')])],
    );
}

test('the cap guard clears all TV source columns with the flag off and a refresh restores the same inventory and facts', function () {
    $this->travelTo('2026-11-08 05:00:00');
    $source = new FakeCatalogSource(tvShows: [maintenanceTvDetails()]);
    app()->instance(CatalogSource::class, $source);
    $catalog = app(Catalog::class);
    $show = $catalog->findOrCreateTvShow(900, 'test')->value;
    $season = $show->seasons()->sole();
    $episode = $show->episodes()->sole();
    $user = User::factory()->create();
    UserEpisode::query()->create(['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => '2026-01-01']);
    $show->update(['synced_at' => now()->subDays(170)->subMinute()]);
    config(['catalog.tv_shows.enabled' => false]);

    CleanUpCatalog::dispatch();

    $this->assertDatabaseHas('titles', ['id' => $show->id, 'tmdb_id' => 900, 'title' => null, 'original_title' => null, 'slug' => null, 'synced_at' => null, 'removed_at' => null, 'overview' => null, 'tagline' => null, 'poster_path' => null, 'backdrop_path' => null, 'original_language' => null, 'imdb_id' => null]);
    $this->assertDatabaseHas('tv_show_details', ['title_id' => $show->id, 'status' => null, 'first_air_date' => null, 'last_air_date' => null, 'season_count' => null, 'episode_count' => null]);
    $this->assertDatabaseHas('seasons', ['id' => $season->id, 'tmdb_id' => 901, 'title_id' => $show->id, 'season_number' => null, 'name' => null, 'overview' => null, 'poster_path' => null, 'air_date' => null, 'episode_count' => null]);
    $this->assertDatabaseHas('episodes', ['id' => $episode->id, 'season_id' => $season->id, 'tmdb_id' => 902, 'season_number' => null, 'episode_number' => null, 'name' => null, 'overview' => null, 'air_date' => null, 'runtime' => null, 'episode_type' => null]);
    $this->assertDatabaseHas('user_episodes', ['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => '2026-01-01']);
    $this->assertDatabaseMissing('ratings', ['source' => 'tmdb', 'source_id' => 'tv:900']);
    $this->assertDatabaseMissing('availabilities', ['title_id' => $show->id]);
    $this->assertDatabaseMissing('genre_title', ['title_id' => $show->id]);
    expect($catalog->tvShowPageData($show->fresh()))->toBeNull();

    config(['catalog.tv_shows.enabled' => true]);
    RefreshTvShow::dispatch($show->id);

    $this->assertDatabaseHas('titles', ['id' => $show->id, 'title' => 'A TV Show', 'synced_at' => '2026-11-08 05:00:00']);
    $this->assertDatabaseHas('seasons', ['id' => $season->id, 'tmdb_id' => 901, 'name' => 'First Season', 'removed_at' => null]);
    $this->assertDatabaseHas('episodes', ['id' => $episode->id, 'tmdb_id' => 902, 'name' => 'First Episode', 'removed_at' => null]);
    $this->assertDatabaseHas('user_episodes', ['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => '2026-01-01']);
    expect($source->tvShowCalls[1]['context']->profile)->toBe(CatalogProfile::Background);
});

test('cleanup deletes old unheld TV inventory but keeps recent views and any user facts even with the flag off', function (bool $enabled) {
    $this->travelTo('2026-11-08 05:00:00');
    config(['catalog.tv_shows.enabled' => $enabled]);
    $unheld = TvShow::factory()->create(['tmdb_id' => 900, 'last_viewed_at' => now()->subDays(30)->subMinute()]);
    $season = Season::factory()->create(['title_id' => $unheld->id]);
    $episode = Episode::factory()->create(['season_id' => $season->id]);
    $recent = TvShow::factory()->create(['last_viewed_at' => now()->subDays(30)]);
    $watchlisted = TvShow::factory()->create(['last_viewed_at' => now()->subDays(100)]);
    $watched = TvShow::factory()->create(['last_viewed_at' => now()->subDays(100)]);
    $user = User::factory()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $watchlisted->id, 'watchlisted_at' => now()]);
    $special = Episode::factory()->create(['season_id' => Season::factory()->create(['title_id' => $watched->id, 'season_number' => 0, 'removed_at' => now()])->id, 'removed_at' => now()]);
    UserEpisode::query()->create(['user_id' => $user->id, 'episode_id' => $special->id, 'watched_at' => now()]);
    app(RatingStore::class)->record(RatingSourceName::Tmdb, 'tv:900', 8.0, 100);
    app(RatingStore::class)->record(RatingSourceName::Tmdb, 'movie:900', 7.0, 100);

    CleanUpCatalog::dispatch();

    $this->assertModelMissing($unheld);
    $this->assertModelMissing($season);
    $this->assertModelMissing($episode);
    $this->assertModelExists($recent);
    $this->assertModelExists($watchlisted);
    $this->assertModelExists($watched);
    $this->assertModelExists($special);
    $this->assertDatabaseHas('user_episodes', ['user_id' => $user->id, 'episode_id' => $special->id]);
    $this->assertDatabaseMissing('ratings', ['source' => 'tmdb', 'source_id' => 'tv:900']);
    $this->assertDatabaseHas('ratings', ['source' => 'tmdb', 'source_id' => 'movie:900']);
})->with([true, false]);

test('catalog purge clears TV inventory and ratings of both kinds with the flag off while keeping rows and facts', function () {
    $this->freezeTime();
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [maintenanceTvDetails()]));
    $show = app(Catalog::class)->findOrCreateTvShow(900, 'test')->value;
    $season = $show->seasons()->sole();
    $episode = $show->episodes()->sole();
    $user = User::factory()->create();
    UserEpisode::query()->create(['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => now()]);
    app(RatingStore::class)->record(RatingSourceName::Tmdb, 'movie:900', 7.0, 100);
    config(['catalog.tv_shows.enabled' => false, 'catalog.cleanup.batch_size' => 1]);

    $this->artisan('catalog:purge --force')->assertSuccessful();

    $this->assertDatabaseHas('titles', ['id' => $show->id, 'tmdb_id' => 900, 'title' => null, 'synced_at' => null, 'removed_at' => null]);
    $this->assertDatabaseHas('seasons', ['id' => $season->id, 'name' => null, 'season_number' => null]);
    $this->assertDatabaseHas('episodes', ['id' => $episode->id, 'name' => null, 'episode_number' => null]);
    $this->assertDatabaseHas('user_episodes', ['user_id' => $user->id, 'episode_id' => $episode->id]);
    $this->assertDatabaseMissing('ratings', ['source' => 'tmdb']);
});

test('scheduled refresh preserves a complete stored TV Show on failure and skips deleted removed or disabled TV Shows', function () {
    $this->freezeTime();
    $source = new FakeCatalogSource(tvShows: [maintenanceTvDetails()]);
    app()->instance(CatalogSource::class, $source);
    $show = app(Catalog::class)->findOrCreateTvShow(900, 'test')->value;
    $syncedAt = $show->synced_at;
    $source->failingTvShows = [900];
    $this->travel(4)->days();

    RefreshTvShow::dispatch($show->id);

    expect($show->fresh()->synced_at)->toEqual($syncedAt);
    $this->assertDatabaseHas('episodes', ['title_id' => $show->id, 'tmdb_id' => 902, 'name' => 'First Episode']);
    expect($source->tvShowCalls)->toHaveCount(2);

    $removed = TvShow::factory()->removed()->create();
    RefreshTvShow::dispatch($removed->id);
    RefreshTvShow::dispatch(999999);
    config(['catalog.tv_shows.enabled' => false]);
    RefreshTvShow::dispatch($show->id);
    expect($source->tvShowCalls)->toHaveCount(2);
});

test('a successful refresh hides vanished inventory without deleting Watched facts and restores its original ids when it returns', function (bool $wholeSeason) {
    $this->freezeTime();
    $details = maintenanceTvDetails();
    $source = new FakeCatalogSource(tvShows: [$details]);
    app()->instance(CatalogSource::class, $source);
    $catalog = app(Catalog::class);
    $show = $catalog->findOrCreateTvShow(900, 'test')->value;
    $episode = $show->episodes()->sole();
    $season = $show->seasons()->sole();
    $user = User::factory()->create();
    UserEpisode::query()->create(['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => now()]);
    $source->tvShows = [new TvShowDetails(900, title: 'A TV Show', seasons: $wholeSeason ? [] : [new SeasonDetails(901, 1)])];

    RefreshTvShow::dispatch($show->id);

    expect($episode->fresh()->removed_at)->not->toBeNull();
    $progress = app(WatchedProgress::class)->for($show, $user);
    expect($progress['total'])->toBe(0)->and($progress['watched'])->toBe(0);
    if ($wholeSeason) {
        expect($progress['seasons'])->toBe([]);
        expect($season->fresh()->removed_at)->not->toBeNull();
    } else {
        expect($progress['seasons'][0]['episodes'])->toBe([]);
    }
    $this->assertDatabaseHas('user_episodes', ['user_id' => $user->id, 'episode_id' => $episode->id]);

    $source->tvShows = [$details];
    RefreshTvShow::dispatch($show->id);

    $this->assertDatabaseHas('seasons', ['id' => $season->id, 'tmdb_id' => 901, 'removed_at' => null]);
    $this->assertDatabaseHas('episodes', ['id' => $episode->id, 'tmdb_id' => 902, 'removed_at' => null]);
    expect(app(WatchedProgress::class)->for($show, $user)['watched'])->toBe(1);
})->with([true, false]);

test('TV planner pagination resumes at its own cursor and the flag off skips all TV planning', function () {
    $this->freezeTime();
    config(['catalog.refresh.planner_page_size' => 1]);
    $user = User::factory()->create();
    $shows = TvShow::factory()->count(2)->create(['synced_at' => null]);
    foreach ($shows as $show) {
        UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $show->id, 'watchlisted_at' => now()]);
    }
    $removed = TvShow::factory()->removed()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $removed->id, 'watchlisted_at' => now()]);
    Queue::fake();

    app()->call([new PlanTitleRefreshes, 'handle']);
    $next = Queue::pushed(PlanTitleRefreshes::class)->sole();
    expect($next->afterTvShowId)->toBe($shows[0]->id);
    app()->call([$next, 'handle']);
    Queue::assertPushed(RefreshTvShow::class, 2);

    Queue::fake();
    config(['catalog.tv_shows.enabled' => false]);
    app()->call([new PlanTitleRefreshes, 'handle']);
    Queue::assertNothingPushed();
});

test('marking a cap-cleared TV Show refreshes preserved Episode rows first and changes nothing on failure', function (bool $failure) {
    $this->freezeTime();
    $source = new FakeCatalogSource(tvShows: [maintenanceTvDetails()]);
    app()->instance(CatalogSource::class, $source);
    $show = app(Catalog::class)->findOrCreateTvShow(900, 'test')->value;
    $show->update(['synced_at' => now()->subDays(171)]);
    CleanUpCatalog::dispatch();
    $source->failing = $failure;
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson(route('tv-shows.watched.update', ['tmdbId' => 900]));

    $response->assertStatus($failure ? 503 : 200);
    expect($source->tvShowCalls)->toHaveCount(2);
    if ($failure) {
        $this->assertDatabaseCount('user_episodes', 0);
        $this->assertDatabaseHas('titles', ['id' => $show->id, 'synced_at' => null]);
    } else {
        $response->assertJsonPath('progress.watched', 1);
        $this->assertDatabaseHas('user_episodes', ['user_id' => $user->id, 'episode_id' => $show->episodes()->sole()->id]);
    }
})->with([true, false]);

test('cleanup blocks a concurrent Watched insert after its final facts check', function () {
    $this->freezeTime();
    $connectionName = 'tv_cleanup_race';
    config(['database.connections.'.$connectionName => config('database.connections.'.config('database.default'))]);
    $other = DB::connection($connectionName);
    $user = User::factory()->connection($connectionName)->create();
    $show = TvShow::factory()->connection($connectionName)->create(['last_viewed_at' => now()->subDays(31)]);
    $season = Season::factory()->connection($connectionName)->create(['title_id' => $show->id]);
    $episode = Episode::factory()->connection($connectionName)->create(['season_id' => $season->id]);
    $this->beforeApplicationDestroyed(function () use ($other, $show, $user): void {
        $other->table('titles')->where('id', $show->id)->delete();
        $other->table('users')->where('id', $user->id)->delete();
        $other->disconnect();
    });
    $other->statement("SET lock_timeout = '100ms'");
    $attempted = false;
    $blocked = false;
    DB::listen(function ($query) use ($connectionName, $other, $user, $episode, &$attempted, &$blocked): void {
        if ($attempted || $query->connectionName === $connectionName || ! str_starts_with($query->sql, 'select "id", "tmdb_id", "kind" from "titles"')) {
            return;
        }
        $attempted = true;
        try {
            $other->table('user_episodes')->insert(['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => now()]);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '55P03') {
                throw $exception;
            }
            $blocked = true;
        }
    });

    CleanUpCatalog::dispatch();

    expect($attempted)->toBeTrue()->and($blocked)->toBeTrue();
    $this->assertDatabaseMissing('titles', ['id' => $show->id]);
});
