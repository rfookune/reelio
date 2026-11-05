# Catalog and Rating source boundary

Type: grilling
Status: resolved
Blocked by: 02, 03, 09
Map: [Reelio MVP](../map.md)

## Question

What is the shape of the module(s) that isolate Reelio from TMDB (the Catalog source) and IMDb's non-commercial ratings dataset (a Rating source)?

- Interface and data objects (the PRD's `MovieProvider` is illustrative only).
- One abstraction or separate Catalog source and Rating source seams.
- Timeouts, bounded retries, and how source failures surface as explicit states to pages.
- How the daily IMDb dataset import fits (a scheduled bulk download, unlike TMDB's on-demand API), and how a missing IMDb rating is represented.
- Where caching sits and how cache keys incorporate country and language.
- How the boundary is faked in tests.

Use the `codebase-design` skill.

## Comments

- From [Test strategy and tooling](./07-test-strategy.md) (resolved): this ticket must deliver an in-memory fake of each source interface alongside it, configurable to return given Movies or to fail; feature and browser tests use the fakes and never see TMDB's JSON. The TMDB adapter itself is tested with `Http::fake()` and recorded fixtures; the IMDb import with a tiny gzipped TSV fixture.

- From [Movie identity, URLs and local storage](./09-movie-identity-and-storage.md) (resolved): Movies are stored in Postgres (a row on first view); IMDb ratings sit in their own dataset table keyed by IMDb id and are read through `imdb_id`, so a Rating source must be readable without a Movie row. Availability is stored in `availabilities` for supported countries only.

## Answer

**Two seams, nothing shared.** The Catalog source and the Rating source are separate interfaces. Each seam has two real adapters (production plus an in-memory fake), which is what makes it a real seam. Swapping TMDB touches no Rating code; adding Rotten Tomatoes touches no Catalog code. Namespaces `App\Catalog` and `App\Ratings` (the first new folders under `app/`; approved by this decision).

**Catalog source.**

- **Adapter interface (small, neutral):** `search(query, page)`, `section(kind, page)` (trending, popular, top rated, upcoming, genre), `movie(tmdbId)` returning one `MovieDetails` object, and `genres()`. A `CatalogContext` carries language and region.
- **The TMDB adapter translates.** It returns neutral objects: the Director and the ranked trailer are decided inside the adapter, and cast, certification and Availability (service, country, offer type) are neutral too. Nothing outside the adapter sees TMDB's JSON or the word "provider". It is tested with `Http::fake()` and recorded fixtures.
- **A deep `Catalog` module sits in front of the adapter** and is the only thing controllers and Actions call. It owns the entry-route find-or-create of the Movie row, cache reads and writes, refresh and purge of stored rows, clamping the 500-page cap, deduplicating by id, and turning source failures into explicit states. Controllers never touch the adapter or the cache.
- **In-memory fake adapter:** can be given Movies and sections, and can be told to fail (per call, or for every call), so every failure state is testable. Feature and browser tests bind it in the container.

**Rating source.**

- **Interface:** one batch method, `ratingsFor(Collection $movies)`, returning an `ExternalRating` per Movie id. `ExternalRating` holds the source (an enum), the value on a 0–10 scale, the vote count and an as-of timestamp.
- **A missing rating is absent from the result.** Never `"N/A"`, never `0`, never a placeholder object. Pages show a source's rating when present and nothing when not.
- **TMDB adapter:** reads `vote_average` from the Movie row; zero votes counts as absent (a brand-new Movie must not show "0.0").
- **IMDb adapter:** joins the dataset table through `imdb_id`; no `imdb_id` or no dataset entry means absent.
- **A thin `Ratings` module** collects the adapters and returns External ratings in a fixed display order. The batch signature avoids one lookup per card on the Watchlist and Watched grids.
- **In-memory fake:** returns given ratings per Movie, or none.

**IMDb import.**

- A scheduled job streams the gzipped `title.ratings.tsv` without loading it into memory and writes to a staging table.
- It validates before swapping: the header matches, malformed rows are skipped and counted, and the row count is at least 90% of the live table's.
- It then swaps staging for the live table in one transaction. If any step fails, the live table is untouched and keeps serving the last good data; the failure goes to Nightwatch and the owner alert ([Analytics and error monitoring](./08-analytics-and-monitoring.md)), and the next run tries again.
- The download URL is config. Job timeout and the queue's `retry_after` follow [Infrastructure baseline](./05-infrastructure-baseline.md). Cadence is the cache-lifetimes patch's. Tested against a tiny gzipped TSV fixture (a normal row, a missing rating, a malformed row).

**Timeouts, retries and failure states.**

| Profile | Timeout | Attempts | Used for |
| --- | --- | --- | --- |
| Interactive | 3 s | one retry | anything a visitor waits on |
| Background | 10 s | three, jittered backoff | refresh and cleanup jobs |

- **Retried:** connection errors, 5xx and 429 only (a 429 honours `Retry-After`, capped at 2 s). A 404 is an answer: it means a dropped Movie ([Movie identity, URLs and local storage](./09-movie-identity-and-storage.md)). A 401 is a configuration error and alerts immediately.
- **Circuit breaker:** after five consecutive interactive failures, calls fail immediately for 30 s, so an outage does not tie up every web worker for seconds per request.
- **Explicit states:** `Catalog` returns a result that is `fresh`, `stale` or `unavailable`, never an exception. When the source fails and an expired cache entry exists it is served as `stale`, within TMDB's 6-month cap.
- **Pages:**
  - Each homepage section fails on its own, with an error card and a retry.
  - Search shows an inline error and keeps the query.
  - A detail page for a Movie with a row renders from the row, with "temporarily unavailable" panels for whatever came from the source.
  - The entry route with no row and the source down shows a 503 page.
  - The Watchlist and Watched pages read only Postgres, so a TMDB outage never affects them.

**Cache keys.** All `Catalog` keys are built in one place: `catalog:v1:{kind}:{params-hash}:{language}:{region}`.

- Language comes from config (`en-US` for the MVP) and is in every key and adapter call.
- Region is in the key only for calls where TMDB uses it (popular, top rated, upcoming); the trending key never carries one. Whether the homepage passes a region at all belongs to the homepage curation patch.
- The user's country never appears in a key (Availability is in Postgres), so one cached payload serves every country.
- `v1` lets a bad payload shape be invalidated by bumping one constant.

**Carry into the spec.**

- The cache-lifetimes patch must set the TTL per kind, the purge period for unviewed rows, and the cadence of the import, and must say how long a `stale` entry may be served.
- The rate-limiting patch inherits the circuit breaker and the entry-route limit from [Movie identity, URLs and local storage](./09-movie-identity-and-storage.md); it should treat them as one outbound-protection story.

- From [Deployment and operations](./19-deployment-and-operations.md) (resolved): the IMDb import runs on a Flex managed queue, where jobs are killed at 90 seconds. The staging table and swap keep a killed run harmless; measure the real duration at build and move the `imports` queue to Pro if it doesn't fit.
