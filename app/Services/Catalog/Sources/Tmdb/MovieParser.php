<?php

namespace App\Services\Catalog\Sources\Tmdb;

use App\Enums\TitleKind;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\Listings\MovieListing;
use App\Services\Catalog\Sources\SourceUnavailable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MovieParser
{
    public const RELEASE_TYPE_THEATRICAL = 3;

    public const RELEASE_TYPE_THEATRICAL_LIMITED = 2;

    public const RELEASE_TYPE_DIGITAL = 4;

    public function __construct(private AppendedDataParser $appends) {}

    /** @param array<string, mixed> $payload */
    public function parse(array $payload, int $tmdbId, string $language): MovieDetails
    {
        try {
            $data = Validator::make($payload, [
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
            cast: $this->appends->cast($data['credits'] ?? []),
            crew: $this->appends->crew($data['credits'] ?? []),
            certification: $usRelease['certification'] ?? null,
            usReleaseDate: $usRelease['date'] ?? null,
            usDigitalReleaseDate: $this->usDigitalReleaseDate($data),
            availability: $this->appends->availability($data['watch/providers'] ?? []),
            trailer: $this->appends->trailer($data['videos'] ?? [], $language, ($data['release_date'] ?? null) ?: null),
        );
    }

    /**
     * A list page's body: its results and page count.
     *
     * @param  array<string, mixed>  $payload
     * @return array{results: array<mixed>, total_pages: int}
     */
    public function listPage(array $payload): array
    {
        try {
            $data = Validator::make($payload, [
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
    public function listings(array $results): array
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
     * The page's well-formed, non-adult TV Show entries of the TV discover list, each once.
     *
     * @param  array<mixed>  $results
     * @return list<MovieListing>
     */
    public function tvListings(array $results): array
    {
        $listings = [];

        foreach ($results as $result) {
            $listing = is_array($result) ? $this->listing(self::asListingEntry($result), TitleKind::TvShow) : null;

            if ($listing !== null && ! isset($listings[$listing->tmdbId])) {
                $listings[$listing->tmdbId] = $listing;
            }
        }

        return array_values($listings);
    }

    /**
     * A TV Show entry with its Name and first-air date as the `title` and `release_date` a listing reads.
     *
     * @param  array<mixed>  $result
     * @return array<mixed>
     */
    private static function asListingEntry(array $result): array
    {
        return [...$result, 'title' => $result['name'] ?? null, 'release_date' => $result['first_air_date'] ?? null];
    }

    /**
     * The page's well-formed, non-adult Movie and TV Show entries of a search across every kind, each once for its
     * kind, told apart by `media_type`; people and the rest are dropped.
     *
     * @param  array<mixed>  $results
     * @return list<MovieListing>
     */
    public function mixedListings(array $results): array
    {
        $listings = [];

        foreach ($results as $result) {
            $kind = is_array($result) ? match ($result['media_type'] ?? null) {
                'movie' => TitleKind::Movie,
                'tv' => TitleKind::TvShow,
                default => null,
            } : null;

            if ($kind === null) {
                continue;
            }

            $listing = $this->listing($kind === TitleKind::TvShow ? self::asListingEntry($result) : $result, $kind);

            if ($listing !== null && ! isset($listings[$kind->value.$listing->tmdbId])) {
                $listings[$kind->value.$listing->tmdbId] = $listing;
            }
        }

        return array_values($listings);
    }

    /**
     * A well-formed, non-adult list entry, or null.
     *
     * @param  array<mixed>  $result
     */
    private function listing(array $result, TitleKind $kind = TitleKind::Movie): ?MovieListing
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
            posterPath: $this->appends->safeImagePath($data['poster_path'] ?? null),
            backdropPath: $this->appends->safeImagePath($data['backdrop_path'] ?? null),
            voteAverage: isset($data['vote_average']) ? (float) $data['vote_average'] : null,
            voteCount: isset($data['vote_count']) ? (int) $data['vote_count'] : null,
            kind: $kind,
        );
    }

    /** @param array<string, mixed> $payload */
    public function imdbId(array $payload, int $tmdbId): ?string
    {
        try {
            $data = Validator::make($payload, [
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
     * @param  array<string, mixed>  $payload
     * @return list<array{id: int, name: string}>
     */
    public function genres(array $payload): array
    {
        try {
            $data = Validator::make($payload, [
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
     * The Streaming services of a watch provider list, ordered by display priority in the country, then by name.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{id: int, name: string}>
     */
    public function streamingServices(array $payload, string $country): array
    {
        try {
            $data = Validator::make($payload, [
                'results' => ['required', 'array', 'min:1'],
                'results.*.provider_id' => ['required', 'integer', 'min:1', 'distinct'],
                'results.*.provider_name' => ['required', 'string', 'min:1', 'max:255'],
                'results.*.display_priorities' => ['sometimes', 'nullable', 'array'],
            ])->validate();
        } catch (ValidationException $exception) {
            throw new SourceUnavailable('The Catalog source returned an invalid Streaming service list.', previous: $exception);
        }

        $services = array_map(fn (array $service): array => [
            'id' => (int) $service['provider_id'],
            'name' => $service['provider_name'],
            'priority' => is_int($service['display_priorities'][$country] ?? null) ? $service['display_priorities'][$country] : PHP_INT_MAX,
        ], array_values($data['results']));

        usort($services, fn (array $a, array $b): int => [$a['priority'], $a['name']] <=> [$b['priority'], $b['name']]);

        return array_map(fn (array $service): array => ['id' => $service['id'], 'name' => $service['name']], $services);
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
