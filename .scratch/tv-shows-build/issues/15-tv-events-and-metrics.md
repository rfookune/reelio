# 15: TV events and metrics

**What to build:** Record events for TV Show actions and report them. Movie events keep their names and meaning. Add the TV events with their properties as listed in the spec's Events and metrics section (viewed, watchlisted and unwatchlisted, Episode, Season and whole-TV Show marks and unmarks with local Season and Episode ids and counts, completed, undone), make search `title_searched` (result count across both kinds) and the Watch-link event kind-neutral with `title_id`, `kind` and country. No view event on the Removed Title 410 or the cap-cleared 503, and none with the flag off. `metrics:report` counts both kinds in weekly returning and search-to-watchlist and gets a TV Shows block. The roll-up is unchanged.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 07 Where to watch and ratings for TV Shows, 10 Season and whole-TV Show bulk actions, 11 Search with TV Shows

**Status:** resolved

- [x] Each TV action records exactly one event with the documented properties; whole-TV Show events carry only a count
- [x] Season and Episode ids are plain integers with no foreign key
- [x] Guest replays record on replay; no events on the 410, the 503 or with the flag off
- [x] `title_searched` and the kind-neutral Watch-link event replace the old names, with Movie tests updated accordingly
- [x] `metrics:report` shows both kinds in the existing metrics and the new TV Shows block
- [x] The 13-month roll-up handles the new event names without change

## Comments

### 2026-11-08 — Implemented

- Added signed-in TV Show view, Watchlist, Episode, Season, whole-TV Show, completion and Undo events through the existing after-response recorder. Episode and Season events use plain local ids; bulk actions record one event with the number of Episodes changed, and whole-TV Show events contain no Season or Episode ids. Guest actions record only when replayed. Removed Title 410s, cap-cleared 503s, partial reloads and disabled TV features do not record view events.
- Search records `title_searched`, counting the results actually shown on page 1 across both enabled kinds. Watch-link clicks record `availability_link_clicked` with `title_id`, `kind` and `country`. Other Movie event names and meanings are preserved.
- TV links retain safe view referrers through entry and canonical redirects. Watchlist and Watched card actions carry their actual source through the frontend, server action and guest replay.
- `metrics:report` includes both kinds in activation, weekly returning and search-to-watchlist conversion. Its TV Shows block reports Watchlist additions, users marking Episodes, Episode marks per marking user (including bulk marks), and completions. The 13-month roll-up still counts actions by day and event name, without retaining or summing TV Episode properties.
- Renamed `UserMovieAction` to `UserTitleAction` and shared it across Movie, TV Show, Season and Episode Undo actions. Added `AnalyticsEvent` for event recording, metrics queries and roll-up event references. Session and stored analytics string values are unchanged.
- Standards review found no violations; spec review's source-attribution finding was fixed and rechecked. A nonblocking extraction opportunity in the existing completion logic remains outside this ticket.
- Implementation committed as `96f0d66`. The enum refactor is implemented and verified in the working tree, not yet committed.
- Validation: the implementation passed 969 unit/feature tests, all 176 browser tests, 31 frontend tests, build, frontend lint/formatting, TypeScript, PHPStan, Pint and both dependency audits. After the enum refactor, all 969 unit/feature tests, 24 affected browser tests, TypeScript, PHPStan and Pint passed.
