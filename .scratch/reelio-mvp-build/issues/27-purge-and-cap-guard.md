# 27: Purge and the cap guard

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Reelio keeps no TMDB data past TMDB's limits: unviewed Movies nobody holds are purged, a 170-day guard clears anything that couldn't be refreshed, and the owner can purge everything if the licence ends. See the spec's purge and cap-guard bullets.

**Blocked by:** [24: Removed Movies in lists and on the 410 page](./24-removed-movies-in-lists.md), [26: Refresh planner for Movies users hold](./26-refresh-planner.md)

**Status:** ready-for-agent

- [ ] A daily cleanup deletes Movies with no user facts and `last_viewed_at` over `catalog.purge_unviewed.after_days` (default 30) old, with their genre links, Availability and detail cache, behind `catalog.purge_unviewed.enabled` (env `CATALOG_PURGE_UNVIEWED`, on by default)
- [ ] The same cleanup deletes Streaming services no Availability references
- [ ] At 170 days without a successful sync, any Movie's TMDB-derived fields are cleared (not marked removed); it shows as "Details temporarily unavailable" in the "No longer available" group, is excluded from the picker, sitemap and paged lists, and is restored by the next successful refresh
- [ ] A cap-cleared Movie's page tries the in-request refresh; on failure it's a 503 with `Retry-After` and `noindex`, "Details temporarily unavailable", and Remove/Clear for signed-in users with facts; never a 410
- [ ] `catalog:purge` clears TMDB-derived data from every row while keeping user facts
- [ ] Feature tests cover the purge flag, the 30-day window, the cap guard and restore, and the command

## Note from ADR 0001 (2026-11-06)

Per [ADR 0001](../../../docs/adr/0001-one-ratings-table-for-every-rating-source.md), TMDB scores live in the `ratings` table (`source = tmdb`), with no foreign key to `movies`. The purge deletes a purged Movie's TMDB rating; the daily cleanup also deletes TMDB rating rows with `as_of` more than 170 days old, whether or not a Movie row exists (Search and Discover record scores for titles with no Movie); `catalog:purge` deletes every TMDB rating row. IMDb rows are not TMDB data and are untouched.

## Note from the Services layout (2026-11-06)

The purge and cap-guard logic goes in `app/Services/Catalog/`, and `catalog:purge` is a console command. The 170-day cap and the `catalog.purge_unviewed.*` keys it specifies live in `config/catalog.php`. The cap-guard 503 reuses `DetailsUnavailable` from `app/Http/Exceptions/`, thrown by the controller, not by the Catalog.

## Note from ticket 14 (2026-11-06)

`Catalog::markRemoved()` already clears every TMDB-derived column through the same list the refresh writes (`clearedSourceAttributes()`). It also detaches genres, deletes Availability, forgets the detail cache and deletes the TMDB rating with `RatingStore::forget()`, which works even while the source is switched off. The cap guard's clearing can share that, minus setting `removed_at`. A Removed Movie's page records no view, so its `last_viewed_at` ages normally for the purge.
