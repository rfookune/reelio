# 11: IMDb ratings import and display

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Each day Reelio imports IMDb's non-commercial ratings dataset safely, and the Movie page shows the IMDb rating with IMDb's credit next to the TMDB rating. See the spec's "IMDb import" and "Scheduler" sections.

**Blocked by:** [10: External ratings: the TMDB rating](./10-external-ratings-tmdb.md)

**Status:** ready-for-human

- [x] An IMDb ratings table keyed by IMDb id (rating, vote count, `imported_at`), loaded through a staging table
- [x] A daily job on the `imports` queue, dispatched from the single 05:00 UTC scheduler slot this ticket creates; it skips the download when `ETag`/`Last-Modified` is unchanged, streams the gzipped TSV without loading it into memory, validates (header matches, malformed rows skipped and counted, row count at least 90% of the live table's) and swaps in one transaction; any failure leaves the live table untouched; the download URL is config; the job timeout is generous and the queue's `retry_after` longer than it
- [x] The owner is alerted (log error now, Nightwatch later) when an import fails or the live table is more than 3 days old
- [x] An IMDb Rating source adapter joins through `imdb_id`; no `imdb_id` or no entry means absent, and there's never a title-search fallback; ratings from it appear wherever the `Ratings` module is used
- [x] The Movie page shows "IMDb 8.7 2.4M" before TMDB, with IMDb's credit under it (the wording requested is "Information courtesy of IMDb (https://www.imdb.com). Used with permission."; final wording per the map's IMDb permission ticket); turning the IMDb source off moves nothing else on the page
- [x] Import tests use a tiny gzipped TSV fixture: a normal row, a missing rating, a malformed row, a header mismatch, a low row count and an unchanged file
- [ ] The import's real duration is measured against Flex's 90 s limit and recorded on this ticket (it decides whether `imports` moves to Pro)

## Import duration

All local runs are on a developer machine against a local Postgres, with the real dataset (1,729,129 rows, 0 skipped) and 52.5 MiB peak memory: memory stays flat because the file streams to disk and is read line by line.

- **Rename-swap design (superseded, 2026-11-06):** 20.3 s.
- **Shared `ratings` table (ADR 0001, 2026-11-06):** 28.3 s into an empty source, 32.4 s replacing 1.7M existing rows (the daily case), with the whole load in one transaction. The delete-and-insert costs about 12 s over the rename swap.
- **Flex: not yet measured.** It needs a deployed `imports` queue on Laravel Cloud. A Flex worker with a remote database will be slower than the local 32.4 s; if a real run approaches 90 s, move `imports` to Pro. This is why the ticket is `ready-for-human`.

## Deploy checks

- The Cloud `imports` queue's `retry_after` (visibility timeout) must be longer than the job's 300 s timeout; it's set on Cloud, not in `config/queue.php`.
- The sync's real commit path (the temporary table dropped `ON COMMIT`) can't be exercised under `RefreshDatabase`, which never commits; confirm one real sync on Cloud succeeds twice in a row.
- The `ratings` table and the migration moving TMDB scores off `movies` have not been run on any shared database yet.

## Deviation (2026-11-06)

Per [ADR 0001](../../../docs/adr/0001-one-ratings-table-for-every-rating-source.md):

- The dedicated `imdb_ratings` and `imdb_ratings_staging` tables are replaced by the shared `ratings` table (`source = imdb`, `source_id` = the IMDb id). That migration was never run outside the test database, so it's replaced outright.
- There's no permanent staging table: each sync streams into a temporary table created for that run, validates it, then replaces the source's rows in one transaction.
- The IMDb-specific importer, job and freshness check become generic Rating dataset sync code; IMDb keeps only its feed (URL and TSV parser).
- The durations above are re-measured on the new design.
