<?php

namespace App\Http\Controllers;

use App\Http\Exceptions\DetailsUnavailable;
use App\Models\Movie;
use App\Models\Title;
use App\Models\TvShow;
use App\Services\Catalog\Catalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\Support\Header;

class TvShowController extends Controller
{
    public function show(Request $request, string $slug, string $id, Catalog $catalog): Response|RedirectResponse
    {
        abort_unless(config('catalog.tv_shows.enabled'), 404);
        $titleId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($titleId === false, 404);
        $title = Title::query()->findOrFail($titleId);
        if ($title->kind === 'movie') {
            return redirect(Movie::query()->findOrFail($titleId)->canonicalUrl(), 301);
        }
        $tvShow = TvShow::query()->findOrFail($titleId);
        $result = $catalog->tvShowDetails($tvShow);
        abort_if($result->isRemoved(), 410);
        throw_if($result->isUnavailable(), DetailsUnavailable::class);
        if ($slug !== $tvShow->slug) {
            return redirect($tvShow->canonicalUrl(), 301);
        }
        if (! $request->hasHeader(Header::PARTIAL_COMPONENT)) {
            $tvShow->update(['last_viewed_at' => now()]);
        }

        return Inertia::render('tv-shows/Show', [
            'canonicalUrl' => $tvShow->canonicalUrl(),
            'imageBaseUrl' => config('catalog.image_base_url'),
            'catalogStatus' => $result->status->value,
            'tvShow' => [
                'id' => $tvShow->id, 'title' => $tvShow->title, 'overview' => $tvShow->overview, 'posterPath' => $tvShow->poster_path,
            ],
        ]);
    }
}
