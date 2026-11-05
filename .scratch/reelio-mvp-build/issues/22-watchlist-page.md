# 22: Watchlist page

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** A signed-in user sees their Watchlist as a poster grid they can sort and page through without ever skipping a Movie, and act on each card. See the spec's "Watchlist and Watched pages" section.

**Blocked by:** [10: External ratings: the TMDB rating](./10-external-ratings-tmdb.md), [16: Mark as watched and Clear Watched](./16-mark-as-watched-and-clear-watched.md), [19: Discover homepage](./19-discover-homepage.md)

**Status:** ready-for-agent

- [ ] `/watchlist` behind `auth` (guests go to login and return), client-rendered, `noindex`, read only from Postgres
- [ ] The poster-card grid (2–6 columns, first 6 cards eager) with one External rating per card via one batch `Ratings` call, and "Last watched {date}" on rewatch-queued Movies
- [ ] The sort menu (Recently added default, Oldest added, Title A–Z, Newest release, Oldest release) in `?sort=` (omitted for the default, unknown values rejected); release sorts put undated Movies last both ways; title sort ignores case and accents; ties break on the `user_movies` id
- [ ] Keyset pagination, 48 per page, `Inertia::scroll()` with a manual "Show more"; removing cards optimistically never skips a Movie on the next page; Movies without a title are excluded from the paged query
- [ ] Header: the Watchlist / Watched switch, "Watchlist · {count}" updated optimistically, the sort menu
- [ ] Each card has the floating bookmark and a "⋯" menu with Mark as watched; when a card leaves, focus moves to the same control on the next card, else the previous, else the empty-state heading
- [ ] Empty state: "Your Watchlist is empty" / "Tap the bookmark on any movie to save it here." plus the cached Trending grid (hidden if unavailable)
- [ ] Show more shows a spinner and shimmer cards; a sort change dims the grid until the first page arrives; Back restores sort, pages and scroll; card links carry the `watchlist` referrer
- [ ] Feature tests cover each sort, keyset paging across removals, authorization and the empty state
