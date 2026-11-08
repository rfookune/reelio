<?php

namespace App\Http\Support;

use App\Enums\UserMovieList;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Movie picker: a uniformly random pick from a user's candidates, the Movies on their Watchlist (rewatches
 * included) that still have details, so Removed and cap-cleared Movies are never picked. It reads only Postgres
 * and keeps no state between picks.
 */
class MoviePicker
{
    public function __construct(private User $user) {}

    /**
     * Picks one candidate matching the criteria and counts the pool it was picked from. The previous pick counts
     * towards the pool but is left out when two or more candidates match.
     *
     * @return array{movie: ?Movie, poolSize: int}
     */
    public function pick(PickerCriteria $criteria): array
    {
        $pool = $this->candidates()->when($criteria->genreIds !== [], fn (Builder $query): Builder => $query
            ->whereHas('genres', fn (Builder $genres): Builder => $genres->whereIn('genres.id', $criteria->genreIds)));
        $poolSize = $pool->clone()->count();
        $movie = $poolSize === 0 ? null : $pool
            ->when($poolSize > 1 && $criteria->previousTmdbId !== null, fn (Builder $query): Builder => $query->where('tmdb_id', '!=', $criteria->previousTmdbId))
            ->inRandomOrder()
            ->first();

        return ['movie' => $movie, 'poolSize' => $poolSize];
    }

    /**
     * The genres at least one candidate has, by name, so the filter never offers a genre that matches nothing.
     *
     * @return list<array{id: int, name: string}>
     */
    public function genres(): array
    {
        return array_values(Genre::query()
            ->whereHas('movies', fn (Builder $movies): Builder => $movies->whereIn('titles.id', $this->candidates()->select('titles.id')))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Genre $genre): array => ['id' => $genre->id, 'name' => $genre->name])
            ->all());
    }

    /** @return Builder<Movie> */
    private function candidates(): Builder
    {
        return Movie::query()->whereNotNull('title')->whereIn('id', UserMovie::query()
            ->select('title_id')
            ->whereBelongsTo($this->user)
            ->whereNotNull(UserMovieList::Watchlist->dateColumn()));
    }
}
