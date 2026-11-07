# 21: Search page

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Anyone can search Movies by title on `/search`, with live results, the query in the URL, poster cards, "Show more results" and clear empty and error states. See the spec's "Search" section.

**Blocked by:** [10: External ratings: the TMDB rating](./10-external-ratings-tmdb.md), [15: Save to the Watchlist](./15-save-to-the-watchlist.md), [19: Discover homepage](./19-discover-homepage.md)

**Status:** resolved

- [x] The adapter gains `search(query, page)`; results are cached per query and page for 1 h and served `stale` for up to 24 h; `include_adult=false`, TMDB's order, poster-less Movies kept
- [x] `/search` has one input (autofocused with no query); search starts at 2 characters after a 300 ms debounce; Enter skips the debounce; a newer request cancels the one in flight; each change replaces the history entry
- [x] `?q=` (trimmed, at most 100 characters) and `?page=` (1–500) sync with the page; Back restores the query, loaded pages and scroll
- [x] Results use the poster card (2–6 columns): poster or tile, floating bookmark, 2-line title, year when known, and IMDb when the Movie has a row with an IMDb rating, else TMDB, else "Not yet rated"; no "⋯" menu; the first 6 cards eager, the rest lazy
- [x] 20 per page with `Inertia::scroll()` and a manual "Show more results" button, stopping at TMDB page 500; a count from `total_results`
- [x] Every state in the spec's table: Trending suggestions from the Discover payload for no query and no results, shimmer on first load, dimmed results while the query changes, the button spinner, the live-region announcement, `stale` shown normally, and "We can't search right now" with Retry
- [x] Links go to the canonical URL or the entry route with the `search` referrer
- [x] Search is limited to 60/min per user or IP; the page is client-rendered and `noindex`
- [x] `movie_searched` with `query_length` and `result_count` on each page-1 response; the query text is never stored
- [x] Browser tests cover debounce and cancellation; feature tests cover validation, paging, states and the event

## Note from ADR 0001 (2026-11-06)

Per [ADR 0001](../../../docs/adr/0001-one-ratings-table-for-every-rating-source.md), TMDB scores are read from the `ratings` table through the `Ratings` module. When search results are fetched, record each result's TMDB score in `ratings` by `tmdb_id` (no Movie row needed), so the card rating comes from the same batch `Ratings` call as every other surface.

## Note from the Services layout (2026-11-06)

`search(query, page)` is added to `CatalogSource` and its three implementations in `app/Services/Catalog/Sources/`. The 1 h fresh and 24 h stale windows go in `config/catalog.php`, not as class constants. Recording the TMDB score goes through `RatingStore` (`App\Services\Ratings`), the allowed direction.

## Note from the build (2026-11-07)

- **Adapter.** `search(query, page, context)` reads `search/movie` with `include_adult=false` and the language, and returns `SearchResults`: neutral `MovieListing`s in TMDB's order, poster-less ones included, plus `totalPages` and `totalResults`. Adult, malformed and repeated listings are dropped, as for sections, through the list parsing the two now share. `GuardedCatalogSource` puts it behind the same breaker and throttle; `FakeCatalogSource` takes pages per query and records its calls.
- **Catalog.** `Catalog::search(query, page)` returns a `CatalogResult`. A page read within `search.fresh_seconds` (1 h) comes from the cache; an older or missing one is read again, recording every result's TMDB score by `tmdb_id`. When the source fails, the cached page is `stale` until `search.stale_seconds` (24 h) after it was read, then the search is `unavailable`. That age is checked from the stored read time, not only the cache TTL. The cache key holds a hash of the query, never its text. `Catalog::storedTrending()` reads Discover's stored Trending without building the payload, and `Catalog::LAST_SOURCE_PAGE` (500) is now shared with the Discover builder.
- **Request.** `GET /search` is behind `throttle:search` (60/min, user or IP), sends `X-Robots-Tag: noindex` (and a robots meta tag), and is in `HandleInertiaRequests::$withoutSsr`. `q` is trimmed of Unicode whitespace (`Str::trim`) and cut to `search.maximum_query_length` (100); a non-string `q` is no query. `page` is clamped to 1–500, and anything that isn't digits is 1. A query under `search.minimum_query_length` (2) isn't searched. Both lengths are passed to the page, so the client applies the same rules.
- **Props** (`App\Http\Support\SearchPage`, lazy like Discover's):
  - `results` is `Inertia::scroll()` with metadata stopping at the search's last page or 500, matched on `data.tmdbId` so a Movie repeated across pages appears once.
  - `totalResults` and `status` (`fresh`, `stale`, `unavailable` or null).
  - `trending`, shown with no search, no results or an unavailable search.
  - `userMovies` is an `Inertia::merge` prop, and "Show more" reloads it with each page (`params.only`), so later pages' bookmarks are right.
  - Ratings come from one `Ratings` call through `RatingsByCatalogId`, now shared with `DiscoverPage`. The IMDb id comes only from the Movie's row, so a card shows IMDb only for a Movie whose row has one, Trending included.
  - A partial reload of `userMovies` alone, as after a Watchlist action, reads the search from the cache only (`Catalog::cachedSearch`). It isn't counted by `throttle:search`, so a 429 there can't roll back a save that succeeded. The action has its own budget, and the exemption can only read the cache.
  - A later page the source can't answer gets scroll metadata pointing at itself, so "Show more" asks for it again.
- **Event.** `movie_searched` with `query_length` and `result_count` is recorded when a page-1 `results` prop is resolved. A search that isn't answered records nothing, and neither does a `userMovies`-only reload.
- **Page** (`pages/Search.vue`):
  - **Query visits.** Typing waits 300 ms; Enter submits at once. Each query visit is a `replace` partial reload naming every prop the query changes, with `reset: ['results', 'userMovies']`. Inertia turns `reset` into a partial reload, so the props have to be named; it also resets `<InfiniteScroll>`. A newer visit cancels the one in flight (Inertia's sync visits). The query last *asked for* (shown or in flight) isn't sent again unless its answer failed, so going back to the shown query still cancels a newer one in flight.
  - **The input** is filled from `query` only on mount, so a slow answer never overwrites typing.
  - **Settling.** Whether the page is settling comes from the request lifecycle (typing not yet sent, or a visit in flight), not from comparing the input with the server's query. A trimming difference between JS and PHP therefore costs at most one extra request; it can never leave the page stuck.
  - **Loading and the count.** Results dim while settling. The first search shows shimmer cards, and "Show more results" shows a spinner plus appended shimmer cards. The count comes from `total_results`.
  - **Messages.** "No movies match …" shows with the tip and Trending. Unavailable, network errors and 429 show inline ("We can't search right now" / "You're going too fast. Try again in a moment.") with Retry, which keeps focus by moving it to the input; older results are hidden behind the error. A failed "Show more" (429, network, or the source) shows a line above the button, which then retries. Settled first pages and every failure are announced in the shell's live region.
  - **Earlier pages.** A deep link to a later page offers "Show earlier results".
- **Components.** `PosterCard` (the Watchlist and Watched card, ready for ticket 22) and `PosterCardSkeleton`. `BookmarkToggle` gains a `floating` variant on a solid chip (44 px on mobile), and `TmdbImage` gains `loading` and `placeholder`, so card posters have empty alt text while the missing-poster tile still shows the title. The first 6 cards load eagerly.
- **Focus.** `pageFocus` now focuses an element marked `data-autofocus` instead of the `<h1>`, so Search with no query starts in its input.
- **Wayfinder.** The Search nav item and Discover's failure-state link now use the `search` route.
- **Search text in the session.** Two places would otherwise keep `q` in the session.
  - The framework's previous URL, stored on every full-page GET. `App\Http\Middleware\StartSession` replaces the framework's in the web group and stores Search's path alone.
  - A guest's recorded last page, which `IntendedUrl` now records as `/search` without `q`.

  The spec says a guest lands back on the page after signing in, and user story 19 says search text is never stored. Privacy wins: a guest who saves from Search returns to an empty Search, with the usual toast.
- **Accepted risk.** The `throttle:search` exemption for `userMovies`-only reloads trusts two Inertia request headers the client controls. Anyone who forges them gets unlimited cache-only search reads plus a few database queries per request, never a TMDB call. Cloud's edge limit covers production.
- **Known limits:**
  - Redacting `q` from request logs, Nightwatch and Sentry is left to the monitoring ticket (29).
  - A deep link to a page past the search's last page is `unavailable`, because TMDB (and the fake) answer it as a missing page.
