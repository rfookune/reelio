# 09: Watchlist for TV Shows

**What to build:** A user can add a TV Show to the Watchlist and remove it from the TV Show page header, with the three state variants of the header actions (Not started, In progress, Completed show the right controls). Endpoints mirror the Movie ones keyed by TMDB id, so a card with no row yet works: they create the row when needed, share the Movie auth, throttle and pending-guest-action rules, and offer Undo. Re-adding a Completed TV Show sets a new Date added and leaves Episode marks untouched.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 06 TV Show page content

**Status:** resolved

- [x] Add and remove work from the page header and by TMDB id for a TV Show with no row
- [x] A guest's add is stored as a pending action and replayed after sign-in
- [x] Undo restores the Watchlist fact exactly
- [x] Re-adding a Completed TV Show sets a new Date added and keeps Episode marks
- [x] A Removed TV Show cannot be added (covered fully by the Removed Title ticket)

## Comments

### 2026-11-08 — Implemented

- Added the header Watchlist toggle for Not started, In progress and Completed TV Shows. Re-adding a Completed TV Show sets a new Date added without changing Episode marks; an existing Watchlist entry keeps its original date.
- Added flag-gated add/remove endpoints by TMDB id, creating TV Shows through the Catalog only when needed. They share the Movie creation budget and action throttle, validate their source, reject Removed TV Shows on add, and isolate users and kinds.
- TV Show Watchlist guest actions use the existing latest-pending-action session lifecycle and replay once after sign-in with an Undo toast. Expired, removed, disabled and unavailable cases preserve sign-in without saving facts.
- Shared Episode/Watchlist Undo restores exact server-owned snapshots and refuses stale tokens or changed facts. It preserves unchanged Episode records and can recreate a purged TV Show through the existing catalog rules before restoring its earlier Date added.
- Confirmed saves update Inertia history so browser Back preserves the header state and the next tap removes the saved entry. Optimistic controls roll back with a message on failure, and completion/Undo keep the header toggle in sync.
- Validation: 852 unit/feature tests, 168 browser tests and 27 frontend tests passed, along with the production build, frontend lint/format/types, Pint and PHPStan. Standards review found no violations; the Spec review's history-state finding was reproduced, fixed and re-reviewed successfully. The kind-specific resolver's shared error-mapping shape remains consistent with the Movie convention.
- Whole-TV Show Mark/Clear controls and the confirmation dialog belong to ticket 10. Normalized one extra blank line in ticket 08's record to satisfy the repository formatter.
