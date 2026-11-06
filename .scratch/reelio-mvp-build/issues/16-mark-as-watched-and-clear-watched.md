# 16: Mark as watched and Clear Watched

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** A user can mark a Movie as watched and clear that record, with every lifecycle rule (including rewatches) and the Movie page showing the right controls for each state. See the spec's lifecycle table and the Movie page actions table.

**Blocked by:** [15: Save to the Watchlist](./15-save-to-the-watchlist.md)

**Status:** resolved

- [x] `PUT`/`DELETE /movies/tmdb/{tmdbId}/watched` (same plumbing, limits and row creation as the Watchlist endpoints)
- [x] Mark as watched sets the Watched date to now and removes the Movie from the Watchlist; on a Watched Movie that's on the Watchlist it moves the date (a rewatch); on a Watched Movie not on the Watchlist it changes nothing; Clear Watched leaves the Watchlist alone
- [x] The Movie page shows the four action states from the spec's table, including the "Watched {date}" chip with Clear (×) and the quiet "Last watched {date} · Clear" line for a queued rewatch
- [x] Toasts "Marked as watched" / "Cleared from Watched" with optimistic rollback
- [x] `movie_marked_watched` / `movie_unmarked_watched` with `movie_id`, `source` and `was_on_watchlist`; marking as watched records no Watchlist removal event
- [x] Feature tests cover every row of the lifecycle table

## Note from the build (2026-11-06)

- All four actions (add, remove, mark as watched, clear Watched) run on `UserMovie::change()`. It locks the row, or inserts an empty one and locks that, applies the change, then saves the row or deletes it when it holds neither fact. It returns the facts before and after as timestamps, so an action records its event only when they differ. Undo (17) can take its snapshot from the same before-state. A clear on a Movie with no row inserts an empty row and deletes it in the same transaction: an extra write on a no-op path.
- `PUT /watched` finds or creates the Movie as the Watchlist endpoints do, and is a 410 for a Removed Movie (`MovieByCatalogId::findOrCreateUnremoved()`). `DELETE /watched` with no Movie row is a no-op, as in 15. Both share the `user-movie-actions` limit with the Watchlist endpoints.
- `was_on_watchlist` is the Watchlist state before marking as watched, and the current (unchanged) Watchlist state when clearing.
- `userMovies` entries now carry `watchedDate`, a UTC timestamp (named for the glossary's Watched date). Dates show as "12 Sep 2026", built from `en-US` parts because `en-GB` writes "Sept". The server renders them in UTC; once mounted, the page shows them in the browser's time zone, so "today" is the user's today. The spec asks for the user's locale; the form is fixed English instead, which avoids hydration mismatches.
- On the Movie page, when the control that had focus is replaced (Mark as watched becomes the chip, or Clear removes it), focus moves to "Mark as watched", else the chip's Clear. Browsers that don't focus buttons on click (Safari, Firefox on macOS) leave focus where it was; only Chromium is tested, and a rollback's focus isn't.
- The certification badge in the hero has `data-slot="certification"`; `MovieLayoutTest` now looks for that instead of `.border-control`, which the outline "Mark as watched" button also matches.
