<?php

use App\Jobs\CheckRatingDatasetFreshness;
use App\Jobs\SyncRatingDataset;
use App\Ratings\Enums\RatingSourceName;
use App\Ratings\Enums\RatingSyncOutcome;
use App\Ratings\RatingStore;
use App\Ratings\Sync\ImdbRatingDataset;
use App\Ratings\Sync\RatingDatasetSync;
use App\Ratings\Sync\RatingSyncFailed;
use App\Ratings\Sync\RatingSyncResult;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function imdbDatasetUrl(): string
{
    return 'https://datasets.example.test/title.ratings.tsv.gz';
}

beforeEach(function () {
    config(['ratings.sources.imdb.dataset_url' => imdbDatasetUrl()]);
});

/** @param array<string, string> $headers */
function fakeDataset(string $gzippedBody, array $headers = ['ETag' => '"v1"', 'Last-Modified' => 'Wed, 04 Nov 2026 03:00:00 GMT'], int $status = 200): void
{
    Http::fake([imdbDatasetUrl() => fn () => Http::response($gzippedBody, $status, $headers)]);
}

function datasetFixture(): string
{
    return file_get_contents(base_path('tests/Fixtures/imdb/title.ratings.tsv.gz'));
}

/** @param list<string> $rows */
function gzippedDataset(array $rows, string $header = "tconst\taverageRating\tnumVotes"): string
{
    return gzencode(implode("\n", [$header, ...$rows])."\n");
}

function seedImdbRatings(int $count): void
{
    foreach (range(1, $count) as $i) {
        app(RatingStore::class)->record(RatingSourceName::Imdb, "tt9{$i}", 5.0, 10);
    }
}

function syncImdb(): RatingSyncResult
{
    return app(RatingDatasetSync::class)->sync(app(ImdbRatingDataset::class));
}

function imdbRows(): array
{
    return DB::table('ratings')->where('source', 'imdb')->orderBy('source_id')->get()
        ->map(fn ($row) => [$row->source_id, $row->value, $row->vote_count, $row->as_of])->all();
}

test('a sync loads valid rows, skips a missing rating and a malformed row, and counts them', function () {
    $this->travelTo('2026-11-05 05:00:00');
    fakeDataset(datasetFixture());

    $result = syncImdb();

    expect($result->outcome)->toBe(RatingSyncOutcome::Synced)
        ->and($result->imported)->toBe(2)
        ->and($result->skipped)->toBe(2)
        ->and(imdbRows())->toBe([
            ['tt0111161', '9.300', 3100000, '2026-11-05 05:00:00'],
            ['tt0816692', '8.700', 2400000, '2026-11-05 05:00:00'],
        ]);
});

test('a sync replaces only its own source\'s rows', function () {
    seedImdbRatings(2);
    app(RatingStore::class)->record(RatingSourceName::Tmdb, '157336', 8.5, 41832);
    fakeDataset(gzippedDataset(["tt0816692\t8.6\t2500000", "tt0111161\t9.3\t3100000"]));

    syncImdb();

    expect(array_column(imdbRows(), 0))->toBe(['tt0111161', 'tt0816692'])
        ->and(DB::table('ratings')->where('source', 'tmdb')->value('source_id'))->toBe('157336');
});

/**
 * Proves only that no permanent table is left in `public`. The temporary table's `ON COMMIT DROP` can't be
 * observed here, because RefreshDatabase's transaction never commits; ticket 11 lists a real sync as a deploy check.
 */
test('a sync creates no permanent table', function () {
    $tablesBefore = DB::table('pg_tables')->where('schemaname', 'public')->pluck('tablename')->sort()->values()->all();
    fakeDataset(datasetFixture());

    syncImdb();

    expect(DB::table('pg_tables')->where('schemaname', 'public')->pluck('tablename')->sort()->values()->all())->toBe($tablesBefore);
});

test('a vote count too large to store is skipped and counted', function () {
    fakeDataset(gzippedDataset(["tt11\t7.0\t100", "tt12\t7.0\t3000000000"]));

    $result = syncImdb();

    expect($result->imported)->toBe(1)
        ->and($result->skipped)->toBe(1);
});

test('a failed validation leaves the live rows untouched', function (string $body) {
    seedImdbRatings(10);
    fakeDataset($body);

    expect(fn () => syncImdb())->toThrow(RatingSyncFailed::class);

    expect(imdbRows())->toHaveCount(10)
        ->and(array_column(imdbRows(), 0))->toContain('tt91');
})->with([
    'header mismatch' => [fn () => gzippedDataset(["tt0816692\t8.7\t2400000"], "id\trating\tvotes")],
    'row count under 90% of the live rows' => [fn () => gzippedDataset(array_map(fn (int $i) => "tt1{$i}\t7.0\t100", range(1, 8)))],
    'no valid rows' => [fn () => gzippedDataset([])],
    'not gzip' => ['plain text, not gzip'],
    'truncated gzip' => [fn () => substr(gzippedDataset(array_map(fn (int $i) => "tt1{$i}\t7.0\t100", range(1, 20))), 0, 40)],
]);

test('a repeated title id is skipped and counted rather than failing the sync', function () {
    fakeDataset(gzippedDataset(["tt11\t7.0\t100", "tt12\t6.0\t50", "tt11\t9.0\t900"]));

    $result = syncImdb();

    expect($result->imported)->toBe(2)
        ->and($result->skipped)->toBe(1)
        ->and(DB::table('ratings')->where('source_id', 'tt11')->value('value'))->toBe('7.000');
});

test('a failed download leaves the live rows untouched', function () {
    seedImdbRatings(3);
    fakeDataset('', [], 503);

    expect(fn () => syncImdb())->toThrow(RatingSyncFailed::class);

    expect(imdbRows())->toHaveCount(3);
});

test('an unchanged file is not downloaded again', function () {
    Http::fake([imdbDatasetUrl() => fn (Request $request) => $request->hasHeader('If-None-Match', '"v1"')
        ? Http::response('', 304)
        : Http::response(datasetFixture(), 200, ['ETag' => '"v1"', 'Last-Modified' => 'Wed, 04 Nov 2026 03:00:00 GMT'])]);
    syncImdb();

    $result = syncImdb();

    expect($result->outcome)->toBe(RatingSyncOutcome::Unchanged)
        ->and(imdbRows())->toHaveCount(2);
    Http::assertSent(fn (Request $request) => $request->hasHeader('If-None-Match', '"v1"')
        && $request->hasHeader('If-Modified-Since', 'Wed, 04 Nov 2026 03:00:00 GMT'));
});

test('a source with no rows always downloads, even with remembered validators', function () {
    fakeDataset(datasetFixture());
    syncImdb();
    DB::table('ratings')->where('source', 'imdb')->delete();

    syncImdb();

    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request) => $request->hasHeader('If-None-Match'));
    expect(imdbRows())->toHaveCount(2);
});

test('the sync job runs on the imports queue, unique per source, with a timeout under the local retry_after', function () {
    $job = new SyncRatingDataset(RatingSourceName::Imdb);

    expect($job->queue)->toBe('imports')
        ->and($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('imdb')
        ->and($job->timeout)->toBeGreaterThanOrEqual(300)
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan($job->timeout)
        ->and($job->uniqueFor)->toBeGreaterThan($job->timeout);
});

test('the sync job syncs its source\'s dataset', function () {
    fakeDataset(datasetFixture());

    SyncRatingDataset::dispatchSync(RatingSourceName::Imdb);

    expect(imdbRows())->toHaveCount(2);
});

test('a switched-off source is not synced', function () {
    config(['ratings.sources.imdb.enabled' => false]);
    Http::fake();

    SyncRatingDataset::dispatchSync(RatingSourceName::Imdb);

    Http::assertNothingSent();
});

test('a failed sync job alerts the owner', function () {
    Log::spy();

    (new SyncRatingDataset(RatingSourceName::Imdb))->failed(new RatingSyncFailed('header mismatch'));

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context) => str_contains($message, 'Rating dataset sync failed')
        && $context['source'] === 'imdb' && $context['reason'] === 'header mismatch');
});

test('the owner is alerted when a source\'s rows are more than three days old', function (string $asOf, bool $alerted) {
    $this->travelTo($asOf);
    seedImdbRatings(1);
    $this->travelTo('2026-11-05 05:00:00');
    Log::spy();

    CheckRatingDatasetFreshness::dispatchSync();

    $alerted
        ? Log::shouldHaveReceived('error')->once()
        : Log::shouldNotHaveReceived('error');
})->with([
    'fresh' => ['2026-11-04 05:00:00', false],
    'exactly three days' => ['2026-11-02 05:00:00', false],
    'stale' => ['2026-11-01 04:59:00', true],
]);

test('a source that was never synced alerts the owner', function () {
    Log::spy();

    CheckRatingDatasetFreshness::dispatchSync();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context) => str_contains($message, 'never been synced') && $context['source'] === 'imdb');
});

test('an unchanged file keeps old rows from reading as stale', function () {
    $this->travelTo('2026-11-01 05:00:00');
    Http::fake([imdbDatasetUrl() => fn (Request $request) => $request->hasHeader('If-None-Match')
        ? Http::response('', 304)
        : Http::response(datasetFixture(), 200, ['ETag' => '"v1"'])]);
    syncImdb();
    $this->travelTo('2026-11-05 05:00:00');
    syncImdb();
    Log::spy();

    CheckRatingDatasetFreshness::dispatchSync();

    Log::shouldNotHaveReceived('error');
});
