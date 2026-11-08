<?php

namespace App\Jobs;

use App\Models\Movie;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the sitemap daily at 05:00 UTC while the indexing flag is on: one file per `seo.sitemap_page_size` Movies
 * that have details, then the index that lists them. A Removed or cap-cleared Movie has no `synced_at`, so it is
 * left out. Files from an earlier, longer build are deleted after the new ones are written, and the index is written
 * last so it never lists a page that doesn't exist yet. Off, it deletes any earlier build so that switching the flag back on never serves a stale one.
 */
class BuildSitemap implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const INDEX_PATH = 'sitemap/index.xml';

    public int $timeout = 300;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function __construct()
    {
        $this->onQueue('imports');
    }

    public static function pagePath(int $page): string
    {
        return "sitemap/{$page}.xml";
    }

    public function handle(): void
    {
        $disk = Storage::disk(config('seo.sitemap_disk'));

        if (! config('seo.indexing')) {
            $disk->deleteDirectory('sitemap');

            return;
        }

        $pageSize = max(1, (int) config('seo.sitemap_page_size'));
        $pages = 0;
        $entries = '';
        $count = 0;

        $flush = function () use (&$pages, &$entries, &$count, $disk): void {
            if ($count === 0) {
                return;
            }

            $disk->put(self::pagePath(++$pages), $this->document('urlset', $entries));
            $entries = '';
            $count = 0;
        };

        foreach (Movie::query()->whereNotNull('synced_at')->orderBy('id')->lazyById(1000) as $movie) {
            $entries .= '<url><loc>'.$this->escape($movie->canonicalUrl()).'</loc><lastmod>'.$movie->synced_at->toIso8601String().'</lastmod></url>';

            if (++$count === $pageSize) {
                $flush();
            }
        }

        $flush();

        $locations = '';
        for ($page = 1; $page <= $pages; $page++) {
            $locations .= '<sitemap><loc>'.$this->escape(route('sitemap.show', ['page' => $page])).'</loc></sitemap>';
        }

        $disk->put(self::INDEX_PATH, $this->document('sitemapindex', $locations));

        foreach ($disk->files('sitemap') as $file) {
            if (preg_match('#^sitemap/(\d+)\.xml$#', $file, $matches) && (int) $matches[1] > $pages) {
                $disk->delete($file);
            }
        }
    }

    private function document(string $root, string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n<{$root} xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">{$body}</{$root}>\n";
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
