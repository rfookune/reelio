<?php

namespace App\Ratings\Sync;

use App\Ratings\Enums\RatingSourceName;
use App\Ratings\Exceptions\RatingReplacementRefused;
use App\Ratings\RatingStore;
use App\Ratings\Sync\Datasets\RatingDataset;
use App\Ratings\Sync\Enums\RatingSyncOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Syncs any full-dataset Rating source: a conditional download to disk, then a validated replacement of the
 * source's rows. Any failure throws before the replacement, leaving the live rows serving.
 */
class RatingDatasetSync
{
    public function __construct(private RatingStore $store) {}

    public function sync(RatingDataset $dataset): RatingSyncResult
    {
        $source = $dataset->source();
        $path = tempnam(sys_get_temp_dir(), "rating-dataset-{$source->value}-");

        if ($path === false) {
            throw new RatingSyncFailed('Could not create a temporary file for the download.');
        }

        try {
            $validators = $this->download($dataset, $path);

            if ($validators === null) {
                $this->recordSuccess($source);

                return new RatingSyncResult(RatingSyncOutcome::Unchanged);
            }

            try {
                ['imported' => $imported, 'skipped' => $skipped] = $this->store->replaceSource($source, $dataset->rows($path));
            } catch (RatingReplacementRefused $exception) {
                throw new RatingSyncFailed($exception->getMessage(), previous: $exception);
            }

            Cache::forever($this->validatorsKey($source), $validators);
            $this->recordSuccess($source);
            Log::info('Rating dataset synced.', ['source' => $source->value, 'imported' => $imported, 'skipped' => $skipped]);

            return new RatingSyncResult(RatingSyncOutcome::Synced, $imported, $skipped);
        } finally {
            @unlink($path);
        }
    }

    /** When the source last synced successfully, by loading its file or confirming it unchanged. */
    public function lastSuccessAt(RatingSourceName $source): ?CarbonImmutable
    {
        $timestamp = Cache::get($this->lastSuccessKey($source));

        return is_numeric($timestamp) ? CarbonImmutable::createFromTimestamp((int) $timestamp) : null;
    }

    /**
     * Streams the file to disk. The request is conditional only when the source has live rows to keep;
     * a source with none always downloads in full.
     *
     * @return array{etag: ?string, last_modified: ?string}|null the new validators, or null when the file is unchanged
     */
    private function download(RatingDataset $dataset, string $path): ?array
    {
        $headers = [];
        $remembered = Cache::get($this->validatorsKey($dataset->source()));

        if (is_array($remembered) && $this->store->hasRows($dataset->source())) {
            $headers = array_filter([
                'If-None-Match' => $remembered['etag'] ?? null,
                'If-Modified-Since' => $remembered['last_modified'] ?? null,
            ]);
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(config('ratings.sync.download_timeout_seconds'))
                ->sink($path)
                ->get($dataset->url());
        } catch (ConnectionException $exception) {
            throw new RatingSyncFailed('The rating dataset could not be downloaded.', previous: $exception);
        }

        if ($response->status() === 304) {
            return null;
        }

        if (! $response->successful()) {
            throw new RatingSyncFailed("The rating dataset download returned HTTP {$response->status()}.");
        }

        return [
            'etag' => $response->header('ETag') ?: null,
            'last_modified' => $response->header('Last-Modified') ?: null,
        ];
    }

    private function recordSuccess(RatingSourceName $source): void
    {
        Cache::forever($this->lastSuccessKey($source), now()->getTimestamp());
    }

    private function validatorsKey(RatingSourceName $source): string
    {
        return "ratings:{$source->value}:dataset-validators";
    }

    private function lastSuccessKey(RatingSourceName $source): string
    {
        return "ratings:{$source->value}:last-synced-at";
    }
}
