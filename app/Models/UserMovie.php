<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Closure;
use Database\Factories\UserMovieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

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

    /**
     * Changes a user's facts about a Movie under the row's lock, so concurrent actions on the same Movie run one
     * after the other and each sees what the last one left. With no row, an empty one is inserted and locked
     * first, whether or not a concurrent action inserted it already; if another action deletes it before the lock
     * is taken, the insert is tried again. Afterwards the row is saved, or deleted when it holds neither fact, so
     * an empty row never outlives the transaction. Returns the facts before and after, as timestamps that compare
     * equal when nothing changed.
     *
     * @param  Closure(self): void  $apply
     * @return array{before: array{watchlisted_at: ?string, watched_at: ?string}, after: array{watchlisted_at: ?string, watched_at: ?string}}
     */
    public static function change(User $user, Movie $movie, Closure $apply): array
    {
        return DB::transaction(function () use ($user, $movie, $apply): array {
            $locked = fn (): ?self => self::query()->whereBelongsTo($user)->whereBelongsTo($movie)->lockForUpdate()->first();
            $row = $locked();

            for ($attempt = 1; $row === null; $attempt++) {
                throw_if($attempt > 3, LogicException::class, 'The facts row kept disappearing before it could be locked.');
                self::query()->insertOrIgnore(['user_id' => $user->id, 'movie_id' => $movie->id]);
                $row = $locked();
            }

            $before = $row->snapshot();
            $apply($row);
            $row->watchlisted_at === null && $row->watched_at === null ? $row->delete() : $row->save();

            return ['before' => $before, 'after' => $row->snapshot()];
        });
    }

    /** @return array{watchlisted_at: ?string, watched_at: ?string} */
    private function snapshot(): array
    {
        return ['watchlisted_at' => $this->watchlisted_at?->toJSON(), 'watched_at' => $this->watched_at?->toJSON()];
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
