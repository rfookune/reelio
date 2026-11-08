# 13: Removed TV Shows

**What to build:** Handle a TV Show TMDB has removed, as a Removed Title. Its page is a title-less 410 for everyone (`noindex`), sharing one error page and exception with the Removed Movie (noun swapped; the Removed Movie behaviour is unchanged), linking to Discover and Your Watchlist when signed in. A signed-in user with facts sees 'It's still in your lists' with Remove and Clear (all Watched Episodes, one Undo) and 'You marked {n} Episodes as watched', with no Season or Episode names. A Removed TV Show is never fetched again, and every action that would set a fact (including a replayed guest action) gives the 410 or the existing sign-in message. In the lists it joins the 'No longer available' group (heading 'TMDB has removed these, so we can't show their details.') as a missing-poster stub with 'Removed TV show' and the user's own date, with only Remove (Watchlist) or Clear Watched (Watched), no state or progress, included in filter and counts. A visit does not update `last_viewed_at`.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 12 Watchlist and Watched with TV Shows

**Status:** resolved

- [x] The 410 page, copy and links match the spec for visitors and signed-in users with facts
- [x] Remove and Clear work from the page and the stubs, each with Undo
- [x] Setting a fact on a Removed TV Show is refused; guest replay shows the existing message
- [x] Stubs appear in the group with the right date and menu, and count in the filter
- [x] `last_viewed_at` is not updated on the 410; the Removed Movie page and tests are unchanged
- [x] The cap-cleared 503 page behaves as for a cap-cleared Movie

## Comments

### 2026-11-08 — Notes from ticket 12

- The list classes were renamed there: `UserListPage`, `UserListController`, `UserList`, `ListSort`, `UserList.vue`, `UserListCardMenu.vue`, and the page prop `movies` is now `titles`. `unavailableMovies` is still Movie-only; making it `unavailableTitles` belongs here, with the stubs.
- A TV Show without details is excluded from the cards, `userTvShows` and the counts (`UserListPage::entries()`, `userTvShows()`); the stubs need to be added to all three so the filter counts include them.

### 2026-11-08 — Implemented

- Implemented the shared 410 and cap-cleared 503 pages, viewer-only cleanup facts, list stubs, filter counts, Remove and Clear with Undo, and refused fact-setting actions. Removed TV Shows are never fetched again and visits do not update `last_viewed_at`.
- Implementation committed in `a2de9e7`. Standards and spec reviews cleared after fixing viewer facts on stale-action 410 responses and Movie Undo when a TV Show shares its TMDB id.
- Renamed the shared exception to `TitleRemoved` at the user's request, including PHP callers and the frontend exception allowlist. Renamed the Movie test files to `RemovedTitlesTest.php` and `RemovedTitlesJourneyTest.php`; TV-specific coverage remains separate. Movie behavior is preserved; the shared list-heading expectation follows the spec.
- Full implementation validation passed: 920 unit/feature tests, 176 browser tests, 31 frontend tests, lint, typechecking, build, and PHPStan. After the rename, 73 affected PHP tests and 10 browser tests passed, along with lint, typechecking, build, and PHPStan.
