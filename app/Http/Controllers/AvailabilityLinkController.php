<?php

namespace App\Http\Controllers;

use App\Actions\RecordEvent;
use App\Catalog\Catalog;
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
        $movieId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($movieId === false, 404);
        $movie = $catalog->find($movieId);
        $country = $request->query('country');
        abort_unless(is_string($country) && in_array($country, config('catalog.supported_countries'), true), 404);

        $recordEvent->handle($request->user(), 'availability_link_clicked', ['movie_id' => $movie->id, 'country' => $country]);

        return redirect()->away(sprintf(self::TMDB_WATCH_PAGE, $movie->tmdb_id, $country))->header('X-Robots-Tag', 'noindex');
    }
}
