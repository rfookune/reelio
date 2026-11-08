# 32: Sitemap

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** While indexing is on, search engines get a daily sitemap of every Movie Reelio has details for. See the spec's sitemap bullet.

**Blocked by:** [31: SEO, SSR and the indexing flag](./31-seo-ssr-and-indexing-flag.md)

**Status:** resolved

- [x] A daily job on `imports` (05:00 UTC slot) builds a sitemap index split at 50k URLs, listing every Movie row that has details (no Removed or cap-cleared Movies) with `lastmod` from `synced_at`
- [x] It runs and is served only while the indexing flag is on; off means no sitemap
- [x] `robots.txt` references the sitemap when the flag is on
- [x] Feature tests cover inclusion, exclusion and the flag

## Note from ticket 27 (2026-11-07)

"Has details" is `Movie::hasSourceDetails()` (`synced_at` not null): Removed Movies and cap-cleared Movies both have `synced_at` cleared, so selecting `whereNotNull('synced_at')` excludes both. The daily `CleanUpCatalog` job clears Movies at 170 days without a sync, and `catalog:purge` clears every row, so a sitemap built after either drops those Movies. Add the sitemap job to the `Schedule::...->group()` in `routes/console.php`. The lists and the picker test `title` not null instead; the two agree except for a synced Movie TMDB gives no title, which the sitemap would list but the lists show as unavailable, so pick one rule for both if that matters.

## Comments

- Resolution: implemented in `4f4ee56` and redirected in `ca2f16a`.
- Redirection: the first version wrote the sitemap files to a filesystem disk (`FILESYSTEM_DISK`, `local` by default). The `imports` worker scales to zero and the web servers don't share its disk, so they would have returned 404. The sitemap now lives in the `sitemap_pages` table (`name`, `xml`, `built_at`), with `seo.sitemap_disk` removed. `BuildSitemap` builds every document in memory and swaps the table's contents in one transaction, so a run that fails midway leaves the previous sitemap whole.
- Run `php artisan migrate` to create `sitemap_pages`. The migration was written but not run against a real database.
- Routes: `/sitemap.xml` (`sitemap.index`) and `/sitemaps/{page}.xml` (`sitemap.show`). Both return 404 while the indexing flag is off or before the first build. `robots.txt` adds `Sitemap: {index url}` when the flag is on.
- The job is scheduled in the 05:00 UTC group with a 10-minute delay, so it reads what `CleanUpCatalog` cleared. Off, it empties the table, so switching the flag back on never serves a stale build.
- The split size is `seo.sitemap_page_size` (50,000); tests set it to 2.
- Ticket 27's question stays open: the sitemap selects `synced_at` not null, so a synced Movie TMDB gives no title is listed, while the lists show it as unavailable.
- A build holds every document in memory (about 5 MB per 50,000 URLs); stream to the table if the catalog grows to millions of Movies.
- Not verified: the sitemap routes aren't in `indexable_routes`, so they likely carry `X-Robots-Tag: noindex`, which crawlers generally ignore on sitemaps.
- Tests: `tests/Feature/SitemapTest.php`; `tests/Feature/IndexingTest.php` was adjusted for the `Sitemap:` line in `robots.txt`.
