<?php

namespace App\Http\Support\Search;

use App\Enums\ProgressState;
use App\Enums\TitleKind;
use App\Models\Movie;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserTitle;
use App\Services\Catalog\Sources\Listings\MovieListing;
use App\Services\TvShows\ProgressSummaries;

/**
 * Which listed Titles a user has already seen, so Browse can leave them out. A Movie is seen once it has a Watched
 * record, a TV Show once it is Completed, and either once the user has given it a User rating. Titles on the
 * Watchlist, and TV Shows In progress or Not started, are not seen, and neither is a Title Reelio holds no row
 * for.
 */
class SeenTitles
{
    public function __construct(private ProgressSummaries $progress) {}

    /**
     * The listings the user hasn't seen, in their order.
     *
     * @param  list<MovieListing>  $listings
     * @return list<MovieListing>
     */
    public function unseen(User $user, array $listings): array
    {
        $movieIds = $this->titleIds(Movie::class, $listings, TitleKind::Movie);
        $tvShowIds = $this->titleIds(TvShow::class, $listings, TitleKind::TvShow);

        if ($movieIds === [] && $tvShowIds === []) {
            return $listings;
        }

        $facts = UserTitle::query()->whereBelongsTo($user)->whereIn('title_id', [...$movieIds, ...$tvShowIds])->get()->keyBy('title_id');
        $progress = $tvShowIds === [] ? [] : $this->progress->for($user, array_values($tvShowIds));

        return array_values(array_filter($listings, function (MovieListing $listing) use ($movieIds, $tvShowIds, $facts, $progress): bool {
            $titleId = ($listing->kind === TitleKind::TvShow ? $tvShowIds : $movieIds)[$listing->tmdbId] ?? null;

            if ($titleId === null) {
                return true;
            }

            $row = $facts->get($titleId);

            if ($row?->rating !== null) {
                return false;
            }

            return $listing->kind === TitleKind::TvShow
                ? ($progress[$titleId]['state'] ?? null) !== ProgressState::Completed
                : $row?->watched_at === null;
        }));
    }

    /**
     * The Title ids of the listings of a kind that Reelio has a row for, by catalog id.
     *
     * @param  class-string<Movie|TvShow>  $model
     * @param  list<MovieListing>  $listings
     * @return array<int, int>
     */
    private function titleIds(string $model, array $listings, TitleKind $kind): array
    {
        $tmdbIds = array_values(array_unique(array_map(fn (MovieListing $listing): int => $listing->tmdbId, array_filter($listings, fn (MovieListing $listing): bool => $listing->kind === $kind))));

        return $tmdbIds === [] ? [] : $model::query()->whereIn('tmdb_id', $tmdbIds)->pluck('id', 'tmdb_id')->all();
    }
}
