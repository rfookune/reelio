<?php

namespace App\Services\Catalog\Maintenance;

use App\Enums\TitleKind;
use App\Models\Episode;
use App\Models\Title;
use App\Models\UserEpisode;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Titles\AvailabilityWriter;
use App\Services\Catalog\Titles\MovieCatalog;
use App\Services\Catalog\Titles\TvShowCatalog;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use App\Services\Ratings\TmdbRatingId;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CatalogCleanup
{
    public function __construct(private RatingStore $ratings, private AvailabilityWriter $availability, private MovieCatalog $movies, private TvShowCatalog $tvShows) {}

    /**
     * One run of the daily cleanup, each step taking at most `cleanup.batch_size` rows:
     *
     * - a Title whose last successful sync is more than `cap.after_days` old has everything from the Catalog source
     *   cleared, as a Removed Title does, but isn't marked removed: its next successful refresh restores it;
     * - TMDB ratings older than that are deleted, whether or not a Title exists for them;
     * - Titles nobody holds and nobody has viewed for `purge_unviewed.after_days` are deleted, while that purge is
     *   switched on;
     * - Streaming services no Availability references any more are deleted.
     *
     * Returns whether any step filled its batch, so another run should follow.
     */
    public function cleanUp(): bool
    {
        $batchSize = config('catalog.cleanup.batch_size');
        $capCutoff = now()->subDays(config('catalog.cap.after_days'));
        $batchCounts = [
            $this->clearPastCap($capCutoff, $batchSize),
            $this->ratings->forgetOlderThan(RatingSourceName::Tmdb, $capCutoff, $batchSize),
            $this->purgeUnviewed($batchSize),
            $this->availability->deleteUnreferencedServices($batchSize, now()->subSeconds(config('catalog.cleanup.unreferenced_service_grace_seconds'))),
        ];

        return in_array($batchSize, $batchCounts, true);
    }

    /**
     * Clears up to `$limit` Titles last synced before `$cutoff`, and returns how many it considered. Each is locked
     * and checked again first, so a Title a refresh has just restored is left alone.
     */
    private function clearPastCap(CarbonInterface $cutoff, int $limit): int
    {
        $pastCap = fn (Builder $query): Builder => $query->whereNull('removed_at')->where('synced_at', '<', $cutoff);
        $candidateIds = $this->titleIds($pastCap(Title::query())->orderBy('id')->limit($limit));
        $this->movies->clearEach($candidateIds, $pastCap);
        $this->tvShows->clearEach($candidateIds, $pastCap);

        return count($candidateIds);
    }

    /**
     * Deletes up to `$limit` Titles nobody holds and nobody has viewed for `purge_unviewed.after_days`, with their
     * genre links, Availability (both cascade), detail cache and TMDB rating, and returns how many it considered. The
     * candidates and their Episodes are locked, then checked again for facts, because saving either fact needs a foreign-key lock that conflicts with
     * ours: a fact saved before the lock is seen by the check, and none can be saved after it until the delete. Two
     * races inside the delete's transaction are accepted: a save that loaded the row just before fails once, and
     * trying again re-creates the row from its catalog id; a page view that loaded it renders, or is a 404 if it
     * needed a refresh, and its URL stays a 404 like any purged Title's.
     */
    private function purgeUnviewed(int $limit): int
    {
        if (! config('catalog.purge_unviewed.enabled')) {
            return 0;
        }

        $candidateIds = $this->titleIds($this->unviewedAndUnheld(Title::query())->orderBy('id')->limit($limit));

        if ($candidateIds === []) {
            return 0;
        }

        $purged = DB::transaction(function () use ($candidateIds): Collection {
            Title::query()->whereKey($candidateIds)->orderBy('id')->lockForUpdate()->get(['id']);
            Episode::query()->whereIn('title_id', $candidateIds)->orderBy('id')->lockForUpdate()->get(['id']);
            $titles = $this->unviewedAndUnheld(Title::query()->whereKey($candidateIds))->get(['id', 'tmdb_id', 'kind']);
            Title::query()->whereKey($titles->modelKeys())->delete();
            $this->ratings->forgetMany(RatingSourceName::Tmdb, array_values($titles->map(fn (Title $title): string => TmdbRatingId::for($title->kind, $title->tmdb_id))->all()));

            return $titles;
        });

        $context = new CatalogContext(config('catalog.language'));

        foreach ($purged as $title) {
            if ($title->kind === TitleKind::TvShow) {
                $this->tvShows->forgetCachedData($title->tmdb_id, $context);
            } else {
                $this->movies->forgetCachedData($title->tmdb_id, $context);
            }
        }

        return count($candidateIds);
    }

    /**
     * @param  Builder<Title>  $query
     * @return Builder<Title>
     */
    private function unviewedAndUnheld(Builder $query): Builder
    {
        return $query->where('last_viewed_at', '<', now()->subDays(config('catalog.purge_unviewed.after_days')))->whereDoesntHave('userTitles')->whereDoesntHave('episodes', fn (Builder $episodes): Builder => $episodes->whereIn('id', UserEpisode::query()->select('episode_id')));
    }

    /**
     * @param  Builder<Title>  $query
     * @return list<int>
     */
    private function titleIds(Builder $query): array
    {
        return array_values($query->get(['id'])->map(fn (Title $title): int => $title->id)->all());
    }
}
