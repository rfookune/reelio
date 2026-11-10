# 03: Rating survives Watchlist, Watched, undo, Removed Titles and purge

**What to build:** A user's rating is independent of everything else they do with a Title. Removing a Title from the Watchlist, clearing Watched, or undoing either never touches the rating; a Removed Title keeps it; and purge and cap paths never delete a Title someone has rated. See [the spec](../spec.md).

**Blocked by:** 02: Set, change and clear a User rating.

**Status:** resolved

- [x] Removing a Title from the Watchlist keeps the rating
- [x] Clearing Watched keeps the rating
- [x] Undoing a Watchlist or Watched change leaves the rating as it is
- [x] Restoring never deletes a facts row that holds only a rating, and never changes the rating
- [x] A Title that becomes a Removed Title keeps each user's rating
- [x] Purge and cap paths do not delete a Title that any user has rated
- [x] Feature tests cover each case above, in the style of the existing undo, Removed Titles and cap tests

## Comments

- The existing shared facts lifecycle, undo snapshots and Catalog cleanup paths already preserve User ratings; no production changes were needed. Added 37 regression cases across the existing HTTP, cleanup-job and purge-command feature tests.
- Coverage includes Movie and TV Show Watchlist removal; Watched changes and undo at Movie, Episode, Season and TV Show scope; changing or clearing ratings between an action and undo; each user retaining their rating after Catalog removal; and rating-only Titles surviving daily purge, cap clearing and `catalog:purge`.
- `composer ci:check` passes with 1,048 PHP tests, plus frontend lint/formatting, TypeScript, PHP formatting and PHP static analysis. All 43 JavaScript tests and 224 browser tests pass. Standards and Spec reviews found no issues.
