<?php

use App\Jobs\CheckImdbRatingsFreshness;
use App\Jobs\ImportImdbRatings;
use App\Ratings\Imdb\ImdbImportFailed;
use App\Ratings\Imdb\ImdbImportOutcome;
use App\Ratings\Imdb\ImdbRatingsImporter;
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
    config(['ratings.imdb.dataset_url' => imdbDatasetUrl()]);
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

function seedLiveRatings(int $count): void
{
    DB::table('imdb_ratings')->insert(array_map(fn (int $i) => ['imdb_id' => "tt9{$i}", 'rating' => 5.0, 'vote_count' => 10, 'imported_at' => '2026-11-01 05:00:00'], range(1, $count)));
}

test('a normal import loads valid rows, skips a missing rating and a malformed row, and counts them', function () {
    $this->travelTo('2026-11-05 05:00:00');
    fakeDataset(datasetFixture());

    $result = app(ImdbRatingsImporter::class)->import();

    expect($result->outcome)->toBe(ImdbImportOutcome::Imported)
        ->and($result->imported)->toBe(2)
        ->and($result->skipped)->toBe(2);
    expect(DB::table('imdb_ratings')->orderBy('imdb_id')->get()->map(fn ($row) => (array) $row)->all())->toBe([
        ['imdb_id' => 'tt0111161', 'rating' => '9.3', 'vote_count' => 3100000, 'imported_at' => '2026-11-05 05:00:00'],
        ['imdb_id' => 'tt0816692', 'rating' => '8.7', 'vote_count' => 2400000, 'imported_at' => '2026-11-05 05:00:00'],
    ]);
    expect(DB::table('imdb_ratings_staging')->count())->toBe(0);
});

test('a later import replaces the live table entirely', function () {
    seedLiveRatings(2);
    fakeDataset(gzippedDataset(["tt0816692\t8.6\t2500000", "tt0111161\t9.3\t3100000"]));

    app(ImdbRatingsImporter::class)->import();

    expect(DB::table('imdb_ratings')->pluck('imdb_id')->sort()->values()->all())->toBe(['tt0111161', 'tt0816692']);
});

test('a failed validation leaves the live table untouched', function (string $body) {
    seedLiveRatings(10);
    fakeDataset($body);

    expect(fn () => app(ImdbRatingsImporter::class)->import())->toThrow(ImdbImportFailed::class);

    expect(DB::table('imdb_ratings')->count())->toBe(10)
        ->and(DB::table('imdb_ratings')->where('imdb_id', 'tt91')->exists())->toBeTrue();
})->with([
    'header mismatch' => [fn () => gzippedDataset(["tt0816692\t8.7\t2400000"], "id\trating\tvotes")],
    'row count under 90% of the live table' => [fn () => gzippedDataset(array_map(fn (int $i) => "tt1{$i}\t7.0\t100", range(1, 8)))],
    'no valid rows' => [fn () => gzippedDataset([])],
    'not gzip' => ['plain text, not gzip'],
    'truncated gzip' => [fn () => substr(gzippedDataset(array_map(fn (int $i) => "tt1{$i}\t7.0\t100", range(1, 20))), 0, 40)],
]);

test('a repeated IMDb id is skipped and counted rather than failing the import', function () {
    fakeDataset(gzippedDataset(["tt11\t7.0\t100", "tt12\t6.0\t50", "tt11\t9.0\t900"]));

    $result = app(ImdbRatingsImporter::class)->import();

    expect($result->imported)->toBe(2)
        ->and($result->skipped)->toBe(1)
        ->and(DB::table('imdb_ratings')->where('imdb_id', 'tt11')->value('rating'))->toBe('7.0');
});

test('the swap keeps the primary key name on the live table', function () {
    fakeDataset(datasetFixture());
    app(ImdbRatingsImporter::class)->import();
    app(ImdbRatingsImporter::class)->import();

    $live = DB::selectOne("select indexname from pg_indexes where tablename = 'imdb_ratings'");

    expect($live->indexname)->toBe('imdb_ratings_pkey');
});

test('a failed download leaves the live table untouched', function () {
    seedLiveRatings(3);
    fakeDataset('', [], 503);

    expect(fn () => app(ImdbRatingsImporter::class)->import())->toThrow(ImdbImportFailed::class);

    expect(DB::table('imdb_ratings')->count())->toBe(3);
});

test('an unchanged file is not downloaded again', function () {
    Http::fake([imdbDatasetUrl() => fn (Request $request) => $request->hasHeader('If-None-Match', '"v1"')
        ? Http::response('', 304)
        : Http::response(datasetFixture(), 200, ['ETag' => '"v1"', 'Last-Modified' => 'Wed, 04 Nov 2026 03:00:00 GMT'])]);
    app(ImdbRatingsImporter::class)->import();

    $result = app(ImdbRatingsImporter::class)->import();

    expect($result->outcome)->toBe(ImdbImportOutcome::Unchanged)
        ->and(DB::table('imdb_ratings')->count())->toBe(2);
    Http::assertSent(fn (Request $request) => $request->hasHeader('If-None-Match', '"v1"')
        && $request->hasHeader('If-Modified-Since', 'Wed, 04 Nov 2026 03:00:00 GMT'));
});

test('an empty live table always downloads, even with remembered validators', function () {
    fakeDataset(datasetFixture());
    app(ImdbRatingsImporter::class)->import();
    DB::table('imdb_ratings')->delete();

    app(ImdbRatingsImporter::class)->import();

    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request) => $request->hasHeader('If-None-Match'));
    expect(DB::table('imdb_ratings')->count())->toBe(2);
});

test('the import job runs on the imports queue with a timeout under the local retry_after and a lock that outlives it', function () {
    $job = new ImportImdbRatings;

    expect($job->queue)->toBe('imports')
        ->and($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->timeout)->toBeGreaterThanOrEqual(300)
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan($job->timeout)
        ->and($job->uniqueFor)->toBeGreaterThan($job->timeout);
});

test('a failed import job alerts the owner', function () {
    Log::spy();

    (new ImportImdbRatings)->failed(new ImdbImportFailed('header mismatch'));

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context) => str_contains($message, 'IMDb ratings import failed') && $context['reason'] === 'header mismatch');
});

test('the owner is alerted when the live table is more than three days old', function (string $importedAt, bool $alerted) {
    $this->travelTo('2026-11-05 05:00:00');
    DB::table('imdb_ratings')->insert(['imdb_id' => 'tt1', 'rating' => 7.0, 'vote_count' => 1, 'imported_at' => $importedAt]);
    Log::spy();

    (new CheckImdbRatingsFreshness)->handle();

    $alerted
        ? Log::shouldHaveReceived('error')->once()
        : Log::shouldNotHaveReceived('error');
})->with([
    'fresh' => ['2026-11-04 05:00:00', false],
    'exactly three days' => ['2026-11-02 05:00:00', false],
    'stale' => ['2026-11-01 04:59:00', true],
]);

test('an empty live table alerts the owner that it has never been imported', function () {
    Log::spy();

    (new CheckImdbRatingsFreshness)->handle();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'never been imported'));
});

test('an unchanged file keeps an old table from reading as stale', function () {
    $this->travelTo('2026-11-01 05:00:00');
    Http::fake([imdbDatasetUrl() => fn (Request $request) => $request->hasHeader('If-None-Match')
        ? Http::response('', 304)
        : Http::response(datasetFixture(), 200, ['ETag' => '"v1"'])]);
    app(ImdbRatingsImporter::class)->import();

    $this->travelTo('2026-11-05 05:00:00');
    app(ImdbRatingsImporter::class)->import();
    Log::spy();

    (new CheckImdbRatingsFreshness)->handle();

    Log::shouldNotHaveReceived('error');
});
