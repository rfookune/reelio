<?php

namespace App\Actions;

use App\Enums\Surface;
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
    public function __construct(private TvShowByCatalogId $tvShows, private LatestTvShowAction $latest, private WatchedProgress $progress, private RecordEvent $recordEvent) {}

    /** @return array{watchlisted: bool, progress: ?Progress, undoToken: string} */
    public function handle(Request $request, User $user, string $tmdbId, bool $watchlisted, Surface $source): array
    {
        $catalogId = $this->tvShows->catalogId($tmdbId);
        $tvShow = $watchlisted ? $this->tvShows->findOrCreate($request, $tmdbId) : $this->tvShows->find($tmdbId);
        throw_if($watchlisted && $tvShow?->isRemoved(), TitleRemoved::class, $tvShow, $user);
        $token = $this->latest->change($request, $user, $tvShow, $catalogId, function () use ($user, $tvShow, $watchlisted, $source): void {
            if ($tvShow !== null) {
                $facts = UserTitle::change($user, $tvShow, function (UserTitle $row) use ($watchlisted): void {
                    $row->watchlisted_at = $watchlisted ? ($row->watchlisted_at ?? now()) : null;
                });
                if ($facts['before'] !== $facts['after']) {
                    $this->recordEvent->handle($user, $watchlisted ? 'tv_show_watchlisted' : 'tv_show_unwatchlisted', ['tv_show_id' => $tvShow->id, 'source' => $source->value]);
                }
            }
        }, ['action' => $watchlisted ? 'add_to_watchlist' : 'remove_from_watchlist']);

        return ['watchlisted' => $watchlisted, 'progress' => $tvShow === null ? null : $this->progress->for($tvShow, $user), 'undoToken' => $token];
    }
}
