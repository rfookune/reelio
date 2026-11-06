# 14: Refresh on view, and Removed Movies

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** A Movie page view refreshes data more than a day old before rendering, and a Movie TMDB has dropped becomes a Removed Movie whose page is a title-less 410. See the spec's "Refresh, cache lifetimes, purge and the cap guard" and "Movie identity and URLs" sections.

**Blocked by:** [08: Catalog resilience and failure states](./08-catalog-resilience-and-failure-states.md), [12: Where to watch on the Movie page](./12-where-to-watch-on-the-movie-page.md)

**Status:** ready-for-agent

- [ ] One `movie()` refresh updates the row, genres, Availability, referenced Streaming services (upserted) and the detail cache, and sets `synced_at`
- [ ] A view with `synced_at` over 24 h old refreshes in the request (interactive profile); on failure the page renders stored data as `stale` (Where to watch shows "may be out of date")
- [ ] A 404 on refresh keeps the row, clears every TMDB-derived field, its genre links and Availability, and marks it removed; user facts are untouched
- [ ] A Removed Movie's page is a 410 for everyone with `noindex`, no title, and the copy "This movie is no longer available. Our data source, TMDB, has removed this movie, so we can't show its details any more.", linking to Discover (list actions arrive with the lists)
- [ ] Feature tests drive refresh, stale fallback and removal through the fake adapter

## Note from ADR 0001 (2026-11-06)

Per [ADR 0001](../../../docs/adr/0001-one-ratings-table-for-every-rating-source.md), TMDB's score lives in the `ratings` table, not on the Movie row. The refresh operation records the Movie's TMDB rating (or deletes it when the score is missing or has zero votes), and making a Movie a Removed Movie deletes its TMDB rating along with the other TMDB-derived fields.

## Note from ticket 12 (2026-11-06)

- Availability is written only where `synced_at` is set, so "Updated N days ago" stays truthful. The refresh operation should call `AvailabilityWriter::replace()` in the same step that moves `synced_at`; the `Catalog::details()` refetch deliberately doesn't touch Availability.
- Where to watch already shows "may be out of date" when `Catalog::details()` is served `stale`, as well as past 7 calendar days since `synced_at`. When the real in-request refresh lands, a failed refresh should feed the same flag (spec, Movie page States).

## Note from the Services layout (2026-11-06)

Put the refresh and removal logic in `app/Services/Catalog/` and keep it free of HTTP: `Catalog` reports a Removed Movie as a result, and the controller turns it into the 410, as `DetailsUnavailable` (in `app/Http/Exceptions/`) does for the 503. A new exception for the Catalog goes in `app/Services/Catalog/Exceptions/`, and a new tunable (such as the 24 h refresh age) in `config/catalog.php`. `App\Services` must not import `App\Http` (`tests/Unit/ArchitectureTest.php`).
