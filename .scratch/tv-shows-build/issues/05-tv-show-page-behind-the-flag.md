# 05: A TV Show page appears behind the flag

**What to build:** A signed-out visitor can open a TV Show end to end, behind the `catalog.tv_shows.enabled` flag (config only, env `CATALOG_TV_SHOWS`, default off, on in the test setup). Add the entry route `/tv-shows/tmdb/{tmdbId}` (TMDB confirms the id, the row is created, then a 301, rate-limited like the Movie one), the canonical `/tv-shows/{slug}-{id}` page with slug redirect and canonical tag, the cross-kind 301s between the Movie and TV URLs, and the in-request all-or-nothing full fetch (refresh when stale after 24 h; a stored TV Show renders stale on failure; a first view with nothing stored gets the unavailable page; one fetch counts against the row-creation limit and circuit breaker). The page itself is bare: Name, overview and poster. With the flag off, TV routes and the cross-kind redirect 404, and nothing new is created.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 03 Kind-aware TMDB rating ids, 04 TMDB TV adapter, Seasons and Episodes

**Status:** resolved

- [x] `/tv-shows/tmdb/{tmdbId}` creates the row and 301s to the canonical URL; unknown TMDB ids 404 without creating rows
- [x] A wrong slug 301s; the Movie URL for a TV Show's id (and the reverse) 301s to the other kind's canonical URL
- [x] The first view fetches and stores the TV Show, its Seasons and Episodes in one all-or-nothing write
- [x] A failed refresh renders the stored TV Show as stale; a failed first view shows the unavailable page
- [x] With the flag off, TV URLs 404 and no rows are created
- [x] Feature tests use the Catalog fake and cover fresh, stale and unavailable

## Comments

### 2026-11-08 — Implemented

- Added the default-off `CATALOG_TV_SHOWS` flag, with TV Shows enabled in the test setup. Disabled TV entry/canonical routes and cross-kind redirects return 404 before fetching or creating data.
- Added entry and canonical TV Show routes, permanent slug and cross-kind redirects, a canonical tag, and the bare Name/overview/poster page.
- Initial fetches share the Movie creation budget and Catalog circuit breaker. A single transaction stores the Title, TV metadata, Seasons, Episodes, genres, Availability and kind-aware TMDB rating. Refreshes run after 24 hours; failed refreshes render stored data as stale, and failed first fetches return 503 without partial writes.
- Complete snapshots match Seasons and Episodes by TMDB id, preserving local identities. Removed TV Shows clear source data while keeping rows and are never fetched again.
- Standards and spec reviews completed; no remaining material findings after the removal-clearing fix.
- Validation: affected TV and Movie feature tests, frontend production build, app JavaScript tests (19), and full browser suite (161) passed. `composer ci:check` passed after the review fix, including frontend checks, Vue typechecking, Pint, PHPStan and all 769 unit/feature tests.
- An additional unscoped `vp test run` discovered a vendor Playwright example with an unavailable `@playwright/test` dependency; app-scoped `vp test run resources/js` passed all 19 tests. No dependency changes were made.

The rich page content and detailed SEO remain in ticket 06; the complete Removed TV Show UI remains in ticket 13, and scheduled refresh/cap/purge work remains in ticket 14.
