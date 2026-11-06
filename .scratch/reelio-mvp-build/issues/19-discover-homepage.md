# 19: Discover homepage

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** `/` becomes the real Discover page: a daily hero from Trending, then numbered Trending this week, Popular and Top rated rows, the same for every visitor, with bookmark toggles, and graceful failure. See the spec's "Discover" section.

**Blocked by:** [04: Browser test harness and the sign-in journey](./04-browser-tests-and-sign-in-journey.md), [08: Catalog resilience and failure states](./08-catalog-resilience-and-failure-states.md), [09: Trailer](./09-trailer.md), [10: External ratings: the TMDB rating](./10-external-ratings-tmdb.md), [15: Save to the Watchlist](./15-save-to-the-watchlist.md)

**Status:** ready-for-agent

- [ ] The adapter gains `section(kind, page)`; `Catalog` clamps TMDB's 500-page cap and deduplicates by id
- [ ] A Discover payload is built by a daily job dispatched at 05:00 UTC (and by the first request if missing) and only read by visitors; it holds the hero and sections fetched concurrently
- [ ] Hero: the highest-ranked Trending Movie with a backdrop and an overview; title, year, runtime, genres, both External ratings, overview, "Add to Watchlist" and "Watch trailer" (only when a trailer exists; it opens the trailer player from ticket 09)
- [ ] Rows of 10: Trending this week (numbered, global), Popular, Top rated; each row shows a small lazy poster (`w92`/`w154`), title, year / runtime, both External ratings and a bookmark toggle using the catalog-id endpoints and `userMovies`
- [ ] Deduplicated across sections in page order (the hero excepted), later sections filling from their next TMDB page; adult and poster-less Movies left out
- [ ] A failed section keeps its previous version as `stale` for up to 7 days without a notice, then is hidden; if every section fails: "We can't reach our movie data right now" with Retry, Search still reachable
- [ ] Row links use the canonical URL when a row exists, else the entry route, with the `discover` referrer
- [ ] The top bar floats over a glow from the hero backdrop
- [ ] The discover → details → save browser journey at desktop and mobile

## Note from ADR 0001 (2026-11-06)

Per [ADR 0001](../../../docs/adr/0001-one-ratings-table-for-every-rating-source.md), TMDB scores are read from the `ratings` table through the `Ratings` module. When the Discover payload is built, record each title's TMDB score in `ratings` by `tmdb_id` (no Movie row needed), so rows and the hero use the same batch `Ratings` call as every other surface.

## Note from the Services layout (2026-11-06)

`section(kind, page)` is added to `CatalogSource` and implemented in `TmdbCatalogSource`, `GuardedCatalogSource` and `FakeCatalogSource`, all in `app/Services/Catalog/Sources/`. Section cache lifetimes (the 7-day stale window) go in `config/catalog.php`. The daily job is in `app/Jobs/`; the logic stays in `app/Services/Catalog/`.
