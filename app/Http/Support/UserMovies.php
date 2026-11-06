<?php

namespace App\Http\Support;

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;

/**
 * The `userMovies` page prop: the viewer's facts about each Movie on the page, keyed by catalog id, so every
 * surface showing the same Movie reads one entry. Guests, and Movies the viewer holds no facts about, get an
 * entry with both facts false. The Watched date is a UTC timestamp.
 */
class UserMovies
{
    /**
     * @param  iterable<Movie>  $movies
     * @return array<int, array{watchlisted: bool, watched: bool, watchedDate: ?string}>
     */
    public static function for(?User $user, iterable $movies): array
    {
        $movies = collect($movies);
        $facts = $user === null ? collect() : UserMovie::query()
            ->whereBelongsTo($user)
            ->whereIn('movie_id', $movies->pluck('id'))
            ->get()
            ->keyBy('movie_id');

        return $movies->mapWithKeys(fn (Movie $movie): array => [$movie->tmdb_id => [
            'watchlisted' => $facts->get($movie->id)?->watchlisted_at !== null,
            'watched' => $facts->get($movie->id)?->watched_at !== null,
            'watchedDate' => $facts->get($movie->id)?->watched_at?->toJSON(),
        ]])->all();
    }
}
