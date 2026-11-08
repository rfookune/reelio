<?php

namespace App\Http\Support;

use App\Http\Exceptions\DetailsUnavailable;
use App\Http\Exceptions\TitleRemoved;
use App\Models\Movie;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\Exceptions\CreationLimited;
use Illuminate\Http\Request;

/**
 * Resolves the catalog id in a request's path to a Movie row, for the entry route and the catalog-id actions.
 * Creation is limited per user, or per IP for guests, under one limit shared by every route that creates rows.
 */
class MovieByCatalogId
{
    public function __construct(private Catalog $catalog) {}

    /**
     * The stored Movie, or one created once the Catalog source confirms the id. An id the source doesn't know is
     * a 404, an exhausted creation limit a 429 with `Retry-After`, and a source outage with no row a 503.
     */
    public function findOrCreate(Request $request, string $tmdbId): Movie
    {
        try {
            $result = $this->catalog->findOrCreate($this->catalogId($tmdbId), RateLimitKey::for($request));
        } catch (CreationLimited $exception) {
            abort(429, $exception->getMessage(), ['Retry-After' => $exception->retryAfter, 'X-Robots-Tag' => 'noindex']);
        }

        throw_if($result->isUnavailable(), DetailsUnavailable::class);
        abort_if($result->value === null, 404);

        return $result->value;
    }

    /**
     * The Movie a user action is setting a fact on, found or created as `findOrCreate()` does. A Removed Movie is a
     * 410: facts about it can only be cleared, never set.
     */
    public function findOrCreateUnremoved(Request $request, string $tmdbId): Movie
    {
        $movie = $this->findOrCreate($request, $tmdbId);
        throw_if($movie->isRemoved(), TitleRemoved::class);

        return $movie;
    }

    /** The stored Movie, if any, without asking the Catalog source or creating a row. */
    public function find(string $tmdbId): ?Movie
    {
        return $this->catalog->findByCatalogId($this->catalogId($tmdbId));
    }

    /** A positive id that fits an integer; anything else, such as an oversized id, is a 404. */
    public function catalogId(string $tmdbId): int
    {
        $catalogId = filter_var($tmdbId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($catalogId === false, 404);

        return $catalogId;
    }
}
