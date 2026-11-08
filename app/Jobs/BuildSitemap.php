<?php

namespace App\Jobs;

use App\Models\Movie;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Builds the sitemap daily at 05:00 UTC while the indexing flag is on: one document per `seo.sitemap_page_size`
 * Movies that have details, then the index that lists them, all in the `sitemap_pages` table so every web server
 * serves the same build. A Removed or cap-cleared Movie has no `synced_at`, so it is left out. The documents are
 * built first and swapped in one transaction, so a run that fails midway leaves the previous sitemap whole. Off, it
 * deletes any earlier build so that switching the flag back on never serves a stale one.
 */
class BuildSitemap implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const INDEX = 'index';

    public int $timeout = 300;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function __construct()
    {
        $this->onQueue('imports');
    }

    public static function pageName(int $page): string
    {
        return (string) $page;
    }

    public function handle(): void
    {
        if (! config('seo.indexing')) {
            DB::table('sitemap_pages')->delete();

            return;
        }

        $pageSize = max(1, (int) config('seo.sitemap_page_size'));
        $builtAt = now();
        $pages = [];
        $entries = '';
        $count = 0;

        foreach (Movie::query()->whereNotNull('synced_at')->orderBy('id')->lazyById(1000) as $movie) {
            $entries .= '<url><loc>'.$this->escape($movie->canonicalUrl()).'</loc><lastmod>'.$movie->synced_at->toIso8601String().'</lastmod></url>';

            if (++$count === $pageSize) {
                $pages[] = $entries;
                $entries = '';
                $count = 0;
            }
        }

        if ($count > 0) {
            $pages[] = $entries;
        }

        $documents = [];
        $locations = '';
        foreach ($pages as $index => $body) {
            $name = self::pageName($index + 1);
            $documents[$name] = $this->document('urlset', $body);
            $locations .= '<sitemap><loc>'.$this->escape(route('sitemap.show', ['page' => $name])).'</loc></sitemap>';
        }

        $documents[self::INDEX] = $this->document('sitemapindex', $locations);

        DB::transaction(function () use ($documents, $builtAt): void {
            DB::table('sitemap_pages')->delete();

            foreach ($documents as $name => $xml) {
                DB::table('sitemap_pages')->insert(['name' => (string) $name, 'xml' => $xml, 'built_at' => $builtAt]);
            }
        });
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
