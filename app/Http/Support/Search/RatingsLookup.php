<?php

namespace App\Http\Support\Search;

use App\Enums\TitleKind;
use App\Http\Support\Cards\RatingsByCatalogId;
use App\Services\Catalog\Search\ImdbIdLookup;
use App\Services\Catalog\Sources\Listings\MovieListing;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\ExternalRating;
use App\Services\Ratings\Ratings;

/**
 * The IMDb ratings of the Titles on a page that its cards couldn't show, because the Titles' IMDb ids weren't known:
 * a search result carries no IMDb id, and neither does a Browse card, so the page asks for these after it renders.
 */
class RatingsLookup
{
    public function __construct(
        private ListedTitles $titles,
        private ImdbIdLookup $imdbIds,
        private Ratings $ratingSources,
    ) {}

    /**
     * The ratings of the Movies and TV Shows on the page that have an IMDb rating once their IMDb id is known, from
     * a lookup if need be. Keyed by catalog id for a Movie and `tv:` and the catalog id for a TV Show. A Title whose
     * row has its IMDb id isn't listed, since its card already shows it. Only the page's own ids are looked up,
     * never ids a visitor supplies, and nothing is looked up while IMDb is switched off.
     *
     * @return array<int|string, list<array{source: string, name: string, value: float, voteCount: int}>>
     */
    public function lookedUp(): array
    {
        if (! RatingSourceName::Imdb->isEnabled()) {
            return [];
        }

        $found = [];

        foreach ([TitleKind::Movie, TitleKind::TvShow] as $kind) {
            $tmdbIds = array_map(fn (MovieListing $listing): int => $listing->tmdbId, array_filter($this->titles->listings(), fn (MovieListing $listing): bool => $listing->kind === $kind));
            $tmdbIdsWithoutImdbId = array_values(array_filter($tmdbIds, fn (int $tmdbId): bool => $this->titles->rowsOf($kind)->get($tmdbId)?->imdb_id === null));

            if ($tmdbIdsWithoutImdbId === []) {
                continue;
            }

            $imdbIds = $this->imdbIds->lookUpImdbIds($tmdbIdsWithoutImdbId, $kind);

            foreach (RatingsByCatalogId::for($this->ratingSources, $imdbIds, $kind) as $tmdbId => $ratings) {
                if (array_filter($ratings, fn (ExternalRating $rating): bool => $rating->source === RatingSourceName::Imdb) !== []) {
                    $found[ListedTitles::key($kind, $tmdbId)] = array_map(fn (ExternalRating $rating): array => $rating->toArray(), $ratings);
                }
            }
        }

        return $found;
    }
}
