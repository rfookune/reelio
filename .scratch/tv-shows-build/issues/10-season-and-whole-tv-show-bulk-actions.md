# 10: Season and whole-TV Show bulk actions

**What to build:** Add bulk marking. Mark and clear a whole Season (skipping unaired, undated and already-watched Episodes so earlier dates stay), mark a whole TV Show (aired Episodes only) through a modal confirm dialog that names the number of aired Episodes (focus trapped and returned, Escape and Cancel change nothing), and a Clear for the whole TV Show (the Completed chip's control). Completion removes the TV Show from the Watchlist and it never returns on its own. Each bulk action is exactly one Undo, restoring the affected Episodes' previous Watched dates plus the Watchlist fact, with no size cap. Whole-TV Show mark with no Episodes stored first tries a refresh and changes nothing if that fails. A guest's bulk action is stored as the pending action (the confirm dialog shows first).

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 08 Mark Episodes as watched, 09 Watchlist for TV Shows

**Status:** resolved

- [x] Season mark and clear change only eligible Episodes; earlier Watched dates are kept
- [x] The whole-TV Show mark requires the confirm dialog naming the aired Episode count
- [x] Becoming Completed removes the TV Show from the Watchlist; later new Episodes do not re-add it
- [x] One Undo restores Episode marks and Watchlist membership exactly, and fails when state has changed
- [x] Whole-TV Show mark with no Episodes stored refreshes first and changes nothing on failure
- [x] Guest replay works for Season and whole-TV Show actions
- [x] Browser tests cover the Undo toast after a Season bulk action and the dialog's focus behaviour

## Comments

### 2026-11-08 — Implemented

- Added Season mark/clear (`/tv-shows/{tvShow}/seasons/{season}/watched`) and whole-TV Show mark/clear (`/tv-shows/tmdb/{tmdbId}/watched`) through `ChangeBulkWatched`. Marks touch only aired, non-Removed, not-yet-watched Episodes, so earlier Watched dates stay. The whole-TV Show mark covers numbered Seasons (matching the Completed rule); its Clear removes every mark, Specials included. Becoming Completed removes the Watchlist fact; nothing re-adds it.
- Each bulk action is one Undo via the existing latest-action snapshot (Episode dates plus Watchlist), with no size cap (chunked inserts). A whole-TV Show mark with no Episodes stored refreshes first and returns 503 without changes if that fails.
- Guests: Season and whole-TV Show actions are stored as the pending action and replayed after sign-in; the whole-TV Show dialog shows before storing. A Removed TV Show gives 410 (also refused at store time for a guest's whole-TV Show mark).
- UI: Season mark/clear buttons, "Mark as watched" in the header with a modal confirm dialog naming the aired Episode count, and Clear on the Completed chip. Browser tests cover the Undo toast after a Season mark and the dialog's focus trap, Escape, Cancel and focus return.
- Validation: 1040 feature/browser tests, 27 frontend tests, type check, production build, frontend lint/format and Pint passed. PHPStan was not run.
- Known limitations: a bulk call with nothing to change still returns an Undo token and toast; a refresh yielding only Specials or unaired Episodes marks nothing but still toasts "marked"; the pending guest action remains an `isset` ladder, and the `episodeAction` flash key is reused for bulk actions. The Watchlist card menu "Mark as watched" belongs to a later ticket.
