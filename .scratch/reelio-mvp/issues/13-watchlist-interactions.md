# Watchlist interactions

Type: grilling
Status: resolved
Blocked by: 04
Map: [Reelio MVP](../map.md)

## Question

How do watchlist and watched actions behave in the UI?

- Optimistic updates and rollback on failure (Inertia v3 optimistic updates vs `useHttp`).
- Which surfaces carry the actions (movie card, detail page, picker).
- How an unauthenticated user's intended action (e.g. "add to watchlist") is preserved through sign-in or registration and completed afterwards (Journey A step 6).
- What feedback the user sees on success or failure.

## Comments

- From [The user–movie lifecycle](./04-user-movie-lifecycle.md) (resolved): Settled actions and rules are in [The user–movie lifecycle](./04-user-movie-lifecycle.md). The UI must support Undo that restores the exact earlier state (including the previous Watched date); marking as watched takes a Movie off the Watchlist.
- From [Visual language and app shell](./06-visual-language-and-shell.md) (resolved): the card's Watchlist action is a bookmark toggle floating on the poster (filled with the accent when on the Watchlist); Discover rows carry a bookmark toggle at the end of the row, and the hero has an "Add to Watchlist" button. The prototype showed feedback as a toast with Undo; that pattern is still this ticket's call.
- From [Movie picker](./12-movie-picker.md) (resolved): the picker dialog carries View details and Pick again. Decide here whether it also carries Mark as watched or Remove, and what happens to the dialog when one is used.

## Answer

**How actions are sent**
- Every page carries one `userMovies` prop mapping each Movie id on the page to `{watchlisted, watched}`, and every surface reads from it, so a Movie shown twice (the hero and a row) stays consistent.
- Actions are `async` Inertia router visits with an `optimistic` callback that updates one `userMovies` entry, then a partial reload of `userMovies` only. The server stays the source of truth, and there's no client store. `async` matters: a synchronous visit would interrupt one in flight and roll back its optimistic state.
- Endpoints are idempotent sets, never toggles: `PUT`/`DELETE /movies/{movie}/watchlist` and `PUT`/`DELETE /movies/{movie}/watched`, with Undo as one more endpoint (below). Double-clicks, retries and concurrent visits settle on the state last asked for.
- Watchlist and Watched actions need `auth` only, not `verified`, so a new registrant's pending action completes straight away. Verification still guards account settings and email-dependent features.

**Which surfaces carry which actions**
| Surface | Actions |
| --- | --- |
| Discover rows, hero | Watchlist toggle only |
| Watchlist cards | Watchlist toggle (the floating bookmark); "⋯" menu with Mark as watched |
| Watched cards | Watchlist toggle; "⋯" menu with Clear Watched |
| Detail page | Watchlist toggle; Mark as watched, or "Watched on {date}" with Clear Watched once the Movie is Watched and not on the Watchlist |
| Movie picker | View details, Pick again, Mark as watched (no Remove). Mark as watched closes the dialog, the card leaves optimistically, and a toast with Undo appears. Undo restores the card but doesn't reopen the dialog. |

A rewatch is Add to Watchlist, then Mark as watched.

**Feedback**
- Every action shows a sonner toast ("Added to your Watchlist", "Marked as watched", …) with **Undo**. It lasts about 6 s, pauses on hover or focus, is announced through a polite live region, and Undo is reachable by keyboard.
- On failure the optimistic state rolls back and an error toast says "Couldn't save that. Try again." There is no automatic retry.
- Toggles use `aria-pressed` and a label that changes ("Add to Watchlist" / "Remove from Watchlist").
- When a card leaves the Watchlist page, focus moves to the same control on the next card, else the previous card, else the empty-state heading. If Undo restores the card, focus returns to it.

**Undo**
- Each action stores the previous state of that Movie (both facts and their dates) in the session. Only the user's latest action is kept.
- The Undo endpoint restores the snapshot only if the Movie's current state still matches what the action produced. Otherwise it fails with "That can't be undone any more". The client never supplies dates.
- Analytics: Undo records a new event, `user_movie_action_undone` with `{movie_id, action}`, and leaves the original event in place. This adds to the event list in [Analytics and error monitoring](./08-analytics-and-monitoring.md).

**Guest intent through sign-in (Journey A step 6)**
- Guests see the same controls. Using one stores a pending action `{movie_id, action}` in the session (single-use, 30-minute expiry, only the latest kept), sets the intended URL to the current page, and sends the guest to login.
- After login *or* registration, the pending action is applied idempotently, the user lands back on that page, and the normal toast shows.
- The pending action never travels in a URL, so no GET request ever changes data.

- From [Search experience](./23-search-experience.md) (resolved): the Watchlist and Watched endpoints are addressed by catalog id (`PUT`/`DELETE /movies/tmdb/{tmdbId}/watchlist` and `/watched`), finding or creating the Movie row through `Catalog` as the entry route does, so a Movie with no row can be saved from any list. `userMovies` is keyed by catalog id; Undo snapshots and the guest pending action carry the catalog id. Search joins the surfaces table with the Watchlist toggle only.
