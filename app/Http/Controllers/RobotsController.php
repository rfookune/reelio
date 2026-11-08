<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    /**
     * Disallow everything while the indexing flag is off, and allow crawling and point to the sitemap once it is on. Private pages are not
     * disallowed: a crawler must fetch them to see their `noindex`.
     */
    public function __invoke(): Response
    {
        $rules = config('seo.indexing') ? "Disallow:\n\nSitemap: ".route('sitemap.index') : 'Disallow: /';

        return response("User-agent: *\n{$rules}\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
