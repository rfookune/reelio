# 10: External ratings: the TMDB rating

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** The Rating source seam exists and the Movie page shows the TMDB rating with its vote count, or "Not yet rated". IMDb plugs into the same seam next. See the spec's "Modules" (Rating source) section.

**Blocked by:** [07: Movie page layout and details](./07-movie-page-layout-and-details.md)

**Status:** resolved

- [x] A Rating source interface with one batch method `ratingsFor(Collection $movies)` returning an `ExternalRating` (source enum, 0–10 value, vote count, as-of) per Movie id; a missing rating is absent, never `N/A`, `0` or a placeholder; no blended score anywhere
- [x] A TMDB adapter reads `vote_average`/`vote_count` from the row, with zero votes counting as absent; an in-memory fake returns given ratings or none
- [x] A thin `Ratings` module returns ratings in the fixed order IMDb, then TMDB, in one batch for many Movies (no per-card lookups)
- [x] The Movie page hero shows each rating as the source name in its colour, the score and a compact vote count ("TMDB 8.5 41.8K"), or "Not yet rated"
- [x] A shared rating display for cards (IMDb, else TMDB, else "Not yet rated") is ready for the list surfaces

## Deviation (2026-11-06)

Per [ADR 0001](../../../docs/adr/0001-one-ratings-table-for-every-rating-source.md), TMDB's score no longer lives on the Movie row. The "TMDB adapter reads `vote_average`/`vote_count` from the row" criterion is superseded: a generic stored adapter reads TMDB's score from the `ratings` table by `tmdb_id`, the Catalog records it when it creates a Movie, and the `vote_average`/`vote_count` columns are dropped from `movies` without copying their values: each Movie's TMDB score is recorded again the next time the Catalog fetches it. Zero votes still count as absent, now by never storing the row.

## Note from the Services layout (2026-11-06)

The `Ratings` module now lives in `app/Services/Ratings/` (sources and the fake in `Sources/`). `Ratings` must not depend on `Catalog`; the Catalog records TMDB's score through `RatingStore`. `tests/Unit/ArchitectureTest.php` enforces it.
