<?php

namespace App\Http\Support;

use App\Models\Episode;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserEpisode;
use App\Models\UserTitle;
use App\Services\TvShows\WatchedProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Marks or clears a set of a TV Show's Episodes for a user as one action with one Undo. A mark touches only the
 * eligible Episodes the user has not watched yet, so earlier Watched dates stay; becoming Completed takes the
 * TV Show off the Watchlist, and nothing puts it back.
 *
 * @phpstan-import-type Progress from WatchedProgress
 */
class BulkWatchedChange
{
    private const INSERT_CHUNK = 500;

    public function __construct(private WatchedProgress $progress, private LatestTvShowAction $latest) {}

    /**
     * @param  Builder<Episode>  $eligible  the aired, non-Removed Episodes in scope
     * @param  bool  $clearsAll  whether a clear removes every mark on the TV Show rather than only the eligible ones
     * @return array{progress: Progress, undoToken: string, watchlisted: bool}
     */
    public function apply(Request $request, User $user, TvShow $tvShow, bool $watched, Builder $eligible, bool $clearsAll): array
    {
        $token = $this->latest->change($request, $user, $tvShow, $tvShow->tmdb_id, function () use ($user, $tvShow, $watched, $eligible, $clearsAll): void {
            $wasCompleted = $this->progress->for($tvShow, $user)['state'] === 'Completed';
            if ($watched) {
                $this->markUnwatched($user, $eligible);
            } elseif ($clearsAll) {
                UserEpisode::query()->where('user_id', $user->id)->whereIn('episode_id', $tvShow->episodes()->select('id'))->delete();
            } else {
                UserEpisode::query()->where('user_id', $user->id)->whereIn('episode_id', (clone $eligible)->select('id'))->delete();
            }
            if (! $wasCompleted && $this->progress->for($tvShow, $user)['state'] === 'Completed') {
                UserTitle::change($user, $tvShow, function (UserTitle $row): void {
                    $row->watchlisted_at = null;
                });
            }
        });

        return ['progress' => $this->progress->for($tvShow, $user), 'undoToken' => $token, 'watchlisted' => $tvShow->isWatchlistedBy($user)];
    }

    /** @param Builder<Episode> $eligible */
    private function markUnwatched(User $user, Builder $eligible): void
    {
        $watchedAt = now()->format('Y-m-d H:i:s.u');
        $eligible->whereNotIn('id', UserEpisode::query()->where('user_id', $user->id)->select('episode_id'))
            ->orderBy('id')->pluck('id')
            ->chunk(self::INSERT_CHUNK)
            ->each(fn (Collection $ids) => UserEpisode::query()->insert($ids->map(fn (int $id): array => ['user_id' => $user->id, 'episode_id' => $id, 'watched_at' => $watchedAt])->all()));
    }
}
