# 31: SEO, SSR and the indexing flag

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Public pages render on the server with complete metadata, and one config flag (off by default) controls whether anything is indexable. See the spec's "Public pages, SSR and SEO" section.

**Blocked by:** [07: Movie page layout and details](./07-movie-page-layout-and-details.md), [19: Discover homepage](./19-discover-homepage.md)

**Status:** resolved

- [x] Inertia SSR is on for Discover, Movie pages, About, Privacy, Terms and Accessibility only, off for Search, auth, Watchlist, Watched and settings; client rendering takes over if SSR is down
- [x] One `<Head>` helper sets titles ("{Title} ({year}) · Reelio", "{Page} · Reelio"), descriptions (overview cut to ~155 characters at a word boundary; a fixed line for Discover), Open Graph and Twitter images (backdrop `w1280`, else poster, else a static card) and the canonical URL
- [x] Movie pages carry schema.org `Movie` JSON-LD (`name`, `datePublished`, `director`, `genre`, `duration`, `image`, `description`, `url`), no `aggregateRating`
- [x] An indexing flag, default off: off means `robots.txt` is `Disallow: /` and every page is `noindex`; on means only Discover, Movie pages, About, Privacy, Terms and Accessibility are indexable
- [x] `X-Robots-Tag: noindex` on Search, auth, Watchlist, Watched, settings, the picker endpoint, the entry route's 301, the TMDB watch-page redirect, 410 pages and the cap-cleared 503, regardless of the flag
- [x] SSR output contains the Movie page's cast and Director
- [x] Feature tests cover both flag states, the header list and the metadata

## Comments

- Resolution: implemented in `fdb5391` and revised in `b8cb814`.
- SSR and indexing are two lists in `config/seo.php`, not one: `ssr_routes` and `indexable_routes`, `indexable_routes` defaults to Discover, Movie pages and the About, Privacy, Terms and Accessibility route names (`about`, `privacy`, `terms`, `accessibility`). `ssr_routes` has the same names plus `login` and `register`: a deviation from the ticket's "off for auth", decided by the owner after the split. Login and Register are server-rendered but stay `noindex`; the other auth pages are client-rendered. The real SSR bundle renders both pages. A page can be server-rendered without being indexable. Server-rendering more pages, such as Login, is a one-line change to `ssr_routes`.
- The indexing flag is `SEO_INDEXING_ENABLED` (`config('seo.indexing')`), off by default. `public/robots.txt` was replaced by a route, because a static file would shadow it. Off: `Disallow: /` and every page `noindex`. On: `Disallow:` and only 200 responses on `indexable_routes` are indexable. The `ApplyIndexingPolicy` middleware sets the `noindex` header and keeps one a response already set.
- The About, Privacy, Terms and Accessibility pages don't exist yet (ticket 33). Their route names must match the lists, and the tests cover them with throwaway routes until then.
- The document title separator changed from `-` to `·` for every page, so a Cast page reads "Full cast · {title} · Reelio". Its title wasn't moved onto the helper because the page is `noindex`.
- Added beyond the ticket: `og:type`, `og:site_name`, `og:url` and the Twitter title and description tags, a shared `seo` Inertia prop (`url`, `card`), and `public/og-card.png` as the static share card.
- Tests: `tests/Feature/IndexingTest.php` (both flag states and the header list, including the stale-slug 301), `tests/Feature/ServerRenderingTest.php` (which pages go to the SSR server, and the SSR-down fallback), `tests/Browser/SeoMetadataTest.php` (head tags and JSON-LD; needs `npm run build` first) and vitest files under `resources/js` (run `npx vp test run resources/js`; a bare `vp test run` also picks up a Playwright example under `vendor/`). The SSR server was also checked by hand: its output contains the cast and Director.
- Not covered: the metadata tests aren't feature tests, because SSR is off in the test environment. The feature tests check the props that feed the head.
