<?php

namespace App\Http\Controllers;

use App\Http\Support\DiscoverPage;
use App\Services\Catalog\Discover\DiscoverBuilder;
use App\Services\Ratings\Ratings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Discover, the homepage: the same hero and sections for every visitor, with only `userMovies` differing per
 * user. The props are lazy, so reloading only `userMovies` after a Watchlist action reads nothing else.
 */
class DiscoverController extends Controller
{
    public function __invoke(Request $request, DiscoverBuilder $discover, Ratings $ratings): Response
    {
        $page = new DiscoverPage($discover, $ratings, $request->user());

        return Inertia::render('Discover', [
            'imageBaseUrl' => config('catalog.image_base_url'),
            'hero' => fn (): ?array => $page->hero(),
            'sections' => fn (): array => $page->sections(),
            'userMovies' => fn (): array => $page->userMovies(),
        ]);
    }
}
