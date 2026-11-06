# 08: Catalog resilience and failure states

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** When TMDB is slow, failing or rate-limiting, `Catalog` returns explicit `fresh`, `stale` or `unavailable` results instead of exceptions, and the Movie page degrades gracefully. See the spec's "Catalog: failures, timeouts and cache keys" section.

**Blocked by:** [07: Movie page layout and details](./07-movie-page-layout-and-details.md)

**Status:** resolved

- [x] Interactive (3 s, one retry) and background (10 s, three attempts with jittered backoff) profiles; only connection errors, 5xx and 429 retry, with 429 honouring `Retry-After` capped at 2 s
- [x] A 404 is returned as an answer (for later Removed Movie handling); a 401 raises an immediate owner-visible error
- [x] A circuit breaker fails interactive calls immediately for 30 s after five consecutive failures
- [x] All live TMDB calls share a 15 requests/s throttle in the cache store; over it, `Catalog` returns `stale` or `unavailable` without waiting
- [x] On a source failure an expired cache entry is served as `stale`
- [x] Movie page with a row but the source unavailable: stored fields render; Cast shows a "temporarily unavailable" panel; Director, Writers and Rated read "Temporarily unavailable"; the certification badge is hidden
- [x] The entry route with no row and the source down shows a 503 page
- [x] Adapter tests (fixtures) cover retries, timeouts, 429, 404, 401 and the breaker; feature tests drive every state through the failing fake

## Note from the Services layout (2026-11-06)

The thresholds above (five failures, 30 s open, 15 requests/s) are now `guard.*` keys in `config/catalog.php`, and the detail-cache freshness and lifetime are `details.*`. The profiles' timeouts and attempts stay on `CatalogProfile`. The breaker is `GuardedCatalogSource` in `app/Services/Catalog/Sources/`.
