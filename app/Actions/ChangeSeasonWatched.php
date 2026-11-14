<?php

namespace App\Actions;

use App\Http\Exceptions\TitleRemoved;
use App\Http\Support\TitleActions\BulkWatchedChange;
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
        $this->validate($tvShow, $season, $user);

        if ($user === null) {
            return null;
        }

        $eligible = $season->episodes()->getQuery()->whereNull('removed_at')->whereDate('air_date', '<=', today());

        return $this->change->apply($request, $user, $tvShow, $watched, $eligible, clearsAll: false, seasonId: $season->id);
    }

    private function validate(TvShow $tvShow, Season $season, ?User $user): void
    {
        throw_if($tvShow->isRemoved(), TitleRemoved::class, $tvShow, $user);
        abort_if($season->removed_at !== null || $season->title_id !== $tvShow->id, 404);
    }
}
