# 21: Search page

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Anyone can search Movies by title on `/search`, with live results, the query in the URL, poster cards, "Show more results" and clear empty and error states. See the spec's "Search" section.

**Blocked by:** [10: External ratings: the TMDB rating](./10-external-ratings-tmdb.md), [15: Save to the Watchlist](./15-save-to-the-watchlist.md), [19: Discover homepage](./19-discover-homepage.md)

**Status:** ready-for-agent

- [ ] The adapter gains `search(query, page)`; results are cached per query and page for 1 h and served `stale` for up to 24 h; `include_adult=false`, TMDB's order, poster-less Movies kept
- [ ] `/search` has one input (autofocused with no query); search starts at 2 characters after a 300 ms debounce; Enter skips the debounce; a newer request cancels the one in flight; each change replaces the history entry
- [ ] `?q=` (trimmed, at most 100 characters) and `?page=` (1–500) sync with the page; Back restores the query, loaded pages and scroll
- [ ] Results use the poster card (2–6 columns): poster or tile, floating bookmark, 2-line title, year when known, and IMDb when the Movie has a row with an IMDb rating, else TMDB, else "Not yet rated"; no "⋯" menu; the first 6 cards eager, the rest lazy
- [ ] 20 per page with `Inertia::scroll()` and a manual "Show more results" button, stopping at TMDB page 500; a count from `total_results`
- [ ] Every state in the spec's table: Trending suggestions from the Discover payload for no query and no results, shimmer on first load, dimmed results while the query changes, the button spinner, the live-region announcement, `stale` shown normally, and "We can't search right now" with Retry
- [ ] Links go to the canonical URL or the entry route with the `search` referrer
- [ ] Search is limited to 60/min per user or IP; the page is client-rendered and `noindex`
- [ ] `movie_searched` with `query_length` and `result_count` on each page-1 response; the query text is never stored
- [ ] Browser tests cover debounce and cancellation; feature tests cover validation, paging, states and the event
