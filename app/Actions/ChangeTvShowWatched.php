<?php

namespace App\Actions;

use App\Enums\Surface;
use App\Enums\UserTitleAction;
use App\Http\Exceptions\DetailsUnavailable;
use App\Http\Exceptions\TitleRemoved;
use App\Http\Support\TitleActions\BulkWatchedChange;
use App\Http\Support\TitleActions\LatestTvShowAction;
use App\Http\Support\TitleActions\TvShowByCatalogId;
use App\Models\TvShow;
use App\Models\User;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\Enums\CatalogStatus;
use App\Services\TvShows\WatchedProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Marks or clears a whole TV Show. A mark covers the aired Episodes of the numbered Seasons; a clear removes every
 * mark, Specials included.
 *
 * @phpstan-import-type Progress from WatchedProgress
 */
class ChangeTvShowWatched
{
    public function __construct(
        private BulkWatchedChange $change,
        private LatestTvShowAction $latest,
        private TvShowByCatalogId $tvShows,
        private Catalog $catalog,
    ) {}

    /**
     * A guest (no user) is only validated, so their action can be stored as pending.
     *
     * @return ($user is null ? null : array{progress: ?Progress, undoToken: string, watchlisted: bool})
     */
    public function handle(Request $request, ?User $user, string $tmdbId, bool $watched, Surface $source): ?array
    {
        $catalogId = $this->tvShows->catalogId($tmdbId);

        if ($user === null) {
            $stored = $this->tvShows->find($tmdbId);
            throw_if($watched && $stored?->isRemoved(), TitleRemoved::class, $stored, $user);

            return null;
        }

        $tvShow = $watched ? $this->tvShows->findOrCreate($request, $tmdbId) : $this->tvShows->find($tmdbId);
        throw_if($watched && $tvShow?->isRemoved(), TitleRemoved::class, $tvShow, $user);
        if ($tvShow === null) {
            return ['progress' => null, 'undoToken' => $this->latest->change($request, $user, null, $catalogId, fn () => null, UserTitleAction::ClearWatched), 'watchlisted' => false];
        }
        if ($watched) {
            $this->ensureEpisodesStored($tvShow, $user);
        }
        $eligible = $tvShow->episodes()->getQuery()->whereNull('removed_at')->whereDate('air_date', '<=', today())
            ->whereHas('season', fn (Builder $season): Builder => $season->where('season_number', '>', 0)->whereNull('removed_at'));

        return $this->change->apply($request, $user, $tvShow, $watched, $eligible, clearsAll: true, source: $source);
    }

    /** A cap-cleared TV Show or one with no Episodes stored is refreshed first; if that fails nothing changes. */
    private function ensureEpisodesStored(TvShow $tvShow, User $user): void
    {
        if ($tvShow->hasSourceDetails() && $tvShow->episodes()->exists()) {
            return;
        }
        $result = $this->catalog->tvShowDetails($tvShow);
        throw_if($result->isRemoved(), TitleRemoved::class, $tvShow, $user);
        throw_if($result->isUnavailable() || ($result->status === CatalogStatus::Stale && $tvShow->episodes()->doesntExist()), DetailsUnavailable::class, $tvShow, $user);
    }
}
