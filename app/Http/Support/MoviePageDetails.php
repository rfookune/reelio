<?php

namespace App\Http\Support;

use App\Http\Exceptions\MovieRemoved;
use App\Models\Movie;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\CatalogResult;
use App\Services\Catalog\Sources\MovieDetails;

/**
 * The Catalog details for one Movie page response, fetched the first time a prop needs them and at most once, so
 * a partial reload that needs none of them, such as reloading `userMovies` after a Watchlist action, never
 * refreshes the Movie. A refresh that finds the Movie removed makes the page a 410.
 */
class MoviePageDetails
{
    /** @var CatalogResult<MovieDetails>|null */
    private ?CatalogResult $result = null;

    public function __construct(private Catalog $catalog, private Movie $movie) {}

    /** @return CatalogResult<MovieDetails> */
    public function result(): CatalogResult
    {
        $this->result ??= $this->catalog->details($this->movie);
        throw_if($this->result->isRemoved(), MovieRemoved::class);

        return $this->result;
    }

    /** Whether the Movie is still due a refresh after this page's attempt, which means its refresh failed. */
    public function refreshFailed(): bool
    {
        $this->result();

        return $this->catalog->isDueOnView($this->movie);
    }
}
