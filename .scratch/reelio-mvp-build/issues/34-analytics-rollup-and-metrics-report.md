# 34: Analytics roll-up and metrics:report

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** The owner can report the beta's success metrics from one command, and events older than 13 months are reduced to anonymous counts. See the spec's "Data model" (events) and "Analytics and monitoring" sections.

**Blocked by:** [05: Analytics event recording](./05-analytics-event-recording.md)

**Status:** resolved

- [x] A daily job (05:00 UTC slot) rolls events older than 13 months into an aggregate table (one row per day and event name with a count; `movie_picker_used` also keeps its `pool_size` total) and deletes them
- [x] `metrics:report` runs documented SQL over both tables, with each metric defined in one place (e.g. weekly returning users, search-to-watchlist conversion, picker usage), counting distinct users searching per day and reporting no zero-result rate
- [x] Feature tests cover the roll-up boundary and the report's definitions on seeded events
