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
            ])->validate();
        } catch (ValidationException $exception) {
            throw new SourceUnavailable('The Catalog source returned invalid movie details.', previous: $exception);
        }

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
        );
    }
}
