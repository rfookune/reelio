# 14: TV refresh, purge and cap guard

**What to build:** Keep TV data fresh and within the TMDB 6-month cap. The planner schedules a unique refresh job per TV Show with user facts (a Watchlist entry or any Watched Episode): every 3 days while airing (Returning Series, In Production, Pilot), every 30 days when Ended or Cancelled. The daily cleanup deletes a TV Show with no user facts not viewed for 30 days, with its Seasons and Episodes. At 170 days without a successful sync every TMDB-derived column on the Title, its Seasons and its Episodes is cleared while rows, ids and TMDB ids stay; the next successful refresh restores them. A Season or Episode missing from a successful refresh is marked removed and restored if it reappears. `catalog:purge` clears Seasons and Episodes too. With the flag off, the planner skips TV Shows but the purge, cap guard and `catalog:purge` still run.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 08 Mark Episodes as watched, 09 Watchlist for TV Shows

**Status:** resolved

- [x] Refresh cadence follows TMDB status and only applies with user facts
- [x] Purge deletes unviewed fact-less TV Shows with their Seasons and Episodes
- [x] The cap guard clears TMDB data but keeps rows and Watched facts, and the next refresh restores everything
- [x] Vanished Seasons and Episodes are hidden, excluded from counts, keep facts and are restored if they return
- [x] `catalog:purge` and the cap guard clear TMDB rating rows of both kinds
- [x] With the flag off the purge and cap guard still run and the planner skips TV Shows

## Comments

### 2026-11-08 — Implemented

- Generalised the shared planner to `PlanTitleRefreshes`, with independent Movie and TV cursors and unique `RefreshTvShow` jobs using the background Catalog profile. TV cadence depends on TMDB status and requires a Watchlist fact or any Watched Episode; the feature flag disables TV planning and fetching.
- Extended daily cleanup and `catalog:purge` to TV Shows, including Seasons, Episodes, page data and kind-prefixed TMDB ratings. Cap clearing preserves row identities and user facts, and refresh restores the same inventory. Cleanup locks Titles and Episodes before the final facts check to protect concurrent Watched inserts.
- Covered vanished Seasons and Episodes returning with their original ids and Watched facts. Whole-TV Show marking now refreshes cap-cleared inventory before applying marks and changes no facts if the refresh fails.
- Standards and spec reviews cleared after fixing the concurrent Watched-insert race and cap-cleared whole-show marking. Regression tests reproduced both defects before the fixes and pass afterwards.
- Validation passed: 936 unit/feature tests, 176 browser tests, 31 frontend tests, PHPStan, full PHP formatting, TypeScript checks and frontend lint/formatting. Repository-wide `npm run check` reports an unrelated formatting issue in `.scratch/tv-shows/spec.md`, changed separately in `0249f9a`; that file is outside this ticket's changes.
