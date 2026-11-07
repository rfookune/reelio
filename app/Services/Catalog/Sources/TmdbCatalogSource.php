<?php

namespace App\Services\Catalog\Sources;

use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Enums\OfferType;
use App\Services\Catalog\Enums\SectionKind;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class TmdbCatalogSource implements CatalogSource
{
    /** TMDB's watch-provider groups and the offer type each becomes. */
    private const OFFER_TYPES = ['flatrate' => OfferType::Stream, 'free' => OfferType::Free, 'ads' => OfferType::Free, 'rent' => OfferType::Rent, 'buy' => OfferType::Buy];

    private const IMAGE_PATH_PATTERN = '/^\/[A-Za-z0-9_-]+\.(jpg|png|svg)$/D';

    private const MAXIMUM_DISPLAY_PRIORITY = 2_147_483_647;

    private const YOUTUBE_KEY_PATTERN = '/^[A-Za-z0-9_-]{11}$/D';

    private const MAX_RETRY_AFTER_MILLISECONDS = 2000;

    /** TMDB release type for a theatrical release. */
    private const RELEASE_TYPE_THEATRICAL = 3;

    /** TMDB release type for a limited theatrical release. */
    private const RELEASE_TYPE_THEATRICAL_LIMITED = 2;

    /** TMDB release type for a digital release. */
    private const RELEASE_TYPE_DIGITAL = 4;

    public function __construct(private BackgroundThrottle $backgroundThrottle) {}

    public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
    {
        $response = $this->get("movie/{$tmdbId}", [
            'language' => $context->language,
            'include_adult' => 'false',
            'include_video_language' => explode('-', $context->language)[0].',null',
            'append_to_response' => 'credits,videos,release_dates,watch/providers',
        ], $context);

        if ($response === null) {
            return null;
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
                'watch/providers' => ['sometimes', 'nullable', 'array'],
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
            crew: $this->crew($data['credits'] ?? []),
            certification: $usRelease['certification'] ?? null,
            usReleaseDate: $usRelease['date'] ?? null,
            usDigitalReleaseDate: $this->usDigitalReleaseDate($data),
            availability: $this->availability($data['watch/providers'] ?? []),
            trailer: $this->trailer($data['videos'] ?? [], $context->language, ($data['release_date'] ?? null) ?: null),
        );
    }

    /**
     * One page of a list. Trending is global, so only Coming soon, which is about US release dates, carries a
     * region. It and the genre collections read TMDB's discover list by popularity; Coming soon takes the same
     * release types, theatrical and digital, that its US date is read from. Adult listings, listings that aren't
     * well formed and repeats within the page are dropped; an unsafe image path is cleared.
     */
    public function section(SectionQuery $query, int $page, CatalogContext $context): SectionPage
    {
        $path = match ($query->kind) {
            SectionKind::Trending => 'trending/movie/week',
            SectionKind::Popular => 'movie/popular',
            SectionKind::TopRated => 'movie/top_rated',
            SectionKind::ComingSoon, SectionKind::Genre => 'discover/movie',
        };
        $filters = match ($query->kind) {
            SectionKind::ComingSoon => [
                'region' => 'US',
                'with_release_type' => implode('|', [self::RELEASE_TYPE_THEATRICAL_LIMITED, self::RELEASE_TYPE_THEATRICAL, self::RELEASE_TYPE_DIGITAL]),
                'release_date.gte' => (string) $query->releasedFrom,
                'release_date.lte' => (string) $query->releasedUntil,
                'sort_by' => 'popularity.desc',
            ],
            SectionKind::Genre => [
                'with_genres' => (int) $query->genreId,
                'vote_count.gte' => (int) $query->minimumVotes,
                'sort_by' => 'popularity.desc',
            ],
            default => [],
        };
        $response = $this->get($path, ['language' => $context->language, 'include_adult' => 'false', 'page' => $page, ...$filters], $context)
            ?? throw new SourceUnavailable('The Catalog source has no such list page.');
        $data = $this->listPage($response);

        return new SectionPage($this->listings($data['results']), $data['total_pages']);
    }

    /**
     * One page of a title search, in TMDB's order, Movies without a poster included. Adult listings, listings that
     * aren't well formed and repeats within the page are dropped; an unsafe image path is cleared.
     */
    public function search(string $query, int $page, CatalogContext $context): SearchResults
    {
        $response = $this->get('search/movie', ['query' => $query, 'page' => $page, 'language' => $context->language, 'include_adult' => 'false'], $context)
            ?? throw new SourceUnavailable('The Catalog source has no such search page.');
        $data = $this->listPage($response);
        $totalResults = $response->json('total_results');

        if (! is_int($totalResults) || $totalResults < 0) {
            throw new SourceUnavailable('The Catalog source returned an invalid result count.');
        }

        return new SearchResults($this->listings($data['results']), $data['total_pages'], $totalResults);
    }

    /**
     * The Movie's IMDb title id from its external ids, a response far smaller than its details. TMDB's 404 means it
     * doesn't know the Movie, and an id that isn't an IMDb title id (`tt` and digits) counts as none.
     */
    public function imdbId(int $tmdbId, CatalogContext $context): ?string
    {
        $response = $this->get("movie/{$tmdbId}/external_ids", [], $context);

        if ($response === null) {
            return null;
        }

        try {
            $data = Validator::make((array) $response->json(), [
                'id' => ['required', 'integer', 'in:'.$tmdbId],
                'imdb_id' => ['nullable', 'string', 'max:255'],
            ])->validate();
        } catch (ValidationException $exception) {
            throw new SourceUnavailable('The Catalog source returned invalid external ids.', previous: $exception);
        }

        $imdbId = $data['imdb_id'] ?? null;

        return is_string($imdbId) && preg_match('/^tt\d+$/D', $imdbId) === 1 ? $imdbId : null;
    }

    /**
     * TMDB's Movie genre list, by id, with names in the context's language.
     *
     * @return list<array{id: int, name: string}>
     */
    public function genres(CatalogContext $context): array
    {
        $response = $this->get('genre/movie/list', ['language' => $context->language], $context)
            ?? throw new SourceUnavailable('The Catalog source has no genre list.');

        try {
            $data = Validator::make((array) $response->json(), [
                'genres' => ['required', 'array', 'min:1'],
                'genres.*.id' => ['required', 'integer', 'min:1', 'distinct'],
                'genres.*.name' => ['required', 'string', 'max:255'],
            ])->validate();
        } catch (ValidationException $exception) {
            throw new SourceUnavailable('The Catalog source returned an invalid genre list.', previous: $exception);
        }

        return array_values(array_map(fn (array $genre): array => ['id' => (int) $genre['id'], 'name' => $genre['name']], $data['genres']));
    }

    /**
     * A list page's body: its results and page count.
     *
     * @return array{results: array<mixed>, total_pages: int}
     */
    private function listPage(Response $response): array
    {
        try {
            $data = Validator::make((array) $response->json(), [
                'total_pages' => ['required', 'integer', 'min:0'],
                'results' => ['present', 'array'],
            ])->validate();
        } catch (ValidationException $exception) {
            throw new SourceUnavailable('The Catalog source returned an invalid list.', previous: $exception);
        }

        return ['results' => (array) $data['results'], 'total_pages' => (int) $data['total_pages']];
    }

    /**
     * The page's well-formed, non-adult listings, each once.
     *
     * @param  array<mixed>  $results
     * @return list<MovieListing>
     */
    private function listings(array $results): array
    {
        $listings = [];

        foreach ($results as $result) {
            $listing = is_array($result) ? $this->listing($result) : null;

            if ($listing !== null && ! isset($listings[$listing->tmdbId])) {
                $listings[$listing->tmdbId] = $listing;
            }
        }

        return array_values($listings);
    }

    /**
     * A GET with the profile's timeout and retries. A 404 is an answer, returned as null; a 401 is a configuration
     * error; anything else unsuccessful, or no connection, is a failure. Each background attempt, a retry too, first
     * takes its turn in the background throttle.
     *
     * @param  array<string, string|int>  $query
     */
    private function get(string $path, array $query, CatalogContext $context): ?Response
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

    /**
     * A well-formed, non-adult list entry, or null.
     *
     * @param  array<mixed>  $result
     */
    private function listing(array $result): ?MovieListing
    {
        $validator = Validator::make($result, [
            'id' => ['required', 'integer', 'min:1'],
            'adult' => ['sometimes', 'boolean', 'declined'],
            'title' => ['nullable', 'string', 'max:255'],
            'overview' => ['nullable', 'string'],
            'release_date' => ['nullable', 'string'],
            'poster_path' => ['nullable', 'string', 'max:255'],
            'backdrop_path' => ['nullable', 'string', 'max:255'],
            'vote_average' => ['nullable', 'numeric', 'between:0,10'],
            'vote_count' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return null;
        }

        $data = $validator->validated();
        $releaseDate = $data['release_date'] ?? null;

        return new MovieListing(
            tmdbId: (int) $data['id'],
            title: $data['title'] ?? null,
            overview: ($data['overview'] ?? null) ?: null,
            releaseDate: is_string($releaseDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $releaseDate) === 1 ? $releaseDate : null,
            posterPath: $this->safeImagePath($data['poster_path'] ?? null),
            backdropPath: $this->safeImagePath($data['backdrop_path'] ?? null),
            voteAverage: isset($data['vote_average']) ? (float) $data['vote_average'] : null,
            voteCount: isset($data['vote_count']) ? (int) $data['vote_count'] : null,
        );
    }

    private function safeImagePath(?string $path): ?string
    {
        return is_string($path) && preg_match(self::IMAGE_PATH_PATTERN, $path) === 1 ? $path : null;
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
     * The directors, writers and producers, one entry per person and job, ordered by MovieDetails::CREW_JOBS and then
     * by TMDB's own order.
     *
     * @param  array<string, mixed>  $credits
     * @return list<array{id: int, name: string, job: string}>
     */
    private function crew(array $credits): array
    {
        $crew = [];

        foreach ((array) ($credits['crew'] ?? []) as $member) {
            if (! is_array($member) || ! is_int($member['id'] ?? null) || ! is_string($member['name'] ?? null) || ! in_array($member['job'] ?? null, MovieDetails::CREW_JOBS, true)) {
                continue;
            }

            $crew["{$member['id']}:{$member['job']}"] ??= ['id' => $member['id'], 'name' => $member['name'], 'job' => $member['job']];
        }

        $order = array_flip(MovieDetails::CREW_JOBS);

        return array_values(collect($crew)->sortBy(fn (array $member): int => $order[$member['job']])->all());
    }

    /**
     * The whole billed cast, in billing order.
     *
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
        ], $members);
    }

    /**
     * Availability in the supported countries. TMDB's `flatrate` is a subscription stream; its `free` and `ads`
     * both become `free`, keeping one row per service with its best `display_priority`. TMDB's own `link` is
     * never used.
     *
     * @param  array<string, mixed>  $watchProviders
     * @return list<array{serviceId: int, serviceName: string, logoPath: ?string, country: string, offerType: string, displayPriority: int}>
     */
    private function availability(array $watchProviders): array
    {
        $offers = [];
        $results = (array) ($watchProviders['results'] ?? []);

        foreach (config('catalog.supported_countries') as $country) {
            if (! is_array($results[$country] ?? null)) {
                continue;
            }

            foreach (self::OFFER_TYPES as $tmdbType => $offerType) {
                foreach ((array) ($results[$country][$tmdbType] ?? []) as $entry) {
                    if (! is_array($entry) || ! is_int($entry['provider_id'] ?? null) || ! is_string($entry['provider_name'] ?? null) || $entry['provider_name'] === '') {
                        continue;
                    }

                    $priority = is_int($entry['display_priority'] ?? null) ? $entry['display_priority'] : PHP_INT_MAX;
                    $key = "{$country}:{$offerType->value}:{$entry['provider_id']}";

                    if (isset($offers[$key]) && $offers[$key]['displayPriority'] <= $priority) {
                        continue;
                    }

                    $logoPath = $entry['logo_path'] ?? null;

                    $offers[$key] = [
                        'serviceId' => $entry['provider_id'],
                        'serviceName' => $entry['provider_name'],
                        'logoPath' => is_string($logoPath) && preg_match(self::IMAGE_PATH_PATTERN, $logoPath) === 1 ? $logoPath : null,
                        'country' => $country,
                        'offerType' => $offerType->value,
                        'displayPriority' => max(0, min($priority, self::MAXIMUM_DISPLAY_PRIORITY)),
                    ];
                }
            }
        }

        return array_values($offers);
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
     * The earliest US digital release.
     *
     * @param  array<string, mixed>  $data
     */
    private function usDigitalReleaseDate(array $data): ?string
    {
        $dates = [];

        foreach ((array) ($data['release_dates']['results'] ?? []) as $country) {
            if (! is_array($country) || ($country['iso_3166_1'] ?? null) !== 'US') {
                continue;
            }

            foreach ((array) ($country['release_dates'] ?? []) as $release) {
                if (is_array($release) && ($release['type'] ?? null) === self::RELEASE_TYPE_DIGITAL && is_string($release['release_date'] ?? null)) {
                    $dates[] = substr($release['release_date'], 0, 10);
                }
            }
        }

        return $dates === [] ? null : min($dates);
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
