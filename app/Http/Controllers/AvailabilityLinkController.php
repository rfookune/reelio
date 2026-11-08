<?php

namespace App\Http\Controllers;

use App\Actions\RecordEvent;
use App\Http\Support\VisitorCountry;
use App\Models\Movie;
use App\Services\Catalog\Catalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "See all watch options on TMDB": records the click, then sends the visitor to TMDB's watch page for the
 * Movie in a supported country. The target is built only from the Movie's `tmdb_id` and that country, never
 * from anything TMDB or the request supplies.
 */
class AvailabilityLinkController extends Controller
{
    private const TMDB_WATCH_PAGE = 'https://www.themoviedb.org/movie/%d/watch?locale=%s';

    public function __invoke(Request $request, string $id, Catalog $catalog, RecordEvent $recordEvent): RedirectResponse
    {
        $movie = $catalog->findTitle($id);
        abort_unless($movie instanceof Movie, 404);
        $country = $request->query('country');
        abort_unless(VisitorCountry::isSupported($country), 404);

        $recordEvent->handle($request->user(), 'availability_link_clicked', ['movie_id' => $movie->id, 'country' => $country]);

        return redirect()->away(sprintf(self::TMDB_WATCH_PAGE, $movie->tmdb_id, $country))->header('X-Robots-Tag', 'noindex');
    }
}
