# 15: Save to the Watchlist

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** A signed-in user can add a Movie to their Watchlist from the Movie page in one tap, by catalog id, with instant feedback and rollback on failure. This sets up the action plumbing every list surface reuses. See the spec's "The user–movie lifecycle" and "Watchlist interactions" sections.

**Blocked by:** [04: Browser test harness and the sign-in journey](./04-browser-tests-and-sign-in-journey.md), [05: Analytics event recording](./05-analytics-event-recording.md), [06: Open a Movie from its TMDB id](./06-open-a-movie-from-its-tmdb-id.md)

**Status:** resolved

- [x] `user_movies` with a cascading `user_id`, restrictive `movie_id`, nullable `watchlisted_at` and `watched_at`, unique `(user_id, movie_id)`; a row with both empty is deleted
- [x] `PUT`/`DELETE /movies/tmdb/{tmdbId}/watchlist` need `auth` (not `verified`), find or create the Movie row through `Catalog` exactly as the entry route does (sharing its 20/min creation limit), and are idempotent: re-adding keeps the original Date added; removing touches only the Watchlist
- [x] Watchlist and Watched actions and Undo share a 60/min limit per user or IP
- [x] A `userMovies` page prop keyed by catalog id; the Movie page toggle ("Add to Watchlist" / "On your Watchlist", `aria-pressed`, changing label) sends an `async` optimistic visit and partially reloads only `userMovies`
- [x] A sonner toast ("Added to your Watchlist" / "Removed from your Watchlist"), ~6 s, pausing on hover or focus, announced politely; on failure the state rolls back with "Couldn't save that. Try again."
- [x] Guests are sent to sign in (the pending-action flow comes later)
- [x] `movie_watchlisted` / `movie_unwatchlisted` with `movie_id` and `source`
- [x] Browser tests cover the optimistic toggle and rollback; feature tests cover authorization, idempotency, row creation, the limits and events

## Note from the build (2026-11-06)

- `DELETE` on a catalog id with no Movie row is a no-op redirect: it doesn't find or create the row. Such a Movie can't hold facts, so creating it would only cost a TMDB call and a creation slot. `PUT` finds or creates through `Catalog` as the entry route does, under the same 20/min limit (both go through `App\Http\MovieByCatalogId`). The spec's "The catalog-id endpoints find or create the Movie row" wording needs updating if this stands.
- Events are recorded only when the Watchlist actually changes. Re-adding a Movie already on the Watchlist, or removing one that isn't, records nothing. The spec's "one event per action" is read this way.
- `PUT` on a Removed Movie is a 410; `DELETE` still works, matching the Removed Movie cards, which offer only Remove.
- The label follows this ticket and the Movie page table ("On your Watchlist"). The spec's Watchlist interactions section says "Remove from Watchlist" for toggles; list surfaces should settle which they use.
- `source` is required and validated against a fixed list (`discover`, `search`, `movie`, `watchlist`, `watched`, `picker`). Ticket 18's pending action is `{catalog id, action}` with no source, so it needs to carry one or pick a default.
- Adding is one conditional upsert and removing locks the row, so each action is atomic and a concurrent one is never lost. Async actions still run in whatever order the server receives them, so two quick clicks can leave the database disagreeing with the last click.
- The limit key (user id, else IP) lives in `App\Http\RateLimitKey`, shared by the action limit and the creation limit. The action limit is the named limiter `user-movie-actions`; 16 and 17 attach to it. `auth` runs before it, so guests aren't counted yet (18).
- `MovieController::show` now loads the props that need the Catalog details lazily, so the `userMovies` reload after an action never refreshes the Movie (a refresh finding it removed would otherwise 410 a save that had succeeded).
- Sonner doesn't pause on focus, so the Toaster wrapper reports focus inside it as hover. This isn't tested. A 429 shows the generic failure toast until 28 adds its line.
