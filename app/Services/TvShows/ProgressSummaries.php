<?php

namespace App\Services\TvShows;

use App\Enums\ProgressState;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A user's Watched progress for many TV Shows at once, in one query, for the lists. It counts as `WatchedProgress`
 * does for a TV Show's page: only the aired Episodes of the numbered Seasons count towards `total` and `watched`,
 * Removed Episodes and Seasons and Specials never do. `marked` counts every Episode watched, Specials, unaired and
 * Removed ones included, since the Watched page lists a TV Show as soon as one is.
 *
 * @phpstan-type Summary array{state: ProgressState, total: int, watched: int, marked: int, latestWatchedAt: ?string}
 */
class ProgressSummaries
{
    /**
     * @param  list<int>  $titleIds
     * @return array<int, Summary> keyed by Title id; a TV Show with no Episodes stored has none
     */
    public function for(User $user, array $titleIds): array
    {
        if ($titleIds === []) {
            return [];
        }

        $counts = 'e.removed_at is null and s.removed_at is null and s.season_number > 0 and e.air_date <= ?';
        $rows = DB::table('episodes as e')
            ->join('seasons as s', 's.id', '=', 'e.season_id')
            ->leftJoin('user_episodes as ue', fn ($join) => $join->on('ue.episode_id', '=', 'e.id')->where('ue.user_id', $user->id))
            ->whereIn('e.title_id', $titleIds)
            ->groupBy('e.title_id')
            ->selectRaw("e.title_id, count(*) filter (where {$counts}) as total, count(ue.episode_id) filter (where {$counts}) as watched, count(ue.episode_id) as marked, max(ue.watched_at) as latest_watched_at", [today()->toDateString(), today()->toDateString()])
            ->get();
        $summaries = [];

        foreach ($rows as $row) {
            $watched = (int) $row->watched;
            $total = (int) $row->total;
            $summaries[(int) $row->title_id] = [
                'state' => $watched === 0 ? ProgressState::NotStarted : ($watched === $total ? ProgressState::Completed : ProgressState::InProgress),
                'total' => $total,
                'watched' => $watched,
                'marked' => (int) $row->marked,
                'latestWatchedAt' => $row->latest_watched_at === null ? null : CarbonImmutable::parse($row->latest_watched_at)->toJSON(),
            ];
        }

        return $summaries;
    }
}
