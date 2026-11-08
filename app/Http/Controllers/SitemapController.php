<?php

namespace App\Http\Controllers;

use App\Jobs\BuildSitemap;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class SitemapController extends Controller
{
    /**
     * The sitemap index, and the sitemap files it lists, as `BuildSitemap` last wrote them. While the indexing flag
     * is off there is no sitemap, and before the first build there is none yet.
     */
    public function index(): Response
    {
        return $this->serve(BuildSitemap::INDEX_PATH);
    }

    public function show(int $page): Response
    {
        return $this->serve(BuildSitemap::pagePath($page));
    }

    private function serve(string $path): Response
    {
        abort_unless(config('seo.indexing'), 404);

        $contents = Storage::disk(config('seo.sitemap_disk'))->get($path);
        abort_if($contents === null, 404);

        return response($contents)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
