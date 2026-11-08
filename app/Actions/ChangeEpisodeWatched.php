<?php

namespace App\Actions;

use App\Models\Episode;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserEpisode;
use App\Models\UserTitle;
use App\Services\TvShows\WatchedProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** @phpstan-import-type Progress from WatchedProgress */
class ChangeEpisodeWatched
{
    public function __construct(private WatchedProgress $progress) {}

    public function validateEpisode(TvShow $tvShow, Episode $episode): void
    {
        abort_unless(config('catalog.tv_shows.enabled'), 404);
        abort_if($tvShow->isRemoved() || $episode->removed_at !== null || $episode->season->removed_at !== null || $episode->title_id !== $tvShow->id, 404);
        if ($episode->air_date === null || $episode->air_date->greaterThan(today())) {
            throw ValidationException::withMessages(['episode' => 'This Episode has not aired yet.']);
        }
    }

    /** @return array{progress: Progress, undoToken: string} */
    public function handle(Request $request, User $user, TvShow $tvShow, Episode $episode, bool $watched): array
    {
        return DB::transaction(function () use ($request, $user, $tvShow, $episode, $watched): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->validateEpisode($tvShow, $episode);
            $before = $this->snapshot($user, $tvShow);
            $wasCompleted = $this->progress->for($tvShow, $user)['state'] === 'Completed';
            if ($watched) {
                UserEpisode::query()->updateOrCreate(['user_id' => $user->id, 'episode_id' => $episode->id], ['watched_at' => now()]);
            } else {
                UserEpisode::query()->where('user_id', $user->id)->where('episode_id', $episode->id)->delete();
            }
            $progress = $this->progress->for($tvShow, $user);
            if (! $wasCompleted && $progress['state'] === 'Completed') {
                UserTitle::change($user, $tvShow, function (UserTitle $row): void {
                    $row->watchlisted_at = null;
                });
            }
            $token = (string) Str::uuid();
            $request->session()->put('tv_actions.latest', [
                'user_id' => $user->id, 'tmdb_id' => $tvShow->tmdb_id, 'token' => $token, 'stored_at' => now()->timestamp,
                'before' => $before, 'after' => $this->snapshot($user, $tvShow),
            ]);

            return ['progress' => $progress, 'undoToken' => $token];
        });
    }

    /** @return array{episodes: array<int, string>, watchlisted_at: ?string, watched_at: ?string} */
    private function snapshot(User $user, TvShow $tvShow): array
    {
        $facts = UserTitle::query()->where('user_id', $user->id)->where('title_id', $tvShow->id)->first();

        return [
            'episodes' => UserEpisode::query()->where('user_id', $user->id)->whereIn('episode_id', $tvShow->episodes()->select('id'))->orderBy('episode_id')
                ->get()->mapWithKeys(fn (UserEpisode $mark): array => [$mark->episode_id => $mark->watched_at->toJSON()])->all(),
            'watchlisted_at' => $facts?->watchlisted_at?->toJSON(), 'watched_at' => $facts?->watched_at?->toJSON(),
        ];
    }

    public function undo(Request $request, User $user, TvShow $tvShow, string $token): bool
    {
        return DB::transaction(function () use ($request, $user, $tvShow, $token): bool {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $latest = $request->session()->get('tv_actions.latest');
            if (! is_array($latest) || $latest['user_id'] !== $user->id || $latest['tmdb_id'] !== $tvShow->tmdb_id || $latest['token'] !== $token || now()->timestamp - $latest['stored_at'] > 1800) {
                return false;
            }
            $request->session()->forget('tv_actions.latest');
            if ($this->snapshot($user, $tvShow) !== $latest['after']) {
                return false;
            }
            UserEpisode::query()->where('user_id', $user->id)->whereIn('episode_id', $tvShow->episodes()->select('id'))->delete();
            foreach ($latest['before']['episodes'] as $episodeId => $date) {
                UserEpisode::query()->create(['user_id' => $user->id, 'episode_id' => $episodeId, 'watched_at' => $date]);
            }
            UserTitle::change($user, $tvShow, function (UserTitle $row) use ($latest): void {
                $row->watchlisted_at = $latest['before']['watchlisted_at'];
                $row->watched_at = $latest['before']['watched_at'];
            });

            return true;
        });
    }
}
