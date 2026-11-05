# Cache lifetimes and refresh schedule

Type: grilling
Status: resolved
Map: [Reelio MVP](../map.md)

## Question

What are the concrete lifetimes and schedules for TMDB- and IMDb-derived data?

- Cache TTL per data kind (lists, search, detail-only data, configuration) and how long a `stale` entry may be served ([Catalog and Rating source boundary](./10-source-boundary.md)).
- Metadata refresh cadence for stored Movies within TMDB's 6-month cap, with a faster cadence for rows that carry user facts.
- The Availability refresh interval, and the purge period for unviewed Movie rows ([Movie identity, URLs and local storage](./09-movie-identity-and-storage.md)), including rows created by a save from a list and never viewed ([Search experience](./23-search-experience.md)).
- The IMDb import time of day, and how refresh work is chunked into jobs under 90 seconds on Flex managed queues ([Deployment and operations](./19-deployment-and-operations.md)).
- What the freshness line on Availability shows ([Country and Availability presentation](./11-country-and-availability.md)).

## Answer

The framing constraints:
- **TMDB's terms:** nothing TMDB-derived may be kept more than 6 months, in Postgres or in the cache ([TMDB terms, attribution and caching limits](./01-tmdb-terms.md)), and Reelio should fetch on demand rather than crawl, at about 40 requests per second.
- **Laravel Cloud:** the scheduler is daily or hourly only and only dispatches jobs, and Flex queue jobs are killed at 90 s ([Deployment and operations](./19-deployment-and-operations.md)).

**One refresh operation per Movie**

- One `movie()` call refreshes everything for a Movie:
    - the row's metadata and genres;
    - its Availability for the supported countries;
    - the Streaming services it references (upserted);
    - the detail-only cache (cast, crew, trailer, certification).
- It sets **one** sync timestamp, `synced_at`, renaming `metadata_synced_at` from [Movie identity, URLs and local storage](./09-movie-identity-and-storage.md). The Availability freshness line reads it too. Separate cadences for metadata and Availability would double the calls for data that arrives in the same response.
- A 404 makes it a Removed Movie, as already decided.

**When a Movie is refreshed**

| Trigger | Rule |
| --- | --- |
| A detail page view | If `synced_at` is more than **24 h** old, refresh **during the request** with the interactive profile (3 s, one retry), then render. On failure, render the stored data as `stale`. |
| On at least one Watchlist | The planner refreshes it every **3 days**, because the Movie picker shows its stored Stream/Free Availability. |
| Watched only (on no Watchlist) | Every **30 days**. |
| No user facts | Never by schedule; purged instead (below). |

- Rejected for views: stale-while-revalidate (render the stored data, queue a refresh). It's faster on the first view each day, but shows possibly weeks-old Availability and turns web traffic into queued jobs.
- Only Movies users hold are refreshed in bulk, which keeps this within TMDB's "fetch on demand" guidance. At beta scale that's about a thousand calls a day.

**Refresh jobs**

- A planner job selects due Movies page by page and dispatches **one job per Movie**. Each job is unique per Movie, so duplicates collapse. The planner re-dispatches itself to continue, so it stays under 90 s.
- A Movie job makes one call with the background profile: at worst 3 attempts × 10 s plus backoff, about 35 s, safely under the 90 s limit.
- All background TMDB calls share a **20 requests per second** throttle in Valkey, half of TMDB's limit, leaving room for visitor traffic.
- Rejected: chunk jobs of several Movies. TMDB has no batch movie endpoint, so chunking saves no TMDB calls, and its worst-case time grows with the chunk size.

**Purge of unviewed Movies**

- The daily cleanup deletes a Movie with no user facts whose `last_viewed_at` is more than **30 days** old, together with its genre links, Availability rows and detail cache.
- `last_viewed_at` is set when the row is created (including by a save from a list) and on every detail view.
- Undo by catalog id re-creates a purged row, so there's no grace period for Undo.
- **Controlled by config:** `catalog.purge_unviewed.enabled` (env `CATALOG_PURGE_UNVIEWED`), **on by default in every environment**, and `catalog.purge_unviewed.after_days` (default 30).
- With the purge off, the `movies` table grows with every crawler- or visitor-created row. [Movie identity, URLs and local storage](./09-movie-identity-and-storage.md) made the purge a hard requirement for this reason, so turning it off is an accepted, temporary risk. The cap guard (below) still keeps every row within TMDB's cap.
- The same cleanup deletes Streaming services that no Availability row references any more, so no service row ages past the cap.

**Cache lifetimes**

| Data | Lifetime | Served as `stale` on failure |
| --- | --- | --- |
| Discover payload | Rebuilt **once a day** by a scheduled job; built on the spot by the first request if missing (a flush or first deploy) | Each section keeps its previous version for up to **7 days**, then is hidden ([Homepage curation](./22-homepage-curation.md)) |
| Search (per query and page) | **1 hour** | up to **24 hours**, then the "We can't search right now" error |
| Detail-only data (per Movie) | **30 days** hard, rewritten on every refresh | until that hard expiry; if missing, the detail page fetches it during the request |

- The Discover payload holds the hero, the five sections, the three rotated genre collections and Coming soon's `release_dates` lookups, fetched concurrently. Visitors only read it. The daily hero and genre rotation stay stable all day because they live in one payload. Search's "Trending this week" empty state reads it too.
- No cache entry lives more than 30 days, which is well inside the 6-month cap.

**TMDB reference data**

- **Genres:** the `genres` table is refreshed **weekly** from TMDB's genre list.
- **Image base URL:** set in config (`https://image.tmdb.org/t/p/`), not fetched from `/configuration`.

**The cap guard**

- The owner is alerted when any Movie with user facts is more than **14 days** past its due refresh. Movies without user facts have no due date and never alert.
- At **170 days** without a successful sync, **any** Movie's TMDB-derived fields are cleared, whether or not the purge is on. This is the same clearing as for licence loss, but the Movie is **not** marked removed. It shows as "Details temporarily unavailable", with the same treatment and in the same "No longer available" group as a Removed Movie ([Watchlist and Watched pages](./25-watchlist-and-watched-pages.md)). The next successful refresh restores it.
- `catalog:purge`, run by hand, applies the same clearing to every row at once if the licence is lost.

**The IMDb import**

- It runs daily and skips the download when IMDb's file is unchanged (`ETag` / `Last-Modified`). The 6-month cap doesn't apply because this isn't TMDB data.
- Ratings stay on show however old the table is. The owner is alerted when the live table is more than **3 days** old (repeated failures).
- The 90 s Flex limit and the move to Pro if it doesn't fit are already decided ([Deployment and operations](./19-deployment-and-operations.md)).

**Schedule**

- Every scheduled task fires in one daily slot at **05:00 UTC** and only dispatches jobs:
    - the IMDb import;
    - the Discover build;
    - the refresh planner;
    - the unviewed-row purge and service cleanup;
    - the sitemap build (while indexing is on);
    - the analytics roll-up.
- Weekly tasks (genres) run in the same slot on Mondays.
- There are no hourly tasks, so the app wakes once a day.

**Freshness line and stale notice**

- Every Availability block (the detail page and the Movie picker) shows "Updated today", "Updated yesterday" or "Updated N days ago", from `synced_at`.
- The "may be out of date" notice appears when that Availability is more than **7 days** old, which only happens when refreshes have been failing. It never depends on which code path served the data.

**Spec follow-up**

- Change-driven refresh using TMDB's changes feed (`/movie/changes`): skip refreshing Movies TMDB reports unchanged, if the feed covers Availability. It's an optimisation, not needed for the MVP.
