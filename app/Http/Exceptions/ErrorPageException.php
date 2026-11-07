<?php

namespace App\Http\Exceptions;

use App\Http\Support\UserMovies;
use App\Models\Movie;
use App\Models\User;
use Closure;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * An HTTP error the app raises itself and shows on the shared error page. The exception message is the page's
 * title, and the description is the line under it.
 */
abstract class ErrorPageException extends HttpException
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(int $statusCode, string $title, public readonly string $description, array $headers = [], public readonly ?array $data = null)
    {
        parent::__construct($statusCode, $title, headers: $headers);
    }

    /**
     * The data of an error page about one stored Movie: its catalog id and the viewer's facts about it, so they can
     * tidy their lists from the page. Nothing when the page is about no Movie.
     *
     * @return array{tmdbId: int, userMovies: Closure(): array<int, array{watchlisted: bool, watched: bool, watchedDate: ?string}>}|null
     */
    protected static function movieData(?Movie $movie, ?User $user): ?array
    {
        return $movie === null ? null : [
            'tmdbId' => $movie->tmdb_id,
            'userMovies' => fn (): array => UserMovies::for($user, [$movie]),
        ];
    }
}
