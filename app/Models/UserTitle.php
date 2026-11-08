<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * A user's facts about one Title: on their Watchlist since the Date added, and Watched on the Watched date. Each
 * is independent, and a row holding neither doesn't exist.
 *
 * @property CarbonImmutable|null $watchlisted_at
 * @property CarbonImmutable|null $watched_at
 */
#[Fillable(['user_id', 'title_id', 'watchlisted_at', 'watched_at'])]
class UserTitle extends Model
{
    protected $table = 'user_titles';

    /**
     * The facts of a Title the user holds nothing about.
     *
     * @var array{watchlisted_at: null, watched_at: null}
     */
    public const NO_FACTS = ['watchlisted_at' => null, 'watched_at' => null];

    /**
     * The facts before and after an action that found nothing to change about a Title the user holds nothing about.
     *
     * @var array{before: array{watchlisted_at: null, watched_at: null}, after: array{watchlisted_at: null, watched_at: null}}
     */
    public const NO_CHANGE = ['before' => self::NO_FACTS, 'after' => self::NO_FACTS];

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
     * Changes a user's facts about a Title under the row's lock, so concurrent actions on the same Title run one
     * after the other and each sees what the last one left. With no row, an empty one is inserted and locked
     * first, whether or not a concurrent action inserted it already; if another action deletes it before the lock
     * is taken, the insert is tried again. Afterwards the row is saved, or deleted when it holds neither fact, so
     * an empty row never outlives the transaction. Returns the facts before and after, as timestamps that compare
     * equal when nothing changed.
     *
     * @param  Closure(static): void  $apply
     * @return array{before: array{watchlisted_at: ?string, watched_at: ?string}, after: array{watchlisted_at: ?string, watched_at: ?string}}
     */
    public static function change(User $user, Title $title, Closure $apply): array
    {
        return DB::transaction(function () use ($user, $title, $apply): array {
            $locked = fn (): ?static => static::query()->whereBelongsTo($user)->whereBelongsTo($title, 'title')->lockForUpdate()->first();
            $row = $locked();

            for ($attempt = 1; $row === null; $attempt++) {
                throw_if($attempt > 3, LogicException::class, 'The facts row kept disappearing before it could be locked.');
                static::query()->insertOrIgnore(['user_id' => $user->id, 'title_id' => $title->id]);
                $row = $locked();
            }

            $before = $row->snapshot();
            $apply($row);
            $row->watchlisted_at === null && $row->watched_at === null ? $row->delete() : $row->save();

            return ['before' => $before, 'after' => $row->snapshot()];
        });
    }

    /**
     * Puts a user's facts about a Title back to `$restored`, under the row's lock as `change()` takes it, but only
     * if they still stand at `$expected`. Facts held in neither leave no row. Returns whether they did.
     *
     * @param  array{watchlisted_at: ?string, watched_at: ?string}  $expected
     * @param  array{watchlisted_at: ?string, watched_at: ?string}  $restored
     */
    public static function restore(User $user, Title $title, array $expected, array $restored): bool
    {
        $asExpected = false;

        self::change($user, $title, function (self $row) use ($expected, $restored, &$asExpected): void {
            $asExpected = $row->snapshot() === $expected;

            if ($asExpected) {
                $row->watchlisted_at = CarbonImmutable::make($restored['watchlisted_at']);
                $row->watched_at = CarbonImmutable::make($restored['watched_at']);
            }
        });

        return $asExpected;
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

    /** @return BelongsTo<Title, $this> */
    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }
}
