# 32: Sitemap

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** While indexing is on, search engines get a daily sitemap of every Movie Reelio has details for. See the spec's sitemap bullet.

**Blocked by:** [31: SEO, SSR and the indexing flag](./31-seo-ssr-and-indexing-flag.md)

**Status:** ready-for-agent

- [ ] A daily job on `imports` (05:00 UTC slot) builds a sitemap index split at 50k URLs, listing every Movie row that has details (no Removed or cap-cleared Movies) with `lastmod` from `synced_at`
- [ ] It runs and is served only while the indexing flag is on; off means no sitemap
- [ ] `robots.txt` references the sitemap when the flag is on
- [ ] Feature tests cover inclusion, exclusion and the flag

## Note from ticket 27 (2026-11-07)

"Has details" is `Movie::hasSourceDetails()` (`synced_at` not null): Removed Movies and cap-cleared Movies both have `synced_at` cleared, so selecting `whereNotNull('synced_at')` excludes both. The daily `CleanUpCatalog` job clears Movies at 170 days without a sync, and `catalog:purge` clears every row, so a sitemap built after either drops those Movies. Add the sitemap job to the `Schedule::...->group()` in `routes/console.php`. The lists and the picker test `title` not null instead; the two agree except for a synced Movie TMDB gives no title, which the sitemap would list but the lists show as unavailable, so pick one rule for both if that matters.
