<?php

namespace App\Services\Catalog\Sources\Tmdb;

use App\Services\Catalog\Sources\Details\EpisodeDetails;
use App\Services\Catalog\Sources\Details\SeasonDetails;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\SourceUnavailable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TvShowParser
{
    public function __construct(private AppendedDataParser $appends) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function details(array $payload, int $tmdbId): array
    {
        $data = $this->validateTvData($payload, [
            'id' => ['required', 'integer', 'in:'.$tmdbId],
            'name' => ['nullable', 'string', 'max:255'],
            'original_name' => ['nullable', 'string', 'max:255'],
            'overview' => ['nullable', 'string'],
            'tagline' => ['nullable', 'string'],
            'first_air_date' => ['nullable', 'date_format:Y-m-d'],
            'last_air_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'string', 'max:255'],
            'number_of_seasons' => ['required', 'integer', 'min:0'],
            'number_of_episodes' => ['nullable', 'integer', 'min:0'],
            'poster_path' => ['nullable', 'string', 'max:255'],
            'backdrop_path' => ['nullable', 'string', 'max:255'],
            'original_language' => ['nullable', 'string', 'max:255'],
            'vote_average' => ['nullable', 'numeric', 'between:0,10'],
            'vote_count' => ['nullable', 'integer', 'min:0'],
            'seasons' => ['present', 'array'],
            'seasons.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'seasons.*.season_number' => ['required', 'integer', 'min:0', 'distinct'],
            'seasons.*.episode_count' => ['required', 'integer', 'min:0'],
            'genres' => ['sometimes', 'array'],
            'genres.*.id' => ['required', 'integer', 'min:1'],
            'genres.*.name' => ['required', 'string', 'max:255'],
            'created_by' => ['sometimes', 'array'],
            'created_by.*.name' => ['required', 'string'],
            'networks' => ['sometimes', 'array'],
            'networks.*.name' => ['required', 'string'],
            'external_ids' => ['present', 'array'],
            'external_ids.imdb_id' => ['present', 'nullable', 'string', 'max:255'],
            'content_ratings' => ['present', 'array'],
            'content_ratings.results' => ['present', 'array'],
            'credits' => ['present', 'array'],
            'credits.cast' => ['present', 'array'],
            'credits.crew' => ['present', 'array'],
            'videos' => ['present', 'array'],
            'videos.results' => ['present', 'array'],
            'watch/providers' => ['present', 'array'],
            'watch/providers.results' => ['present', 'array'],
        ]);

        $numberedSeasons = array_filter($data['seasons'], fn (array $season): bool => $season['season_number'] > 0);
        if (count($numberedSeasons) !== (int) $data['number_of_seasons']) {
            throw new SourceUnavailable('The Catalog source returned an incomplete Season inventory.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $summary
     */
    public function season(array $payload, array $summary): SeasonDetails
    {
        $season = $this->validateTvData($payload, [
            'id' => ['required', 'integer', 'in:'.$summary['id']],
            'season_number' => ['required', 'integer', 'in:'.$summary['season_number']],
            'name' => ['nullable', 'string', 'max:255'],
            'overview' => ['nullable', 'string'],
            'poster_path' => ['nullable', 'string', 'max:255'],
            'air_date' => ['nullable', 'date_format:Y-m-d'],
            'episodes' => ['present', 'array', 'size:'.$summary['episode_count']],
            'episodes.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'episodes.*.season_number' => ['required', 'integer', 'in:'.$summary['season_number']],
            'episodes.*.episode_number' => ['required', 'integer', 'min:0', 'distinct'],
            'episodes.*.name' => ['nullable', 'string', 'max:255'],
            'episodes.*.overview' => ['nullable', 'string'],
            'episodes.*.air_date' => ['nullable', 'date_format:Y-m-d'],
            'episodes.*.runtime' => ['nullable', 'integer', 'min:0'],
            'episodes.*.episode_type' => ['nullable', 'string', 'max:255'],
        ]);
        $episodes = array_map(fn (array $episode): EpisodeDetails => new EpisodeDetails(
            tmdbId: (int) $episode['id'], seasonNumber: (int) $episode['season_number'], number: (int) $episode['episode_number'],
            name: $episode['name'] ?? null, overview: $episode['overview'] ?? null, airDate: ($episode['air_date'] ?? null) ?: null,
            runtime: isset($episode['runtime']) ? (int) $episode['runtime'] : null, episodeType: $episode['episode_type'] ?? null,
        ), array_values($season['episodes']));

        return new SeasonDetails(
            tmdbId: (int) $season['id'], number: (int) $season['season_number'], name: $season['name'] ?? null,
            overview: $season['overview'] ?? null, posterPath: $this->appends->safeImagePath($season['poster_path'] ?? null),
            airDate: ($season['air_date'] ?? null) ?: null, episodeCount: count($episodes), episodes: $episodes,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<SeasonDetails>  $seasons
     */
    public function parse(array $data, array $seasons, string $language): TvShowDetails
    {
        $certification = null;
        foreach ($data['content_ratings']['results'] ?? [] as $rating) {
            if (($rating['iso_3166_1'] ?? null) === 'US' && is_string($rating['rating'] ?? null)) {
                $certification = $rating['rating'] ?: null;
                break;
            }
        }

        return new TvShowDetails(
            tmdbId: (int) $data['id'], title: $data['name'] ?? null, originalTitle: $data['original_name'] ?? null,
            overview: $data['overview'] ?? null, tagline: $data['tagline'] ?? null,
            firstAirDate: ($data['first_air_date'] ?? null) ?: null, lastAirDate: ($data['last_air_date'] ?? null) ?: null,
            status: $data['status'] ?? null, seasonCount: isset($data['number_of_seasons']) ? (int) $data['number_of_seasons'] : null,
            episodeCount: isset($data['number_of_episodes']) ? (int) $data['number_of_episodes'] : null,
            posterPath: $this->appends->safeImagePath($data['poster_path'] ?? null), backdropPath: $this->appends->safeImagePath($data['backdrop_path'] ?? null),
            originalLanguage: $data['original_language'] ?? null,
            voteAverage: isset($data['vote_average']) ? (float) $data['vote_average'] : null,
            voteCount: isset($data['vote_count']) ? (int) $data['vote_count'] : null,
            imdbId: $data['external_ids']['imdb_id'] ?? null, genres: array_map(fn (array $genre): array => ['id' => (int) $genre['id'], 'name' => $genre['name']], array_values($data['genres'] ?? [])),
            creators: array_column($data['created_by'] ?? [], 'name'), networks: array_column($data['networks'] ?? [], 'name'),
            cast: $this->appends->cast($data['credits'] ?? []), certification: $certification,
            trailer: $this->appends->trailer($data['videos'] ?? [], $language, ($data['first_air_date'] ?? null) ?: null),
            availability: $this->appends->availability($data['watch/providers'] ?? []), seasons: $seasons,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, list<string>>  $rules
     * @return array<string, mixed>
     */
    private function validateTvData(array $data, array $rules): array
    {
        try {
            return Validator::make($data, $rules)->validate();
        } catch (ValidationException $exception) {
            throw new SourceUnavailable('The Catalog source returned invalid TV details.', previous: $exception);
        }
    }
}
