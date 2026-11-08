<?php

use App\Jobs\PlanTitleRefreshes;
use App\Jobs\RefreshMovie;
use App\Models\Movie;
use App\Models\UserMovie;
use Illuminate\Support\Facades\Queue;

/** A Movie last synced the given number of days and minutes before now, or never. */
function movieSyncedDaysAgo(?int $days, int $minutes = 1): Movie
{
    return Movie::factory()->create(['synced_at' => $days === null ? null : now()->subDays($days)->subMinutes($minutes)]);
}

/** Runs one planner run in the test, leaving whatever it dispatches on the faked queue. */
function runPlanner(int $afterId = 0): void
{
    app()->call([new PlanTitleRefreshes($afterId), 'handle']);
}

/** @return list<int> */
function plannedMovieIds(): array
{
    return Queue::pushed(RefreshMovie::class)->map(fn (RefreshMovie $job): int => $job->movieId)->sort()->values()->all();
}

test('the planner refreshes Watchlist Movies after 3 days and Watched-only Movies after 30, never Movies nobody holds', function () {
    $this->travelTo('2026-11-06 05:00:00');
    Queue::fake();

    $watchlistDue = movieSyncedDaysAgo(3);
    $watchlistRecent = movieSyncedDaysAgo(3, minutes: 0);
    $watchedDue = movieSyncedDaysAgo(30);
    $watchedRecent = movieSyncedDaysAgo(30, minutes: 0);
    $watchedHereWatchlistedThere = movieSyncedDaysAgo(3);
    $nobodyHolds = movieSyncedDaysAgo(100);
    $removed = Movie::factory()->removed()->create();
    $neverSynced = movieSyncedDaysAgo(null);

    UserMovie::factory()->for($watchlistDue)->create();
    UserMovie::factory()->for($watchlistRecent)->create();
    UserMovie::factory()->watched()->for($watchedDue)->create();
    UserMovie::factory()->watched()->for($watchedRecent)->create();
    UserMovie::factory()->watched()->for($watchedHereWatchlistedThere)->create();
    UserMovie::factory()->for($watchedHereWatchlistedThere)->create();
    UserMovie::factory()->for($removed)->create();
    UserMovie::factory()->watched()->for($neverSynced)->create();

    runPlanner();

    expect(plannedMovieIds())->toBe(collect([$watchlistDue, $watchedDue, $watchedHereWatchlistedThere, $neverSynced])->pluck('id')->sort()->values()->all());
    Queue::assertPushedOn('default', RefreshMovie::class);
});

test('a second planner run while a Movie\'s refresh is still queued does not queue it again', function () {
    $this->freezeTime();
    Queue::fake();
    UserMovie::factory()->for(movieSyncedDaysAgo(4))->create();

    runPlanner();
    runPlanner();

    Queue::assertPushed(RefreshMovie::class, 1);
});

test('a full page hands the rest to the next run, which stops when it runs out of due Movies', function () {
    $this->freezeTime();
    Queue::fake();
    config(['catalog.refresh.planner_page_size' => 2]);
    $movies = collect(range(1, 3))->map(fn (): Movie => movieSyncedDaysAgo(4))->each(fn (Movie $movie) => UserMovie::factory()->for($movie)->create());

    runPlanner();

    expect(plannedMovieIds())->toBe($movies->take(2)->pluck('id')->all());
    $next = Queue::pushed(PlanTitleRefreshes::class)->sole();
    expect($next->afterId)->toBe($movies[1]->id);

    Queue::fake();
    runPlanner($next->afterId);

    expect(plannedMovieIds())->toBe([$movies[2]->id]);
    Queue::assertNotPushed(PlanTitleRefreshes::class);
});
