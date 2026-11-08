<?php

namespace App\Actions;

use App\Enums\AnalyticsEvent;
use App\Enums\ProgressState;
use App\Enums\UserTitleAction;
use App\Http\Exceptions\TitleRemoved;
use App\Http\Support\LatestTvShowAction;
use App\Models\Episode;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserEpisode;
use App\Models\UserTitle;
use App\Services\TvShows\WatchedProgress;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** @phpstan-import-type Progress from WatchedProgress */
class ChangeEpisodeWatched
{
    public function __construct(private WatchedProgress $progress, private LatestTvShowAction $latest, private RecordEvent $recordEvent) {}

    /** @return ($user is null ? null : array{progress: Progress, undoToken: string, watchlisted: bool}) */
    public function handle(Request $request, ?User $user, TvShow $tvShow, Episode $episode, bool $watched): ?array
    {
        $this->validateEpisode($tvShow, $episode, $user);

        if ($user === null) {
            return null;
        }

        $token = $this->latest->change($request, $user, $tvShow, $tvShow->tmdb_id, function () use ($user, $tvShow, $episode, $watched): void {
            $wasOnWatchlist = $tvShow->isWatchlistedBy($user);
            $wasCompleted = $this->progress->for($tvShow, $user)['state'] === ProgressState::Completed;
            if ($watched) {
                UserEpisode::query()->updateOrCreate(['user_id' => $user->id, 'episode_id' => $episode->id], ['watched_at' => now()]);
            } else {
                UserEpisode::query()->where('user_id', $user->id)->where('episode_id', $episode->id)->delete();
            }
            $this->recordEvent->handle($user, $watched ? AnalyticsEvent::EpisodeMarkedWatched : AnalyticsEvent::EpisodeUnmarkedWatched, [
                'tv_show_id' => $tvShow->id, 'season_id' => $episode->season_id, 'episode_id' => $episode->id,
                'source' => 'tv-show', 'was_on_watchlist' => $wasOnWatchlist,
            ]);
            $progress = $this->progress->for($tvShow, $user);
            if (! $wasCompleted && $progress['state'] === ProgressState::Completed) {
                UserTitle::change($user, $tvShow, function (UserTitle $row): void {
                    $row->watchlisted_at = null;
                });
                $this->recordEvent->handle($user, AnalyticsEvent::TvShowCompleted, ['tv_show_id' => $tvShow->id]);
            }
        }, $watched ? UserTitleAction::MarkAsWatched : UserTitleAction::ClearWatched, ['episode_id' => $episode->id]);

        return ['progress' => $this->progress->for($tvShow, $user), 'undoToken' => $token, 'watchlisted' => $tvShow->isWatchlistedBy($user)];
    }

    private function validateEpisode(TvShow $tvShow, Episode $episode, ?User $user): void
    {
        abort_unless(config('catalog.tv_shows.enabled'), 404);
        throw_if($tvShow->isRemoved(), TitleRemoved::class, $tvShow, $user);
        abort_if($episode->removed_at !== null || $episode->season->removed_at !== null || $episode->title_id !== $tvShow->id, 404);
        if ($episode->air_date === null || $episode->air_date->greaterThan(today())) {
            throw ValidationException::withMessages(['episode' => 'This Episode has not aired yet.']);
        }
    }
}
