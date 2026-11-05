# 11: IMDb ratings import and display

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Each day Reelio imports IMDb's non-commercial ratings dataset safely, and the Movie page shows the IMDb rating with IMDb's credit next to the TMDB rating. See the spec's "IMDb import" and "Scheduler" sections.

**Blocked by:** [10: External ratings: the TMDB rating](./10-external-ratings-tmdb.md)

**Status:** ready-for-agent

- [ ] An IMDb ratings table keyed by IMDb id (rating, vote count, `imported_at`), loaded through a staging table
- [ ] A daily job on the `imports` queue, dispatched from the single 05:00 UTC scheduler slot this ticket creates; it skips the download when `ETag`/`Last-Modified` is unchanged, streams the gzipped TSV without loading it into memory, validates (header matches, malformed rows skipped and counted, row count at least 90% of the live table's) and swaps in one transaction; any failure leaves the live table untouched; the download URL is config; the job timeout is generous and the queue's `retry_after` longer than it
- [ ] The owner is alerted (log error now, Nightwatch later) when an import fails or the live table is more than 3 days old
- [ ] An IMDb Rating source adapter joins through `imdb_id`; no `imdb_id` or no entry means absent, and there's never a title-search fallback; ratings from it appear wherever the `Ratings` module is used
- [ ] The Movie page shows "IMDb 8.7 2.4M" before TMDB, with IMDb's credit under it (the wording requested is "Information courtesy of IMDb (https://www.imdb.com). Used with permission."; final wording per the map's IMDb permission ticket); turning the IMDb source off moves nothing else on the page
- [ ] Import tests use a tiny gzipped TSV fixture: a normal row, a missing rating, a malformed row, a header mismatch, a low row count and an unchanged file
- [ ] The import's real duration is measured against Flex's 90 s limit and recorded on this ticket (it decides whether `imports` moves to Pro)
