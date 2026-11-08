<?php

namespace App\Http\Controllers;

use App\Jobs\BuildSitemap;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SitemapController extends Controller
{
    /**
     * The sitemap index, and the sitemap files it lists, as `BuildSitemap` last stored them. While the indexing flag
     * is off there is no sitemap, and before the first build there is none yet.
     */
    public function index(): Response
    {
        return $this->serve(BuildSitemap::INDEX);
    }

    public function show(int $page): Response
    {
        return $this->serve(BuildSitemap::pageName($page));
    }

    private function serve(string $name): Response
    {
        abort_unless(config('seo.indexing'), 404);

        $contents = DB::table('sitemap_pages')->where('name', $name)->value('xml');
        abort_if($contents === null, 404);

        return response($contents)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
