# 08: Mark Episodes as watched

**What to build:** A signed-in user can record Watched progress on the TV Show page. Build the Season accordion (each Season collapsible with 'n of m watched', a progress bar and the default-open rule), compact Episode rows (number, Name, air date, runtime, a round toggle with `aria-pressed`, the overview behind a disclosure), the Specials section (last, collapsed, labelled), and the unaired and undated rules. The Episode toggle marks and unmarks with the derived TV Show state (Not started, In progress, Completed) and the header progress; it is optimistic with rollback and a message on failure. Marking again moves the Watched date to now. A guest's tap becomes their pending action and is replayed after sign-in. Undo (one endpoint by TV Show TMDB id) restores the exact prior Episode state, refusing with 'That can't be undone any more' when things changed.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 06 TV Show page content

**Status:** resolved

- [x] Nested Episode endpoints key by local ids, with scoped bindings 404ing an Episode outside its Season or TV Show and removed ones; unaired and undated Episodes are rejected
- [x] The derived state ignores Specials, unaired, undated and removed Episodes
- [x] The header shows 'n of m Episodes watched' with a progress bar
- [x] The default open Season follows the spec (first with an unwatched aired Episode, else last aired; Season 1 for Not started)
- [x] A guest's Episode action replays after sign-in and nothing is saved before
- [x] Undo restores exactly and refuses when state has changed
- [x] Browser tests cover the optimistic toggle with rollback on a failing endpoint

## Comments

### 2026-11-08 — Implemented

- Added scoped local-id Episode mark/unmark endpoints, Episode-level Watched dates, rewatch dates, and eligibility checks behind the TV Shows flag. Derived progress excludes Specials and ineligible or removed inventory; completion removes the Watchlist fact only on transition.
- Added the Season accordion, compact Episode rows with spoiler disclosures, Specials last, upcoming labels, header progress, and optimistic toggles with rollback and an error message.
- Guest taps use the existing latest-action session lifecycle and replay once after sign-in. The TV Show Undo endpoint checks a user-bound token and the complete earlier/expected Episode and Watchlist snapshots before restoring dates exactly.
- Validation: 826 unit/feature tests, 164 browser tests, 27 frontend tests, production build, frontend lint/format/types, Pint and PHPStan passed. The review-requested validation-message assertions also pass in the affected Episode test file.
- Standards review found one validation-message coverage gap, corrected; Spec review found no gaps. Bulk actions, TV Show Watchlist controls, lists, analytics and refresh work remain assigned to later tickets.
