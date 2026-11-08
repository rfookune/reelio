<?php

namespace App\Actions;

use App\Http\Support\BulkWatchedChange;
use App\Models\Season;
use App\Models\TvShow;
use App\Models\User;
use App\Services\TvShows\WatchedProgress;
use Illuminate\Http\Request;

/** @phpstan-import-type Progress from WatchedProgress */
class ChangeSeasonWatched
{
    public function __construct(private BulkWatchedChange $change) {}

    /**
     * A guest (no user) is only validated, so their action can be stored as pending.
     *
     * @return ($user is null ? null : array{progress: Progress, undoToken: string, watchlisted: bool})
     */
    public function handle(Request $request, ?User $user, TvShow $tvShow, Season $season, bool $watched): ?array
    {
        $this->validate($tvShow, $season);

        if ($user === null) {
            return null;
        }

        $eligible = $season->episodes()->getQuery()->whereNull('removed_at')->whereDate('air_date', '<=', today());

        return $this->change->apply($request, $user, $tvShow, $watched, $eligible, clearsAll: false);
    }

    private function validate(TvShow $tvShow, Season $season): void
    {
        abort_unless(config('catalog.tv_shows.enabled'), 404);
        abort_if($tvShow->isRemoved(), 410);
        abort_if($season->removed_at !== null || $season->title_id !== $tvShow->id, 404);
    }
}
