<?php

namespace App\Http\Support;

use App\Http\Exceptions\DetailsUnavailable;
use App\Http\Exceptions\MovieRemoved;
use App\Models\Movie;
use App\Models\User;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\CatalogResult;
use App\Services\Catalog\Sources\Details\MovieDetails;

/**
 * The Catalog details for one Movie page response, fetched the first time a prop needs them and at most once, so
 * a partial reload that needs none of them, such as reloading `userMovies` after a Watchlist action, never
 * refreshes the Movie. A refresh that finds the Movie removed makes the page a 410; a Movie still without details
 * after it, as one cleared at TMDB's cap whose refresh failed, makes the page a 503.
 */
class MoviePageDetails
{
    /** @var CatalogResult<MovieDetails>|null */
    private ?CatalogResult $result = null;

    public function __construct(private Catalog $catalog, private Movie $movie, private ?User $user = null) {}

    /** @return CatalogResult<MovieDetails> */
    public function result(): CatalogResult
    {
        $this->result ??= $this->catalog->details($this->movie);
        throw_if($this->result->isRemoved(), MovieRemoved::class, $this->movie, $this->user);
        throw_unless($this->movie->hasSourceDetails(), DetailsUnavailable::class, $this->movie, $this->user);

        return $this->result;
    }

    /**
     * Refreshes a Movie without details now, as one cleared at TMDB's cap, since only that restores it and its slug,
     * so a page checks the slug after it. Throws as `result()` does when the Movie is still without them.
     */
    public function restoreIfCleared(): void
    {
        if (! $this->movie->hasSourceDetails()) {
            $this->result();
        }
    }

    /** Whether the Movie is still due a refresh after this page's attempt, which means its refresh failed. */
    public function refreshFailed(): bool
    {
        $this->result();

        return $this->catalog->isDueOnView($this->movie);
    }
}
