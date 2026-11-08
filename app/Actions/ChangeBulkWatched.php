<?php

namespace App\Actions;

use App\Http\Exceptions\DetailsUnavailable;
use App\Http\Support\LatestTvShowAction;
use App\Http\Support\TvShowByCatalogId;
use App\Models\Episode;
use App\Models\Season;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserEpisode;
use App\Models\UserTitle;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\Enums\CatalogStatus;
use App\Services\TvShows\WatchedProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Marks or clears a whole Season, or a whole TV Show, as one action with one Undo. A mark touches only aired,
 * non-Removed Episodes the user has not watched yet, so earlier Watched dates stay; becoming Completed takes the
 * TV Show off the Watchlist, and nothing puts it back.
 *
 * @phpstan-import-type Progress from WatchedProgress
 */
class ChangeBulkWatched
{
    private const INSERT_CHUNK = 500;

    public function __construct(
        private WatchedProgress $progress,
        private LatestTvShowAction $latest,
        private TvShowByCatalogId $tvShows,
        private Catalog $catalog,
    ) {}

    /** Checks that a Season action can be taken on this Season, without changing anything. */
    public function validateSeason(TvShow $tvShow, Season $season): void
    {
        abort_unless(config('catalog.tv_shows.enabled'), 404);
        abort_if($tvShow->isRemoved(), 410);
        abort_if($season->removed_at !== null || $season->title_id !== $tvShow->id, 404);
    }

    /** @return array{progress: Progress, undoToken: string, watchlisted: bool} */
    public function season(Request $request, User $user, TvShow $tvShow, Season $season, bool $watched): array
    {
        $this->validateSeason($tvShow, $season);
        $airedEpisodes = $this->aired($season->episodes()->getQuery());

        return $this->run($request, $user, $tvShow, $watched, $airedEpisodes, clearsAll: false);
    }

    /** Checks that a whole-TV Show mark can be taken on this TV Show, without changing anything. */
    public function validateTvShowMark(string $tmdbId): void
    {
        abort_if($this->tvShows->find($tmdbId)?->isRemoved(), 410);
    }

    /** @return array{progress: ?Progress, undoToken: string, watchlisted: bool} */
    public function tvShow(Request $request, User $user, string $tmdbId, bool $watched): array
    {
        $catalogId = $this->tvShows->catalogId($tmdbId);
        $tvShow = $watched ? $this->tvShows->findOrCreate($request, $tmdbId) : $this->tvShows->find($tmdbId);
        abort_if($watched && $tvShow?->isRemoved(), 410);
        if ($tvShow === null) {
            $token = $this->latest->change($request, $user, null, $catalogId, fn () => null);

            return ['progress' => null, 'undoToken' => $token, 'watchlisted' => false];
        }
        if ($watched) {
            $this->ensureEpisodesStored($tvShow);
        }
        $airedEpisodes = $this->aired($tvShow->episodes()->getQuery())
            ->whereHas('season', fn (Builder $season): Builder => $season->where('season_number', '>', 0)->whereNull('removed_at'));

        return $this->run($request, $user, $tvShow, $watched, $airedEpisodes, clearsAll: true);
    }

    /** A TV Show with no Episodes stored (cap-cleared, or never fetched) is refreshed first; if that fails nothing changes. */
    private function ensureEpisodesStored(TvShow $tvShow): void
    {
        if ($tvShow->episodes()->exists()) {
            return;
        }
        $result = $this->catalog->tvShowDetails($tvShow);
        abort_if($result->isRemoved(), 410);
        throw_if($result->isUnavailable() || ! $tvShow->episodes()->exists() && $result->status === CatalogStatus::Stale, DetailsUnavailable::class);
    }

    /**
     * @param  HasMany<Episode, Season|TvShow>|Builder<Episode>  $query
     * @return Builder<Episode>
     */
    private function aired(Builder $query): Builder
    {
        return $query->whereNull('removed_at')->whereDate('air_date', '<=', today());
    }

    /**
     * @param  Builder<Episode>  $eligible
     * @return array{progress: ?Progress, undoToken: string, watchlisted: bool}
     */
    private function run(Request $request, User $user, TvShow $tvShow, bool $watched, Builder $eligible, bool $clearsAll): array
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
