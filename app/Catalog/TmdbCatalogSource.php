<?php

namespace App\Catalog;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TmdbCatalogSource implements CatalogSource
{
    public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
    {
        try {
            $response = Http::baseUrl(config('catalog.tmdb.base_url'))
                ->withToken(config('catalog.tmdb.token'))
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(3)
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
        );
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

        return array_values(array_map(fn (array $member): array => [
            'id' => $member['id'],
            'name' => $member['name'],
            'character' => ($member['character'] ?? '') !== '' ? $member['character'] : null,
            'profilePath' => $member['profile_path'] ?? null,
        ], array_slice($members, 0, 12)));
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

        foreach ([3, 2] as $type) {
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
