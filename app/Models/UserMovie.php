<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\UserMovieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's facts about one Movie: on their Watchlist since the Date added, and Watched on the Watched date. Each
 * is independent, and a row holding neither doesn't exist.
 *
 * @property CarbonImmutable|null $watchlisted_at
 * @property CarbonImmutable|null $watched_at
 */
#[Fillable(['user_id', 'movie_id', 'watchlisted_at', 'watched_at'])]
class UserMovie extends Model
{
    /** @use HasFactory<UserMovieFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'watchlisted_at' => 'immutable_datetime',
            'watched_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Movie, $this> */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }
}
