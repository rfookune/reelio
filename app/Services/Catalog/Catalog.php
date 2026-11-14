<?php

namespace App\Services\Catalog;

use App\Enums\TitleKind;
use App\Models\Movie;
use App\Models\Title;
use App\Models\TvShow;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Titles\MovieCatalog;
use App\Services\Catalog\Titles\TvShowCatalog;
use App\Services\Catalog\Titles\TvShowPageData;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Catalog
{
    public function __construct(private MovieCatalog $movies, private TvShowCatalog $tvShows) {}

    /** The stored Title as its Movie or TV Show subtype, with request ids validated before querying. */
    public function findTitle(int|string $id): Movie|TvShow
    {
        $titleId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($titleId === false) {
            throw (new ModelNotFoundException)->setModel(Title::class, [$id]);
        }
        $title = Title::query()->findOrFail($titleId);
        if ($title->kind === TitleKind::TvShow) {
            return (new TvShow)->newFromBuilder($title->getAttributes(), $title->getConnectionName());
        }

        return (new Movie)->newFromBuilder($title->getAttributes(), $title->getConnectionName())->load('details');
    }

    /** @return CatalogResult<Movie> */
    public function findOrCreate(int $tmdbId, string $actor): CatalogResult
    {
        return $this->movies->findOrCreate($tmdbId, $actor);
    }

    public function findByCatalogId(int $tmdbId): ?Movie
    {
        return $this->movies->findByCatalogId($tmdbId);
    }

    /** @return CatalogResult<MovieDetails> */
    public function details(Movie $movie): CatalogResult
    {
        return $this->movies->details($movie);
    }

    /** @return CatalogResult<MovieDetails> */
    public function refresh(Movie $movie, CatalogContext $context): CatalogResult
    {
        return $this->movies->refresh($movie, $context);
    }

    public function isDueOnView(Movie $movie): bool
    {
        return $this->movies->isDueOnView($movie);
    }

    public function recordView(Movie $movie): void
    {
        $this->movies->recordView($movie);
    }

    /** @return CatalogResult<TvShow> */
    public function findOrCreateTvShow(int $tmdbId, string $actor): CatalogResult
    {
        return $this->tvShows->findOrCreateTvShow($tmdbId, $actor);
    }

    /** @return CatalogResult<TvShow> */
    public function tvShowDetails(TvShow $tvShow): CatalogResult
    {
        return $this->tvShows->tvShowDetails($tvShow);
    }

    /** @return CatalogResult<TvShow> */
    public function refreshTvShow(TvShow $tvShow, CatalogContext $context): CatalogResult
    {
        return $this->tvShows->refresh($tvShow, $context);
    }

    public function tvShowPageData(TvShow $tvShow): ?TvShowPageData
    {
        return $this->tvShows->tvShowPageData($tvShow);
    }
}
