# 26: Refresh planner for Movies users hold

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Movies users hold stay fresh without anyone viewing them: Watchlist Movies every 3 days, Watched-only every 30, throttled well under TMDB's limits. See the spec's "Refresh, cache lifetimes, purge and the cap guard" section.

**Blocked by:** [14: Refresh on view, and Removed Movies](./14-refresh-on-view-and-removed-movies.md), [16: Mark as watched and Clear Watched](./16-mark-as-watched-and-clear-watched.md)

**Status:** ready-for-agent

- [ ] A planner job, dispatched from the 05:00 UTC slot, selects due Movies page by page, dispatches one unique job per Movie on `default`, and re-dispatches itself so no job runs over 90 s
- [ ] Due means: on any Watchlist and `synced_at` over 3 days old; Watched only and over 30 days old; Movies with no facts are never scheduled
- [ ] Each Movie job is one background-profile `movie()` refresh; all background TMDB calls share a 20 requests/s throttle
- [ ] The owner is alerted when a Movie with user facts is more than 14 days past its due refresh
- [ ] The `genres` table is refreshed weekly (Mondays, same slot)
- [ ] Feature tests cover due selection, uniqueness and continuation with the fake adapter and a faked queue

## Note from the Services layout (2026-11-06)

The planner and per-Movie jobs live in `app/Jobs/`; the refresh logic they call is `Catalog` in `app/Services/Catalog/`. The 3-day and 30-day due ages and the 20 requests/s background throttle belong in `config/catalog.php`.

## Note from ticket 14 (2026-11-06)

Each Movie job calls `Catalog::refresh($movie, new CatalogContext(config('catalog.language'), profile: CatalogProfile::Background))`. A 404 already makes it a Removed Movie there. Exclude Movies with `removed_at` set from due selection.
