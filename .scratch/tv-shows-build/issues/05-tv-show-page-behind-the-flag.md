# 05: A TV Show page appears behind the flag

**What to build:** A signed-out visitor can open a TV Show end to end, behind the `catalog.tv_shows.enabled` flag (config only, env `CATALOG_TV_SHOWS`, default off, on in the test setup). Add the entry route `/tv-shows/tmdb/{tmdbId}` (TMDB confirms the id, the row is created, then a 301, rate-limited like the Movie one), the canonical `/tv-shows/{slug}-{id}` page with slug redirect and canonical tag, the cross-kind 301s between the Movie and TV URLs, and the in-request all-or-nothing full fetch (refresh when stale after 24 h; a stored TV Show renders stale on failure; a first view with nothing stored gets the unavailable page; one fetch counts against the row-creation limit and circuit breaker). The page itself is bare: Name, overview and poster. With the flag off, TV routes and the cross-kind redirect 404, and nothing new is created.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 03 Kind-aware TMDB rating ids, 04 TMDB TV adapter, Seasons and Episodes

**Status:** ready-for-agent

- [ ] `/tv-shows/tmdb/{tmdbId}` creates the row and 301s to the canonical URL; unknown TMDB ids 404 without creating rows
- [ ] A wrong slug 301s; the Movie URL for a TV Show's id (and the reverse) 301s to the other kind's canonical URL
- [ ] The first view fetches and stores the TV Show, its Seasons and Episodes in one all-or-nothing write
- [ ] A failed refresh renders the stored TV Show as stale; a failed first view shows the unavailable page
- [ ] With the flag off, TV URLs 404 and no rows are created
- [ ] Feature tests use the Catalog fake and cover fresh, stale and unavailable
