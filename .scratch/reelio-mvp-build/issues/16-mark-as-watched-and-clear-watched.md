# 16: Mark as watched and Clear Watched

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** A user can mark a Movie as watched and clear that record, with every lifecycle rule (including rewatches) and the Movie page showing the right controls for each state. See the spec's lifecycle table and the Movie page actions table.

**Blocked by:** [15: Save to the Watchlist](./15-save-to-the-watchlist.md)

**Status:** ready-for-agent

- [ ] `PUT`/`DELETE /movies/tmdb/{tmdbId}/watched` (same plumbing, limits and row creation as the Watchlist endpoints)
- [ ] Mark as watched sets the Watched date to now and removes the Movie from the Watchlist; on a Watched Movie that's on the Watchlist it moves the date (a rewatch); on a Watched Movie not on the Watchlist it changes nothing; Clear Watched leaves the Watchlist alone
- [ ] The Movie page shows the four action states from the spec's table, including the "Watched {date}" chip with Clear (×) and the quiet "Last watched {date} · Clear" line for a queued rewatch
- [ ] Toasts "Marked as watched" / "Cleared from Watched" with optimistic rollback
- [ ] `movie_marked_watched` / `movie_unmarked_watched` with `movie_id`, `source` and `was_on_watchlist`; marking as watched records no Watchlist removal event
- [ ] Feature tests cover every row of the lifecycle table
