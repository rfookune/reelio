# 15: Save to the Watchlist

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** A signed-in user can add a Movie to their Watchlist from the Movie page in one tap, by catalog id, with instant feedback and rollback on failure. This sets up the action plumbing every list surface reuses. See the spec's "The user–movie lifecycle" and "Watchlist interactions" sections.

**Blocked by:** [05: Analytics event recording](./05-analytics-event-recording.md), [06: Open a Movie from its TMDB id](./06-open-a-movie-from-its-tmdb-id.md)

**Status:** ready-for-agent

- [ ] `user_movies` with a cascading `user_id`, restrictive `movie_id`, nullable `watchlisted_at` and `watched_at`, unique `(user_id, movie_id)`; a row with both empty is deleted
- [ ] `PUT`/`DELETE /movies/tmdb/{tmdbId}/watchlist` need `auth` (not `verified`), find or create the Movie row through `Catalog` exactly as the entry route does (sharing its 20/min creation limit), and are idempotent: re-adding keeps the original Date added; removing touches only the Watchlist
- [ ] Watchlist and Watched actions and Undo share a 60/min limit per user or IP
- [ ] A `userMovies` page prop keyed by catalog id; the Movie page toggle ("Add to Watchlist" / "On your Watchlist", `aria-pressed`, changing label) sends an `async` optimistic visit and partially reloads only `userMovies`
- [ ] A sonner toast ("Added to your Watchlist" / "Removed from your Watchlist"), ~6 s, pausing on hover or focus, announced politely; on failure the state rolls back with "Couldn't save that. Try again."
- [ ] Guests are sent to sign in (the pending-action flow comes later)
- [ ] `movie_watchlisted` / `movie_unwatchlisted` with `movie_id` and `source`
- [ ] Vitest covers the optimistic toggle and rollback; feature tests cover authorization, idempotency, row creation, the limits and events
