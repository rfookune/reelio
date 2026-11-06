# 17: Undo

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Every Watchlist and Watched action can be undone from its toast, restoring exactly the earlier state, as long as nothing has changed since. See the spec's Undo bullets.

**Blocked by:** [16: Mark as watched and Clear Watched](./16-mark-as-watched-and-clear-watched.md)

**Status:** resolved

- [x] Each action stores the Movie's previous state (both facts and dates, by catalog id) in the session; only the latest action is kept
- [x] An Undo endpoint (path set at build) restores the snapshot only if the current state still matches what the action produced, including restoring no row at all or re-creating a purged Movie row by catalog id; otherwise it fails with "That can't be undone any more"; the client never supplies dates
- [x] Toasts offer a keyboard-reachable Undo; Undo restores focus to the restored control
- [x] `user_movie_action_undone` with `movie_id` and `action`; the original event stays
- [x] Browser tests cover the toast Undo flow; feature tests cover restore, mismatch and the previous Watched date

## Note from the build (2026-11-06)

- Undo is `POST /movies/tmdb/{tmdbId}/undo` (`movies.undo`) with an `action` (`add_to_watchlist`, `remove_from_watchlist`, `mark_as_watched`, `clear_watched`) in the shared `auth` and `user-movie-actions` group. The request names the toast's Movie and action, so an older toast's Undo is refused unless it names the same Movie and action as the latest. Then it undoes the latest, which on the same Movie restores the same facts the older toast would have, apart from a Date added set in between. The session keeps `{user, catalog id, action, before, after, remembered_at}` (`App\Http\LatestUserMovieAction`) for 30 minutes, the spec's retention for the Undo snapshot. The facts are the timestamps `UserMovie::change()` returns, and the client sends no dates.
- Every Watchlist and Watched response is remembered, including ones that changed nothing (such as a `DELETE` on a catalog id with no Movie row), so an older snapshot never outlives the toast that replaced it. Undoing a no-op changes nothing and records no event, matching 15's "an event only when something changes". `user_movie_action_undone` is likewise recorded only when Undo changes something.
- `UserMovie::restore()` compares the locked row to `after` and writes `before` within `change()`'s transaction. A successful or mismatched Undo forgets the snapshot. A stale toast's Undo, or a Catalog failure while re-creating a purged row (404, 429, 503), leaves it in place, so the latest action stays undoable and a failed re-creation can be retried.
- With no Movie row, Undo creates one through the Catalog (`findOrCreate`, under the creation limit) only if the action left no facts and there are facts to restore. Otherwise a missing row means the facts stand at none. Undo also restores facts on a Removed Movie: it puts back what was there instead of setting a new fact, so Remove followed by Undo works on Removed Movie cards.
- Two async actions in flight write the session in whatever order they finish, so the snapshot kept can be the earlier one's. The later toast's Undo is then refused, and the earlier toast's Undo works only while that Movie's facts stand where its action left them. The compare under the row lock keeps the facts correct either way.
- Client: the success toast's Undo sends an optimistic, `async` visit that shows the pre-action entry. A refusal shows the server's message and reloads `userMovies`, because Inertia's rollback would otherwise restore the stale post-action entry over the server's answer. Other failures show the generic line. Action functions now take the pressed control (`$event.currentTarget`). Undo finds it again by `data-slot` inside the Movie's `data-tmdb-id` container. It waits up to 60 frames for the optimistic render, then falls back to the container's first button. List cards (22, 23) need `data-tmdb-id` on the card and a `data-slot` on each control. The "Last watched · Clear" button is now `data-slot="clear-last-watched"`.
- vue-sonner 2.0.9 (the latest) doesn't support its Alt+T hotkey: it focuses the list, and an effect then sends focus straight back to the element that had it. The Toaster wrapper now handles Alt+T itself. It focuses the front toast's action (Undo), else the toast, and Escape inside the toaster returns focus. While a toast is showing, the wrapper's capture-phase listener takes Alt+T even in a text field, which on macOS would otherwise type "†". Tab still reaches toasts, but only after the footer, because the Toaster comes last in the DOM. A one-off check confirmed a toast focused this way stays paused past its 6 s; that check isn't kept as a test.
- `RecordEvent` queued its job in a terminating callback, and the app keeps those after `terminate()`, so a test making several requests (or any long-lived app) re-queued earlier events on each request. Each callback now queues at most once.
