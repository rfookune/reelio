<?php

namespace App\Services\TvShows;

use App\Enums\ProgressState;
use App\Models\Episode;
use App\Models\Season;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserEpisode;

/**
 * @phpstan-type EpisodeProgress array{id: int, number: ?int, name: ?string, overview: ?string, airDate: ?string, runtime: ?int, markable: bool, watchedDate: ?string}
 * @phpstan-type SeasonProgress array{id: int, number: ?int, name: ?string, total: int, watched: int, episodes: list<EpisodeProgress>}
 * @phpstan-type Progress array{state: ProgressState, total: int, watched: int, defaultOpenSeasonId: ?int, seasons: list<SeasonProgress>}
 */
class WatchedProgress
{
    /** @return Progress */
    public function for(TvShow $tvShow, ?User $user): array
    {
        $marks = [];
        if ($user !== null) {
            $records = UserEpisode::query()->where('user_id', $user->id)
                ->whereIn('episode_id', $tvShow->episodes()->select('id'))->get();
            foreach ($records as $record) {
                $marks[$record->episode_id] = $record->watched_at->toJSON();
            }
        }
        $seasons = $tvShow->seasons()->whereNull('removed_at')->with(['episodes' => fn ($query) => $query->whereNull('removed_at')->orderBy('episode_number')->orderBy('id')])
            ->ordered()->get()
            ->map(function (Season $season) use ($marks): array {
                $episodes = $season->episodes->map(fn (Episode $episode): array => [
                    'id' => $episode->id, 'number' => $episode->episode_number, 'name' => $episode->name,
                    'overview' => $episode->overview, 'airDate' => $episode->air_date?->toDateString(), 'runtime' => $episode->runtime,
                    'markable' => $episode->air_date !== null && $episode->air_date->lessThanOrEqualTo(today()),
                    'watchedDate' => $marks[$episode->id] ?? null,
                ]);
                $aired = $episodes->where('markable', true);

                return ['id' => $season->id, 'number' => $season->season_number, 'name' => $season->name,
                    'total' => $aired->count(), 'watched' => $aired->whereNotNull('watchedDate')->count(), 'episodes' => array_values($episodes->all())];
            });
        $numbered = $seasons->where('number', '>', 0);
        $total = (int) $numbered->sum('total');
        $watched = (int) $numbered->sum('watched');
        $state = $watched === 0 ? ProgressState::NotStarted : ($watched === $total ? ProgressState::Completed : ProgressState::InProgress);
        $open = $state === ProgressState::NotStarted ? $numbered->firstWhere('number', 1) : $numbered->first(fn (array $season): bool => $season['watched'] < $season['total']);
        $open ??= $numbered->where('total', '>', 0)->last();

        return ['state' => $state, 'total' => $total, 'watched' => $watched, 'defaultOpenSeasonId' => $open['id'] ?? null, 'seasons' => array_values($seasons->all())];
    }
}
