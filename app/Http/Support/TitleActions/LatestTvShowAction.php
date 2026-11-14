<?php

namespace App\Http\Support\TitleActions;

use App\Actions\RecordEvent;
use App\Enums\AnalyticsEvent;
use App\Enums\UserTitleAction;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserEpisode;
use App\Models\UserTitle;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One latest TV Show action per session, shared by Episode and Watchlist controls. Server-owned snapshots
 * restore exact dates only while every fact still matches what the action left behind.
 *
 * @phpstan-type Snapshot array{episodes: array<int, string>, watchlisted_at: ?string, watched_at: ?string}
 */
class LatestTvShowAction
{
    private const SESSION_KEY = 'tv_actions.latest';

    public function __construct(private TvShowByCatalogId $tvShows, private RecordEvent $recordEvent) {}

    /**
     * @param  Closure(): void  $apply
     * @param  array{season_id?: int, episode_id?: int}  $scope
     */
    public function change(Request $request, User $user, ?TvShow $tvShow, int $tmdbId, Closure $apply, UserTitleAction $action, array $scope = []): string
    {
        return DB::transaction(function () use ($request, $user, $tvShow, $tmdbId, $apply, $action, $scope): string {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($user, $tvShow);
            $apply();
            $token = (string) Str::uuid();
            $request->session()->put(self::SESSION_KEY, [
                'user_id' => $user->id, 'tmdb_id' => $tmdbId, 'token' => $token, 'stored_at' => now()->timestamp,
                'action' => ['action' => $action->value, ...$scope], 'before' => $before, 'after' => $this->snapshot($user, $tvShow),
            ]);

            return $token;
        });
    }

    /** @return Snapshot */
    private function snapshot(User $user, ?TvShow $tvShow): array
    {
        if ($tvShow === null) {
            return ['episodes' => [], 'watchlisted_at' => null, 'watched_at' => null];
        }
        $facts = UserTitle::query()->where('user_id', $user->id)->where('title_id', $tvShow->id)->first();

        return [
            'episodes' => UserEpisode::query()->where('user_id', $user->id)->whereIn('episode_id', $tvShow->episodes()->select('id'))->orderBy('episode_id')
                ->get()->mapWithKeys(fn (UserEpisode $mark): array => [$mark->episode_id => $mark->watched_at->toJSON()])->all(),
            'watchlisted_at' => $facts?->watchlisted_at?->toJSON(), 'watched_at' => $facts?->watched_at?->toJSON(),
        ];
    }

    public function undo(Request $request, User $user, int $tmdbId, string $token): bool
    {
        return DB::transaction(function () use ($request, $user, $tmdbId, $token): bool {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $latest = $request->session()->get(self::SESSION_KEY);
            if (! is_array($latest) || $latest['user_id'] !== $user->id || $latest['tmdb_id'] !== $tmdbId || $latest['token'] !== $token || now()->timestamp - $latest['stored_at'] > 1800) {
                return false;
            }
            $tvShow = $this->tvShows->find((string) $tmdbId);
            if ($this->snapshot($user, $tvShow) !== $latest['after']) {
                $request->session()->forget(self::SESSION_KEY);

                return false;
            }
            if ($tvShow === null && $latest['before']['watchlisted_at'] !== null) {
                $tvShow = $this->tvShows->findOrCreate($request, (string) $tmdbId);
            }
            $request->session()->forget(self::SESSION_KEY);
            if ($tvShow === null) {
                return $latest['before']['episodes'] === [];
            }
            $addedEpisodeIds = array_diff(array_keys($latest['after']['episodes']), array_keys($latest['before']['episodes']));
            UserEpisode::query()->where('user_id', $user->id)->whereIn('episode_id', $addedEpisodeIds)->delete();
            foreach ($latest['before']['episodes'] as $episodeId => $date) {
                if (($latest['after']['episodes'][$episodeId] ?? null) !== $date) {
                    UserEpisode::query()->updateOrCreate(['user_id' => $user->id, 'episode_id' => $episodeId], ['watched_at' => $date]);
                }
            }
            UserTitle::change($user, $tvShow, function (UserTitle $row) use ($latest): void {
                $row->watchlisted_at = $latest['before']['watchlisted_at'];
                $row->watched_at = $latest['before']['watched_at'];
            });

            $this->recordEvent->handle($user, AnalyticsEvent::TvShowActionUndone, ['tv_show_id' => $tvShow->id, ...$latest['action']]);

            return true;
        });
    }
}
