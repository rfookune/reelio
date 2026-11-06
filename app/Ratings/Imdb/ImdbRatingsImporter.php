<?php

namespace App\Ratings\Imdb;

use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Loads IMDb's non-commercial `title.ratings.tsv.gz` into the staging table, validates it, and swaps it in
 * as the live table in one transaction. Any failure throws before the swap, leaving the live table serving.
 */
class ImdbRatingsImporter
{
    private const EXPECTED_HEADER = "tconst\taverageRating\tnumVotes";

    private const MINIMUM_SHARE_OF_LIVE_ROWS = 0.9;

    private const INSERT_BATCH_SIZE = 2000;

    private const VALIDATORS_CACHE_KEY = 'ratings:imdb:dataset-validators';

    /** When an import last finished successfully, either loading the file or confirming it unchanged. */
    public const LAST_SUCCESS_CACHE_KEY = 'ratings:imdb:last-success-at';

    private const DOWNLOAD_TIMEOUT_SECONDS = 120;

    public function import(): ImdbImportResult
    {
        $file = tempnam(sys_get_temp_dir(), 'imdb-ratings-');

        if ($file === false) {
            throw new ImdbImportFailed('Could not create a temporary file for the download.');
        }

        try {
            $validators = $this->download($file);

            if ($validators === null) {
                Cache::forever(self::LAST_SUCCESS_CACHE_KEY, now()->getTimestamp());

                return new ImdbImportResult(ImdbImportOutcome::Unchanged);
            }

            try {
                [$imported, $skipped] = $this->stage($file);
                $this->validateRowCount($imported);
                $this->swap();
            } catch (QueryException $exception) {
                throw new ImdbImportFailed('The IMDb dataset could not be staged or swapped in.', previous: $exception);
            }

            Cache::forever(self::VALIDATORS_CACHE_KEY, $validators);
            Cache::forever(self::LAST_SUCCESS_CACHE_KEY, now()->getTimestamp());
            $this->emptyStaging();
            Log::info('IMDb ratings imported.', ['imported' => $imported, 'skipped' => $skipped]);

            return new ImdbImportResult(ImdbImportOutcome::Imported, $imported, $skipped);
        } finally {
            @unlink($file);
        }
    }

    /**
     * Streams the dataset to disk. The request is conditional only when the live table holds the remembered
     * file; an empty live table always downloads in full.
     *
     * @return array{etag: ?string, last_modified: ?string}|null the new validators, or null when the file is unchanged
     */
    private function download(string $file): ?array
    {
        $headers = [];
        $remembered = Cache::get(self::VALIDATORS_CACHE_KEY);

        if (is_array($remembered) && DB::table('imdb_ratings')->exists()) {
            $headers = array_filter([
                'If-None-Match' => $remembered['etag'] ?? null,
                'If-Modified-Since' => $remembered['last_modified'] ?? null,
            ]);
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(self::DOWNLOAD_TIMEOUT_SECONDS)
                ->sink($file)
                ->get(config('ratings.imdb.dataset_url'));
        } catch (ConnectionException $exception) {
            throw new ImdbImportFailed('The IMDb dataset could not be downloaded.', previous: $exception);
        }

        if ($response->status() === 304) {
            return null;
        }

        if (! $response->successful()) {
            throw new ImdbImportFailed("The IMDb dataset download returned HTTP {$response->status()}.");
        }

        return [
            'etag' => $response->header('ETag') ?: null,
            'last_modified' => $response->header('Last-Modified') ?: null,
        ];
    }

    /**
     * Reads the gzipped TSV line by line into the staging table. Malformed rows, missing ratings and repeated
     * IMDb ids are skipped and counted.
     *
     * @return array{int, int} rows staged and rows skipped
     */
    private function stage(string $file): array
    {
        if (file_get_contents($file, length: 2) !== "\x1f\x8b") {
            throw new ImdbImportFailed('The IMDb dataset is not gzipped.');
        }

        $stream = @gzopen($file, 'rb');

        if ($stream === false) {
            throw new ImdbImportFailed('The IMDb dataset could not be opened.');
        }

        try {
            $header = gzgets($stream);

            if ($header === false || rtrim($header, "\r\n") !== self::EXPECTED_HEADER) {
                throw new ImdbImportFailed('The IMDb dataset header does not match.');
            }

            DB::table('imdb_ratings_staging')->truncate();
            $importedAt = now()->toDateTimeString();
            $batch = [];
            $read = 0;
            $imported = 0;

            while (($line = gzgets($stream)) !== false) {
                $read++;
                $row = $this->parse(rtrim($line, "\r\n"), $importedAt);

                if ($row === null) {
                    continue;
                }

                $batch[] = $row;

                if (count($batch) === self::INSERT_BATCH_SIZE) {
                    $imported += DB::table('imdb_ratings_staging')->insertOrIgnore($batch);
                    $batch = [];
                }
            }

            if (! gzeof($stream)) {
                throw new ImdbImportFailed('The IMDb dataset is corrupt.');
            }

            if ($batch !== []) {
                $imported += DB::table('imdb_ratings_staging')->insertOrIgnore($batch);
            }

            $skipped = $read - $imported;

            return [$imported, $skipped];
        } finally {
            gzclose($stream);
        }
    }

    /** @return array{imdb_id: string, rating: float, vote_count: int, imported_at: string}|null */
    private function parse(string $line, string $importedAt): ?array
    {
        $fields = explode("\t", $line);

        if (count($fields) !== 3) {
            return null;
        }

        [$imdbId, $rating, $votes] = $fields;

        if (preg_match('/^tt\d{1,12}$/D', $imdbId) !== 1
            || ! is_numeric($rating) || (float) $rating < 0 || (float) $rating > 10
            || ! ctype_digit($votes) || (int) $votes < 1 || strlen($votes) > 9) {
            return null;
        }

        return ['imdb_id' => $imdbId, 'rating' => (float) $rating, 'vote_count' => (int) $votes, 'imported_at' => $importedAt];
    }

    private function validateRowCount(int $staged): void
    {
        $live = DB::table('imdb_ratings')->count();

        if ($staged === 0 || $staged < $live * self::MINIMUM_SHARE_OF_LIVE_ROWS) {
            throw new ImdbImportFailed("The IMDb dataset has {$staged} valid rows against {$live} live rows.");
        }
    }

    /**
     * Swaps staging and live by renaming, in one transaction. The primary keys are renamed with their tables so
     * `imdb_ratings_pkey` always belongs to the live table. The lock timeout fails the swap rather than queueing
     * page reads behind a long-running reader.
     */
    private function swap(): void
    {
        DB::transaction(function (): void {
            DB::statement("SET LOCAL lock_timeout = '5s'");
            DB::statement('ALTER TABLE imdb_ratings RENAME TO imdb_ratings_previous');
            DB::statement('ALTER INDEX imdb_ratings_pkey RENAME TO imdb_ratings_previous_pkey');
            DB::statement('ALTER TABLE imdb_ratings_staging RENAME TO imdb_ratings');
            DB::statement('ALTER INDEX imdb_ratings_staging_pkey RENAME TO imdb_ratings_pkey');
            DB::statement('ALTER TABLE imdb_ratings_previous RENAME TO imdb_ratings_staging');
            DB::statement('ALTER INDEX imdb_ratings_previous_pkey RENAME TO imdb_ratings_staging_pkey');
        });
    }

    /**
     * Frees the previous live rows. The new table is already serving, so a failure here is reported, not fatal;
     * the next import truncates staging before loading anyway.
     */
    private function emptyStaging(): void
    {
        try {
            DB::table('imdb_ratings_staging')->truncate();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
