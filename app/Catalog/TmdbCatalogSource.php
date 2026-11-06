<?php

namespace App\Catalog;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class TmdbCatalogSource implements CatalogSource
{
    private const YOUTUBE_KEY_PATTERN = '/^[A-Za-z0-9_-]{11}$/D';

    private const MAX_RETRY_AFTER_MILLISECONDS = 2000;

    /** TMDB release type for a theatrical release. */
    private const RELEASE_TYPE_THEATRICAL = 3;

    /** TMDB release type for a limited theatrical release. */
    private const RELEASE_TYPE_THEATRICAL_LIMITED = 2;

    public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
    {
        $profile = $context->profile;

        try {
            $response = Http::baseUrl(config('catalog.tmdb.base_url'))
                ->withToken(config('catalog.tmdb.token'))
                ->acceptJson()
                ->connectTimeout($profile->timeoutSeconds())
                ->timeout($profile->timeoutSeconds())
                ->retry($profile->attempts(), fn (int $attempt, Throwable $exception): int => $this->retryDelayMilliseconds($profile, $attempt, $exception), $this->isRetryable(...), throw: false)
                ->get("movie/{$tmdbId}", [
                    'language' => $context->language,
                    'include_adult' => 'false',
                    'include_video_language' => explode('-', $context->language)[0].',null',
                    'append_to_response' => 'credits,videos,release_dates,watch/providers',
                ]);
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

        try {
            $data = Validator::make((array) $response->json(), [
                'id' => ['required', 'integer', 'in:'.$tmdbId],
                'title' => ['nullable', 'string', 'max:255'],
                'original_title' => ['nullable', 'string', 'max:255'],
                'overview' => ['nullable', 'string'],
                'tagline' => ['nullable', 'string'],
                'release_date' => ['nullable', 'date_format:Y-m-d'],
                'runtime' => ['nullable', 'integer', 'min:0'],
                'poster_path' => ['nullable', 'string', 'max:255'],
                'backdrop_path' => ['nullable', 'string', 'max:255'],
                'original_language' => ['nullable', 'string', 'max:255'],
                'vote_average' => ['nullable', 'numeric', 'between:0,10'],
                'vote_count' => ['nullable', 'integer', 'min:0'],
                'imdb_id' => ['nullable', 'string', 'max:255'],
                'genres' => ['sometimes', 'array'],
                'genres.*.id' => ['required', 'integer', 'min:1'],
                'genres.*.name' => ['required', 'string', 'max:255'],
                'credits' => ['sometimes', 'nullable', 'array'],
                'release_dates' => ['sometimes', 'nullable', 'array'],
                'videos' => ['sometimes', 'nullable', 'array'],
            ])->validate();
        } catch (ValidationException $exception) {
            throw new SourceUnavailable('The Catalog source returned invalid movie details.', previous: $exception);
        }

        $usRelease = $this->usRelease($data);

        return new MovieDetails(
            tmdbId: (int) $data['id'],
            title: $data['title'] ?? null,
            originalTitle: $data['original_title'] ?? null,
            overview: $data['overview'] ?? null,
            tagline: $data['tagline'] ?? null,
            releaseDate: ($data['release_date'] ?? null) ?: null,
            runtime: isset($data['runtime']) ? (int) $data['runtime'] : null,
            posterPath: $data['poster_path'] ?? null,
            backdropPath: $data['backdrop_path'] ?? null,
            originalLanguage: $data['original_language'] ?? null,
            voteAverage: isset($data['vote_average']) ? (float) $data['vote_average'] : null,
            voteCount: isset($data['vote_count']) ? (int) $data['vote_count'] : null,
            imdbId: $data['imdb_id'] ?? null,
            genres: array_values(array_map(fn (array $genre): array => ['id' => (int) $genre['id'], 'name' => $genre['name']], $data['genres'] ?? [])),
            directors: $this->crewNames($data['credits'] ?? [], ['Director']),
            writers: $this->crewNames($data['credits'] ?? [], ['Writer', 'Screenplay']),
            cast: $this->cast($data['credits'] ?? []),
            certification: $usRelease['certification'] ?? null,
            usReleaseDate: $usRelease['date'] ?? null,
            trailer: $this->trailer($data['videos'] ?? [], $context->language, ($data['release_date'] ?? null) ?: null),
        );
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

    /**
     * @param  array<string, mixed>  $credits
     * @param  list<string>  $jobs
     * @return list<string>
     */
    private function crewNames(array $credits, array $jobs): array
    {
        $names = [];

        foreach ((array) ($credits['crew'] ?? []) as $member) {
            if (is_array($member) && in_array($member['job'] ?? null, $jobs, true) && is_string($member['name'] ?? null)) {
                $names[] = $member['name'];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  array<string, mixed>  $credits
     * @return list<array{id: int, name: string, character: ?string, profilePath: ?string}>
     */
    private function cast(array $credits): array
    {
        $members = array_filter((array) ($credits['cast'] ?? []), fn (mixed $member): bool => is_array($member) && is_int($member['id'] ?? null) && is_string($member['name'] ?? null));
        usort($members, fn (array $a, array $b): int => ($a['order'] ?? PHP_INT_MAX) <=> ($b['order'] ?? PHP_INT_MAX));

        return array_map(fn (array $member): array => [
            'id' => $member['id'],
            'name' => $member['name'],
            'character' => ($member['character'] ?? '') !== '' ? $member['character'] : null,
            'profilePath' => $member['profile_path'] ?? null,
        ], array_slice($members, 0, 12));
    }

    /**
     * Ranks YouTube Trailers before Teasers, then official, then a language match; ties go to the latest video
     * published on or before the primary release date, else the earliest.
     *
     * @param  array<string, mixed>  $videos
     * @return array{key: string, name: string, type: string}|null
     */
    private function trailer(array $videos, string $language, ?string $releaseDate): ?array
    {
        $languageCode = explode('-', $language)[0];
        $scored = [];

        foreach ((array) ($videos['results'] ?? []) as $video) {
            if (! is_array($video)
                || ($video['site'] ?? null) !== 'YouTube'
                || ! is_string($video['key'] ?? null)
                || preg_match(self::YOUTUBE_KEY_PATTERN, $video['key']) !== 1
                || ! in_array($video['type'] ?? null, ['Trailer', 'Teaser'], true)
                || ! is_string($video['name'] ?? null)) {
                continue;
            }

            $scored[] = [
                'score' => ($video['type'] === 'Trailer' ? 4 : 0) + (($video['official'] ?? false) === true ? 2 : 0) + (($video['iso_639_1'] ?? null) === $languageCode ? 1 : 0),
                'published' => is_string($video['published_at'] ?? null) ? $video['published_at'] : '',
                'video' => $video,
            ];
        }

        if ($scored === []) {
            return null;
        }

        $best = max(array_column($scored, 'score'));
        $tied = array_values(array_filter($scored, fn (array $candidate): bool => $candidate['score'] === $best));
        usort($tied, fn (array $a, array $b): int => strcmp($a['published'], $b['published']));

        $chosen = $tied[0];

        if ($releaseDate !== null) {
            foreach ($tied as $candidate) {
                if ($candidate['published'] !== '' && substr($candidate['published'], 0, 10) <= $releaseDate) {
                    $chosen = $candidate;
                }
            }
        }

        return ['key' => $chosen['video']['key'], 'name' => $chosen['video']['name'], 'type' => $chosen['video']['type']];
    }

    /**
     * The US premiere (type 3, else the earliest type 2) and the certification that goes with it.
     *
     * @param  array<string, mixed>  $data
     * @return array{date: ?string, certification: ?string}|null
     */
    private function usRelease(array $data): ?array
    {
        $releases = [];

        foreach ((array) ($data['release_dates']['results'] ?? []) as $country) {
            if (is_array($country) && ($country['iso_3166_1'] ?? null) === 'US') {
                $releases = array_values(array_filter((array) ($country['release_dates'] ?? []), is_array(...)));
            }
        }

        $certification = collect($releases)->pluck('certification')->first(fn (mixed $value): bool => is_string($value) && $value !== '');
        $date = null;

        foreach ([self::RELEASE_TYPE_THEATRICAL, self::RELEASE_TYPE_THEATRICAL_LIMITED] as $type) {
            $matching = array_filter($releases, fn (array $release): bool => ($release['type'] ?? null) === $type && is_string($release['release_date'] ?? null));
            usort($matching, fn (array $a, array $b): int => strcmp($a['release_date'], $b['release_date']));

            if ($matching !== []) {
                $date = substr($matching[0]['release_date'], 0, 10);
                $certification = ($matching[0]['certification'] ?? '') ?: $certification;

                break;
            }
        }

        if ($date === null && $certification === null) {
            return null;
        }

        return ['date' => $date, 'certification' => $certification];
    }
}
