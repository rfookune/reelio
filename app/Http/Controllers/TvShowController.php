<?php

namespace App\Http\Controllers;

use App\Http\Exceptions\DetailsUnavailable;
use App\Http\Exceptions\MovieRemoved;
use App\Http\Support\VisitorCountry;
use App\Http\Support\WhereToWatch;
use App\Models\Movie;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\Enums\CatalogStatus;
use App\Services\Ratings\ExternalRating;
use App\Services\Ratings\Ratings;
use App\Services\TvShows\WatchedProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\Support\Header;

class TvShowController extends Controller
{
    public function show(Request $request, string $slug, string $id, Catalog $catalog, Ratings $ratings, WatchedProgress $progress): Response|RedirectResponse
    {
        abort_unless(config('catalog.tv_shows.enabled'), 404);
        $title = $catalog->findTitle($id);
        if ($title instanceof Movie) {
            return redirect($title->canonicalUrl(), 301);
        }
        $tvShow = $title;
        throw_if($tvShow->isRemoved(), MovieRemoved::class, $tvShow, $request->user());
        if ($tvShow->hasSourceDetails() && $slug !== $tvShow->slug) {
            return redirect($tvShow->canonicalUrl(), 301);
        }
        $result = $catalog->tvShowDetails($tvShow);
        throw_if($result->isRemoved(), MovieRemoved::class, $tvShow, $request->user());
        throw_if($result->isUnavailable() || ! $tvShow->hasSourceDetails(), DetailsUnavailable::class, $tvShow, $request->user());
        if ($slug !== $tvShow->slug) {
            return redirect($tvShow->canonicalUrl(), 301);
        }
        if (! $request->hasHeader(Header::PARTIAL_COMPONENT)) {
            $tvShow->update(['last_viewed_at' => now()]);
        }

        $pageData = $catalog->tvShowPageData($tvShow);
        $metadata = $tvShow->details;
        $airedEpisodes = $tvShow->episodes()->whereNull('removed_at')->whereDate('air_date', '<=', today())
            ->whereHas('season', fn (Builder $season): Builder => $season->where('season_number', '>', 0)->whereNull('removed_at'));
        $seasonCount = (clone $airedEpisodes)->distinct()->count('season_id');
        $episodeCount = $airedEpisodes->count();

        return Inertia::render('tv-shows/Show', [
            'whereToWatch' => fn (): array => WhereToWatch::for($tvShow, VisitorCountry::resolve($request), $result->status === CatalogStatus::Stale),
            'watchlisted' => $tvShow->isWatchlistedBy($request->user()),
            'progress' => $progress->for($tvShow, $request->user()),
            'canonicalUrl' => $tvShow->canonicalUrl(),
            'imageBaseUrl' => config('catalog.image_base_url'),
            'catalogStatus' => $result->status->value,
            'tvShow' => [
                'id' => $tvShow->id, 'tmdbId' => $tvShow->tmdb_id, 'title' => $tvShow->title, 'overview' => $tvShow->overview, 'posterPath' => $tvShow->poster_path,
                'originalTitle' => $tvShow->original_title, 'originalLanguage' => $tvShow->original_language,
                'tagline' => $tvShow->tagline, 'backdropPath' => $tvShow->backdrop_path,
                'firstAirDate' => $metadata->first_air_date?->toDateString(), 'lastAirDate' => $metadata->last_air_date?->toDateString(),
                'status' => $metadata->status, 'seasonCount' => $seasonCount, 'episodeCount' => $episodeCount,
                'genres' => $tvShow->genres()->orderBy('name')->pluck('name')->all(),
                'creators' => $pageData->creators ?? [], 'networks' => $pageData->networks ?? [],
                'cast' => array_slice($pageData->cast ?? [], 0, config('catalog.movie_page.cast_limit')),
                'certification' => $pageData?->certification, 'trailer' => $pageData?->trailer,
                'ratings' => array_map(fn (ExternalRating $rating): array => $rating->toArray(), $ratings->ratingsFor(collect([$tvShow]))[$tvShow->id]),
                'detailsUnavailable' => $pageData === null,
            ],
        ]);
    }
}
