# Watchlist and Watched pages

Type: grilling
Status: resolved
Map: [Reelio MVP](../map.md)

## Question

How do the Watchlist and Watched pages list, sort and page through Movies?

- Whether FR-008's list layout earns its place next to the decided grid card ([Visual language and app shell](./06-visual-language-and-shell.md)).
- Sort options and defaults (date added, Watched date, title, release year, rating) and any filters. The watched-status filter is dropped ([The user–movie lifecycle](./04-user-movie-lifecycle.md)); the picker's genre filter is separate ([Movie picker](./12-movie-picker.md)).
- Pagination or infinite scroll, and the empty states.
- How shells (Movies TMDB has dropped) appear.

## Comments

- From [Movie detail page](./24-movie-detail-page.md) (resolved): a dropped Movie's page is a 410 with no title (every TMDB field is cleared), so a shell card here has no title or poster to show either; decide what identifies it. The detail page shows a Movie that is both Watched and on the Watchlist (a rewatch queued) as "On your Watchlist" plus a quiet "Last watched {date}" line; the Watched card should agree.

## Answer

Both pages read only Reelio's own Postgres rows: every listed Movie has a row and an `imdb_id`. So they never depend on TMDB being up, have no `stale` or `unavailable` states, and show the IMDb rating whenever the dataset has one.

**Layout**

- The poster-card grid only, 2 to 6 columns, using the card from [Visual language and app shell](./06-visual-language-and-shell.md). FR-008's list layout is dropped from P0 and becomes a spec follow-up.

**Sorting**

- One sort menu per page with fixed options and no separate direction toggle:

| Page      | Options (default first)                                                     |
| --------- | --------------------------------------------------------------------------- |
| Watchlist | Recently added, Oldest added, Title A–Z, Newest release, Oldest release     |
| Watched   | Recently watched, Oldest watched, Title A–Z, Newest release, Oldest release |

- Release sorts use the full `release_date`. Undated Movies come last in both directions.
- Title sort ignores case and accents and doesn't strip articles, so _The Matrix_ sorts under T.
- Ties break on the user–movie record's id.
- No rating sort in P0: the card shows IMDb falling back to TMDB, so a rating sort would rank two scales against each other. It is a spec follow-up.
- The sort lives in `?sort=`, omitted for the default. It isn't remembered per user or in a cookie, so the nav always opens the default. Changing it replaces the history entry and resets the loaded pages.

**Filters**

- None on either page. The watched-status filter was already dropped ([The user–movie lifecycle](./04-user-movie-lifecycle.md)), and the PRD's "basic filtering" is met by the Watchlist/Watched split. A genre filter on the list is a follow-up alongside FR-003. The picker's genre filter is separate ([Movie picker](./12-movie-picker.md)).

**Paging**

- `Inertia::scroll()` over **cursor (keyset) pagination**, 48 per page (fills 2, 3, 4 and 6 columns evenly), with `<InfiniteScroll manual>` and a "Show more" button, as on Search. There's no automatic infinite scroll, so the footer with the TMDB notice stays reachable.
- Why keyset: a card removed optimistically is hidden on the client while the server list is unchanged ([Watchlist interactions](./13-watchlist-interactions.md)). Offset pagination would then skip as many Movies on the next page as were removed. A cursor encodes the last card's sort values, so it works even if that card has gone.
- Rejected: loading the whole list in one response and sorting on the client. It's simpler, but the payload and DOM grow without limit.
- Build note: Laravel's cursor pagination doesn't support null sort columns, and supports expressions only when they are aliased in the select. Each sort therefore uses a non-null key (for example `release_date` coalesced so undated Movies sort last) plus the id tiebreak. Removed Movies are kept out of the paginated query (below), so titles are never null in it.

**Removed Movies (dropped by TMDB)**

- They're gathered in a "No longer available" group above the grid: "TMDB has removed these movies, so we can't show their details." There are few of them, so the group isn't paginated and isn't sorted with the grid. The group is hidden when there are none.
- Each card is the missing-poster tile with "Removed movie" in place of a title and the user's own date: "Added {date}" on the Watchlist, "Watched {date}" on Watched. These dates are user data, not TMDB data.
- The card links to the Movie's 410 page ([Movie detail page](./24-movie-detail-page.md)). Its "⋯" menu has Remove (Watchlist) or Clear Watched (Watched), with the normal toast and Undo.
- Rejected: keeping the title when the Movie is dropped. That would hold TMDB-derived data indefinitely, against the purge rule in [Movie identity, URLs and local storage](./09-movie-identity-and-storage.md).
- They're excluded from the Movie picker (as already decided) but included in the header count.

**Card date lines** (matching the detail page)

- **Watchlist card:** no date line normally. A rewatch-queued Movie (also Watched) gets a quiet "Last watched {date}" line, replacing the "Watched" marker in [The user–movie lifecycle](./04-user-movie-lifecycle.md). There's no "Added {date}" line.
- **Watched card:** "Watched {date}", or "Last watched {date}" when the Movie is back on the Watchlist (its bookmark is filled too).
- Dates use the user's locale in short form ("12 Mar 2026"), with tabular numbers.
- The card actions are as decided in [Watchlist interactions](./13-watchlist-interactions.md): the floating bookmark, plus "⋯" with Mark as watched (Watchlist) or Clear Watched (Watched).

**Page header**

- The Watchlist / Watched switch, on every screen size.
- The title with a count ("Watchlist · 42"). The count includes Removed Movies and updates optimistically with removals and Undo.
- The sort menu.
- On the Watchlist only, "Pick for me", **hidden when the picker has no candidates**. This refines [Movie picker](./12-movie-picker.md)'s "hidden when the Watchlist is empty", because a Watchlist of only Removed Movies has no candidates either.

**Empty states**

| Page      | What shows                                                                                                                                                                                                                       |
| --------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Watchlist | "Your Watchlist is empty", then "Tap the bookmark on any movie to save it here." Below it, the cached "Trending this week" grid, using no extra TMDB call and hidden if unavailable, so a new user can start saving on the spot. |
| Watched   | "Nothing marked as watched yet", then "Mark a movie as watched from its page or your Watchlist.", with a link to the Watchlist. No suggestions grid.                                                                             |

When the last card is removed, the empty state appears and focus moves to its heading (as already decided in [Watchlist interactions](./13-watchlist-interactions.md)).

**Loading and navigation**

- The page arrives in one response, with no deferred props. A normal visit shows the progress bar, not a skeleton.
- Show more: a spinner in the button, and shimmer cards appended for the incoming page.
- Changing the sort: the current grid stays, slightly dimmed, until the new first page arrives.
- Back from a Movie restores the sort, the loaded pages and the scroll position.

**Guests and analytics**

- Both routes are behind `auth`. A guest following the Watchlist or Watched nav item goes to login and comes back afterwards through the standard intended URL. The nav items stay visible to guests. Both pages are `noindex` and not server-rendered ([Public pages](./14-public-pages-seo.md)).
- Cards link with the `watchlist` / `watched` referrer for `movie_viewed`. There are no new events.

**Glossary**

- Added **Removed Movie** (a Movie the Catalog source no longer has, which retires "shell" in new writing) and **Date added**.

**Follow-ups for the spec**

- A list layout.
- A rating sort.
- A genre filter on the lists.
