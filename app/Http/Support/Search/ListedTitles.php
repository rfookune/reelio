<?php

namespace App\Http\Support\Search;

use App\Enums\TitleKind;
use App\Models\Movie;
use App\Models\TvShow;
use App\Services\Catalog\Sources\Listings\MovieListing;
use Illuminate\Support\Collection;

/**
 * The Titles a page lists and the rows Reelio already has for them, by catalog id, each loaded once. The cards and the
 * lookup of IMDb ratings both need them, so they share one.
 */
class ListedTitles
{
    /** @var Collection<int, Movie>|null keyed by catalog id */
    private ?Collection $movieRows = null;

    /** @var Collection<int, TvShow>|null keyed by catalog id */
    private ?Collection $tvShowRows = null;

    public function __construct(private ResultsPage $resultsPage) {}

    /**
     * The listings in the source's order.
     *
     * @return list<MovieListing>
     */
    public function listings(): array
    {
        return $this->resultsPage->listings();
    }

    /** The catalog id for a Movie, and `tv:` and it for a TV Show, as the page keys its cards and the lookup its ratings. */
    public static function key(TitleKind $kind, int $tmdbId): string
    {
        return $kind === TitleKind::TvShow ? "tv:{$tmdbId}" : (string) $tmdbId;
    }

    /** @return list<int> */
    public function movieCatalogIds(): array
    {
        return $this->catalogIdsOf(TitleKind::Movie);
    }

    /** @return list<int> */
    public function tvShowCatalogIds(): array
    {
        return $this->catalogIdsOf(TitleKind::TvShow);
    }

    /** @return Collection<int, Movie> keyed by catalog id */
    public function movieRows(): Collection
    {
        return $this->movieRows ??= Movie::query()->whereIn('tmdb_id', $this->movieCatalogIds())->get()->keyBy('tmdb_id');
    }

    /** @return Collection<int, TvShow> keyed by catalog id */
    public function tvShowRows(): Collection
    {
        return $this->tvShowRows ??= TvShow::query()->with('details')->whereIn('tmdb_id', $this->tvShowCatalogIds())->get()->keyBy('tmdb_id');
    }

    /** @return Collection<int, Movie>|Collection<int, TvShow> keyed by catalog id */
    public function rowsOf(TitleKind $kind): Collection
    {
        return $kind === TitleKind::TvShow ? $this->tvShowRows() : $this->movieRows();
    }

    /** @return list<int> */
    private function catalogIdsOf(TitleKind $kind): array
    {
        return array_values(array_unique(array_map(fn (MovieListing $listing): int => $listing->tmdbId, array_filter($this->listings(), fn (MovieListing $listing): bool => $listing->kind === $kind))));
    }
}
