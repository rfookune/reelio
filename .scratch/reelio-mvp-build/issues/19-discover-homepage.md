# 19: Discover homepage

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** `/` becomes the real Discover page: a daily hero from Trending, then numbered Trending this week, Popular and Top rated rows, the same for every visitor, with bookmark toggles, and graceful failure. See the spec's "Discover" section.

**Blocked by:** [04: Browser test harness and the sign-in journey](./04-browser-tests-and-sign-in-journey.md), [08: Catalog resilience and failure states](./08-catalog-resilience-and-failure-states.md), [09: Trailer](./09-trailer.md), [10: External ratings: the TMDB rating](./10-external-ratings-tmdb.md), [15: Save to the Watchlist](./15-save-to-the-watchlist.md)

**Status:** resolved

- [x] The adapter gains `section(kind, page)`; `Catalog` clamps TMDB's 500-page cap and deduplicates by id
- [x] A Discover payload is built by a daily job dispatched at 05:00 UTC (and by the first request if missing) and only read by visitors; it holds the hero and sections fetched concurrently (deviation: fetched one after another under a time budget; see the build note)
- [x] Hero: the highest-ranked Trending Movie with a backdrop and an overview; title, year, runtime, genres, both External ratings, overview, "Add to Watchlist" and "Watch trailer" (only when a trailer exists; it opens the trailer player from ticket 09)
- [x] Rows of 10: Trending this week (numbered, global), Popular, Top rated; each row shows a small lazy poster (`w92`/`w154`), title, year / runtime, both External ratings and a bookmark toggle using the catalog-id endpoints and `userMovies`
- [x] Deduplicated across sections in page order (the hero excepted), later sections filling from their next TMDB page; adult and poster-less Movies left out
- [x] A failed section keeps its previous version as `stale` for up to 7 days without a notice, then is hidden; if every section fails: "We can't reach our movie data right now" with Retry, Search still reachable
- [x] Row links use the canonical URL when a row exists, else the entry route, with the `discover` referrer
- [x] The top bar floats over a glow from the hero backdrop
- [x] The discover → details → save browser journey at desktop and mobile

## Note from ADR 0001 (2026-11-06)

Per [ADR 0001](../../../docs/adr/0001-one-ratings-table-for-every-rating-source.md), TMDB scores are read from the `ratings` table through the `Ratings` module. When the Discover payload is built, record each title's TMDB score in `ratings` by `tmdb_id` (no Movie row needed), so rows and the hero use the same batch `Ratings` call as every other surface.

## Note from the Services layout (2026-11-06)

`section(kind, page)` is added to `CatalogSource` and implemented in `TmdbCatalogSource`, `GuardedCatalogSource` and `FakeCatalogSource`, all in `app/Services/Catalog/Sources/`. Section cache lifetimes (the 7-day stale window) go in `config/catalog.php`. The daily job is in `app/Jobs/`; the logic stays in `app/Services/Catalog/`.

## Note from the build (2026-11-06)

- **Adapter.** `section(SectionKind, page, context)` reads `trending/movie/week`, `movie/popular` and `movie/top_rated` with `include_adult=false` and no region, returning a `SectionPage` of neutral `MovieListing`s plus `totalPages`. Adult, malformed and repeated listings are dropped, and unsafe image paths cleared. A missing page is a failure, not an empty answer. `TmdbCatalogSource::get()` now holds the shared timeout, retry and 404/401 handling for both calls. `GuardedCatalogSource` puts both calls behind one breaker and throttle. `FakeCatalogSource` takes pages per kind and failing kinds.
- **Builder.** `DiscoverBuilder` (behind `Catalog::discover()` / `buildDiscover()`) clamps pages to `discover.max_pages_per_section`, the list's `totalPages` and TMDB's 500. It fills each section to 10 in page order, leaving out poster-less Movies and those an earlier section has. The hero is the first Trending Movie with a backdrop and an overview whose details resolve, and it stays in Trending. Every listed title's TMDB score is recorded by `tmdb_id`. The payload is stored as plain arrays (`DiscoverMovie`, `DiscoverSection`, read back through `fromArray`, so an unreadable entry counts as missing). Each section and the hero carry their own `builtAt`: a failed one keeps its previous version for 7 days (`discover.stale_after_days`, also the cache TTL), then is hidden, and empty sections are hidden. Ranks are positions after the poster filter.
- **Deviation: one detail call per title.** TMDB lists carry no runtime or `imdb_id`, so to show "year / runtime" and both External ratings on rows, the background build makes one `movie()` call per chosen title (about 31 a day). That's on top of the spec's "10 extra calls" for Coming soon; ticket 20 can take the US dates from these calls instead. A Movie whose details fail keeps the previous build's details; at read time a Movie row's runtime and `imdb_id` fill any gap.
- **Deviation: sequential, not concurrent.** The calls run one after another. A time budget (`discover.build_budget_seconds`: 45 s background, 6 s interactive) stops further calls once it's spent: sections not yet read keep their previous versions, and Movies not yet detailed keep their previous details. So a build that times out on every call still finishes inside `BuildDiscover`'s 85 s timeout. Concurrency (`Http::pool` behind a batch adapter method) is the upgrade if ticket 20's extra calls make the budget too tight.
- **Jobs and requests.** `BuildDiscover` (unique, one try) is dispatched from the 05:00 UTC `daily-slot`. A visitor who finds nothing to show builds the payload interactively (lists and the hero's details only) under a lock; others wait up to `discover.build_wait_seconds`. The full build is then queued, but only when the visitor's build produced something, so an outage doesn't queue a job per request. A rejected token is reported through `MisconfigurationReporter`, now shared with `Catalog`.
- **Page.** `DiscoverController` and `DiscoverPage` link to the canonical URL when a row exists, else to the entry route, both with `referrer=discover`. Ratings come from one `Ratings::ratingsFor` call over stand-in Movies whose `id` is the catalog id, so rows without a Movie show the recorded TMDB score. `userMovies` covers every shown catalog id. Props are lazy, so `only: ['userMovies']` reads nothing else.
- **UI.** `DiscoverHero` has the backdrop (the only `fetchpriority="high"` image), title, meta, ratings, overview, `WatchlistButton` and "Watch trailer". "Watch trailer" opens `TrailerPlayer` (new `hideHeading` prop) in a dialog, so nothing loads from YouTube before play. The top bar turns translucent and borderless through `:root:has([data-top-bar-glow])`, and the hero runs up under it, so the bar's backdrop blur over the hero is the glow. Rows (`DiscoverRow`, `ol` for Trending) use `w92`/`w154` lazy posters and `BookmarkToggle` (44 px on mobile, "Add {title} to Watchlist" / "Remove {title} from Watchlist", `aria-pressed`). With nothing to show, the page says "We can't reach our movie data right now", with Retry (a reload) and a link to Search (`/search`, which 404s until ticket 21).
