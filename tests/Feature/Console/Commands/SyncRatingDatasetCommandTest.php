<?php

use App\Jobs\SyncRatingDataset;
use App\Services\Ratings\Enums\RatingSourceName;
use Illuminate\Bus\UniqueLock;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['ratings.sources.imdb.dataset_url' => 'https://datasets.example.test/title.ratings.tsv.gz']);
});

test('the command syncs IMDb by default and reports the counts', function () {
    Http::fake(['https://datasets.example.test/title.ratings.tsv.gz' => Http::response(
        file_get_contents(base_path('tests/Fixtures/imdb/title.ratings.tsv.gz')),
        200,
        ['ETag' => '"v1"'],
    )]);

    $this->artisan('ratings:sync')
        ->expectsOutputToContain('Synced IMDb: 2 imported, 2 skipped.')
        ->assertSuccessful();

    expect(DB::table('ratings')->where('source', 'imdb')->pluck('source_id')->sort()->values()->all())
        ->toBe(['tt0111161', 'tt0816692']);
});

test('the command shows the sync\'s progress', function () {
    Http::fake(['https://datasets.example.test/title.ratings.tsv.gz' => Http::response(
        file_get_contents(base_path('tests/Fixtures/imdb/title.ratings.tsv.gz')),
        200,
        ['ETag' => '"v1"'],
    )]);

    $this->artisan('ratings:sync imdb')
        ->expectsOutputToContain('Downloading the IMDb dataset…')
        ->expectsOutputToContain('4 rows read')
        ->expectsOutputToContain('Replacing the live IMDb rows…')
        ->assertSuccessful();
});

test('the command says when the dataset is unchanged since the last sync', function () {
    Http::fake(['https://datasets.example.test/title.ratings.tsv.gz' => fn (Request $request) => $request->hasHeader('If-None-Match', '"v1"')
        ? Http::response('', 304)
        : Http::response(file_get_contents(base_path('tests/Fixtures/imdb/title.ratings.tsv.gz')), 200, ['ETag' => '"v1"'])]);
    $this->artisan('ratings:sync imdb')->assertSuccessful();

    $this->artisan('ratings:sync imdb')
        ->expectsOutputToContain("The IMDb dataset hasn't changed since the last sync; nothing was replaced.")
        ->assertSuccessful();
});

test('a failed sync exits with failure and releases the lock', function () {
    Http::fake(['https://datasets.example.test/title.ratings.tsv.gz' => Http::response('', 503)]);

    $this->artisan('ratings:sync imdb')
        ->expectsOutputToContain('The IMDb sync failed; the live rows are unchanged.')
        ->assertFailed();

    expect((new UniqueLock(Cache::store()))->acquire(new SyncRatingDataset(RatingSourceName::Imdb)))->toBeTrue();
});

test('the command refuses to run while a sync holds the lock, and leaves that lock in place', function () {
    Http::fake();
    $lock = new UniqueLock(Cache::store());
    $lock->acquire(new SyncRatingDataset(RatingSourceName::Imdb));

    $this->artisan('ratings:sync imdb')
        ->expectsOutputToContain('A sync for IMDb is already queued or running.')
        ->assertFailed();

    Http::assertNothingSent();
    expect($lock->acquire(new SyncRatingDataset(RatingSourceName::Imdb)))->toBeFalse();
});

test('the command refuses a source it cannot sync', function (string $source, string $message) {
    Http::fake();
    config(['ratings.sources.imdb.enabled' => false]);

    $this->artisan("ratings:sync {$source}")
        ->expectsOutputToContain($message)
        ->assertFailed();

    Http::assertNothingSent();
})->with([
    'unknown' => ['rotten', 'Unknown Rating source. Valid sources: imdb, tmdb.'],
    'switched off' => ['imdb', "IMDb is switched off, so it isn't synced."],
    'no dataset' => ['tmdb', 'TMDB has no dataset to sync.'],
]);
