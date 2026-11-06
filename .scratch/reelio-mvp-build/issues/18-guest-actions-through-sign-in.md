# 18: Guest actions through sign-in

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** A guest who presses a Watchlist or Watched control is taken to sign in or register, and the action completes on the page they were on, without waiting for email verification. See the spec's "Guest intent through sign-in" bullets.

**Blocked by:** [15: Save to the Watchlist](./15-save-to-the-watchlist.md)

**Status:** resolved

- [x] A guest using a control stores a single-use pending action `{catalog id, action}` in the session (30-minute expiry, latest only), counted in the shared action limit
- [x] The intended URL is the page the session last recorded on a page load, never a header or request parameter; one guard accepts only relative paths starting with a single `/` (not `//` or `/\`), else Discover
- [x] After login or registration the pending action is applied idempotently, the user lands back on that page, and the normal toast shows
- [x] No GET request changes data, and the pending action never travels in a URL
- [x] Feature tests cover login and registration completion, expiry, single use, and the guard rejecting `//evil.com` and `/\evil.com`

## Note from the build (2026-11-06)

- The four Watchlist and Watched routes swap `auth` for `StorePendingGuestAction::for(<action>)`, after `throttle:user-movie-actions`, so a guest's store counts in the shared 60/min limit by IP. (`auth` there would be sorted before the throttle by middleware priority.) A signed-in request goes through. A guest's request is validated (`source`, catalog id) and stored as `{catalog id, action, source, stored_at}` under `user_movie_actions.pending`, and the guest is redirected to `/login` with no query. No Movie row is created. All four actions can be pending, so a stale signed-in page used after logging out elsewhere still completes. `source` is kept for the action's analytics event. Undo stays `auth`.
- Laravel's `_previous.url` skips XHR, so it misses every Inertia visit, and `url()->previous()` reads `Referer` first. `RecordGuestPage` (web group) records a guest's own page loads, full or Inertia, as `guest.last_page`: GET, not a prefetch, a 200 HTML or Inertia response, and not a `guest`-only route (login, register, 2FA, password reset). `IntendedUrl::setToLastPage()` runs it through the one guard into `url.intended`. The guard accepts `^/(?![/\\])` with no whitespace or control characters, anchored with `D`; anything else gives Discover. Fortify's stock `redirect()->intended()` then lands every sign-in path.
- The `ApplyPendingGuestAction` listener on `Login` pulls the pending action (single use, 30 minutes) and applies it through `UserMovieActionByCatalogId`, which the controllers now share, so it's idempotent and remembered for Undo. It covers password login, registration (before verification) and the 2FA challenge. Passkey sign-in goes through the same `guard->login()` and `intended()` (read in vendor, not tested). A `Login` on a GET (a remember-me cookie) applies nothing. A failure (404, 410, 429, 503, or anything unexpected, which is reported) still signs the user in, with the generic error toast. The 429 wording is left to 28, like the client's own action toasts.
- The applied action is flashed as `userMovieAction` `{tmdbId, action, previous}`. `initializeAppliedActionToast()` shows the normal success toast with Undo; Undo's focus falls back to the Movie's first control.
- `url.intended` isn't cleared when a pending action expires, so a guest who signs in later from the nav, in the same session, still returns to the page they pressed the control on.
