<?php

namespace App\Services\Catalog\Sources\Tmdb;

use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Sources\SourceMisconfigured;
use App\Services\Catalog\Sources\SourceUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class TmdbClient
{
    private const MAX_RETRY_AFTER_MILLISECONDS = 2000;

    public function __construct(private BackgroundThrottle $backgroundThrottle) {}

    /**
     * A GET with the profile's timeout and retries. A 404 is an answer, returned as null; a 401 is a configuration
     * error; anything else unsuccessful, or no connection, is a failure. Each background attempt, a retry too, first
     * takes its turn in the background throttle.
     *
     * @param  array<string, string|int>  $query
     */
    public function get(string $path, array $query, CatalogContext $context): ?Response
    {
        $profile = $context->profile;

        try {
            $response = Http::baseUrl(config('catalog.tmdb.base_url'))
                ->withToken(config('catalog.tmdb.token'))
                ->acceptJson()
                ->connectTimeout($profile->timeoutSeconds())
                ->timeout($profile->timeoutSeconds())
                ->retry($profile->attempts(), fn (int $attempt, Throwable $exception): int => $this->retryDelayMilliseconds($profile, $attempt, $exception), $this->isRetryable(...), throw: false)
                ->when($profile === CatalogProfile::Background, fn (PendingRequest $request): PendingRequest => $request->beforeSending(fn () => $this->backgroundThrottle->awaitTurn()))
                ->get($path, $query);
        } catch (ConnectionException $exception) {
            throw new SourceUnavailable('The Catalog source is unavailable.', previous: $exception);
        }

        if ($response->notFound()) {
            return null;
        }

        if ($response->unauthorized()) {
            throw new SourceMisconfigured('The Catalog source rejected our credentials.');
        }

        if (! $response->successful()) {
            throw new SourceUnavailable('The Catalog source returned an unsuccessful response.');
        }

        return $response;
    }

    public function tvDetails(int $tmdbId, CatalogContext $context, ?float $deadline): ?Response
    {
        try {
            $response = $this->tvRequest(Http::createPendingRequest(), $context, $deadline)->get("tv/{$tmdbId}", [
                'language' => $context->language,
                'include_video_language' => explode('-', $context->language)[0].',null',
                'append_to_response' => 'external_ids,content_ratings,videos,aggregate_credits,watch/providers',
            ]);
        } catch (ConnectionException $exception) {
            throw new SourceUnavailable('The Catalog source is unavailable.', previous: $exception);
        }

        if ($response->notFound()) {
            return null;
        }

        return $this->checkTvResponse($response);
    }

    /**
     * @param  list<int>  $numbers
     * @return array<int, Response>
     */
    public function seasons(int $tmdbId, array $numbers, CatalogContext $context, ?float $deadline): array
    {
        $responses = Http::pool(function (Pool $pool) use ($numbers, $tmdbId, $context, $deadline): array {
            $requests = [];
            foreach ($numbers as $number) {
                $requests[] = $this->tvRequest($pool->as((string) $number), $context, $deadline)
                    ->get("tv/{$tmdbId}/season/{$number}", ['language' => $context->language]);
            }

            return $requests;
        });
        foreach ($responses as $number => $response) {
            $responses[$number] = $this->checkTvResponse($response);
        }

        return $responses;
    }

    /**
     * Interactive TV calls share one budget and do not retry within it. Background attempts retain the usual
     * retries and throttle, including each pooled Season request.
     */
    private function tvRequest(PendingRequest $request, CatalogContext $context, ?float $deadline): PendingRequest
    {
        $profile = $context->profile;
        $timeout = $deadline === null ? $profile->timeoutSeconds() : $deadline - microtime(true);
        if ($timeout <= 0) {
            throw new SourceUnavailable('The Catalog source exceeded the TV Show fetch timeout.');
        }

        return $request->baseUrl(config('catalog.tmdb.base_url'))->withToken(config('catalog.tmdb.token'))->acceptJson()
            ->connectTimeout($timeout)->timeout($timeout)
            ->retry($deadline === null ? $profile->attempts() : 1, fn (int $attempt, Throwable $exception): int => $this->retryDelayMilliseconds($profile, $attempt, $exception), $this->isRetryable(...), throw: false)
            ->when($profile === CatalogProfile::Background, fn (PendingRequest $pending): PendingRequest => $pending->beforeSending(fn () => $this->backgroundThrottle->awaitTurn()));
    }

    private function checkTvResponse(Response|Throwable $response): Response
    {
        if ($response instanceof Throwable) {
            throw new SourceUnavailable('The Catalog source is unavailable.', previous: $response);
        }
        if ($response->unauthorized()) {
            throw new SourceMisconfigured('The Catalog source rejected our credentials.');
        }
        if (! $response->successful()) {
            throw new SourceUnavailable('The Catalog source returned an unsuccessful TV response.');
        }

        return $response;
    }

    /** Only connection errors, server errors and 429 are retried; a 404 is an answer and a 401 a configuration error. */
    private function isRetryable(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && ($exception->response->serverError() || $exception->response->status() === 429);
    }

    private function retryDelayMilliseconds(CatalogProfile $profile, int $attempt, Throwable $exception): int
    {
        if ($exception instanceof RequestException && $exception->response->status() === 429) {
            $retryAfter = $exception->response->header('Retry-After');

            if (ctype_digit($retryAfter) && (int) $retryAfter > 0) {
                return min(self::MAX_RETRY_AFTER_MILLISECONDS, (int) $retryAfter * 1000);
            }
        }

        return match ($profile) {
            CatalogProfile::Interactive => 100,
            CatalogProfile::Background => (250 * 2 ** ($attempt - 1)) + random_int(0, 250),
        };
    }
}
