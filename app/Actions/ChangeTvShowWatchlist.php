<?php

namespace App\Actions;

use App\Http\Exceptions\TitleRemoved;
use App\Http\Support\LatestTvShowAction;
use App\Http\Support\TvShowByCatalogId;
use App\Models\User;
use App\Models\UserTitle;
use App\Services\TvShows\WatchedProgress;
use Illuminate\Http\Request;

/** @phpstan-import-type Progress from WatchedProgress */
class ChangeTvShowWatchlist
{
    public function __construct(private TvShowByCatalogId $tvShows, private LatestTvShowAction $latest, private WatchedProgress $progress) {}

    /** @return array{watchlisted: bool, progress: ?Progress, undoToken: string} */
    public function handle(Request $request, User $user, string $tmdbId, bool $watchlisted): array
    {
        $catalogId = $this->tvShows->catalogId($tmdbId);
        $tvShow = $watchlisted ? $this->tvShows->findOrCreate($request, $tmdbId) : $this->tvShows->find($tmdbId);
        throw_if($watchlisted && $tvShow?->isRemoved(), TitleRemoved::class, $tvShow, $user);
        $token = $this->latest->change($request, $user, $tvShow, $catalogId, function () use ($user, $tvShow, $watchlisted): void {
            if ($tvShow !== null) {
                UserTitle::change($user, $tvShow, function (UserTitle $row) use ($watchlisted): void {
                    $row->watchlisted_at = $watchlisted ? ($row->watchlisted_at ?? now()) : null;
                });
            }
        });

        return ['watchlisted' => $watchlisted, 'progress' => $tvShow === null ? null : $this->progress->for($tvShow, $user), 'undoToken' => $token];
    }
}
