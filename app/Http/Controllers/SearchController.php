<?php

namespace App\Http\Controllers;

use App\Actions\RecordEvent;
use App\Http\Support\Search\SearchPages;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Search: title search for everyone, with the query and page in the URL, and Browse while there
 * is no query. Results load a page at a time, merged into those already shown, and `userMovies` merges with
 * them. The page is client-rendered and `noindex`.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, SearchPages $searchPages, RecordEvent $recordEvent): Response
    {
        $page = $searchPages->forRequest($request);

        if ($request->query('tab') === 'tv' && $page->runtimeBand() !== null) {
            $query = $request->query();
            unset($query['tab']);
            unset($query['q']);

            return redirect()->route('search', $query);
        }

        $response = Inertia::render('Search', $page->props())->toResponse($request);
        $response->headers->set('X-Robots-Tag', 'noindex');

        $event = $page->analytics();

        if ($event !== null) {
            $recordEvent->handle($page->request->user, $event['event'], $event['properties']);
        }

        return $response;
    }
}
