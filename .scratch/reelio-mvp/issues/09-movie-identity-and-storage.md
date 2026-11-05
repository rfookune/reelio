# Movie identity, URLs and local storage

Type: grilling
Status: resolved
Blocked by: 01, 02, 03
Map: [Reelio MVP](../map.md)

## Question

How is a Movie identified, addressed, and stored in Reelio?

- Public URL shape for `/movies/{movie}`: Catalog source id, local id, or slug, given that most visited Movies have never been saved by anyone, and given the PRD's "provider independence" principle.
- When a local Movie record is created (on first view, on first save or watch, or never) and what it holds vs what lives only in cache.
- Where External ratings (TMDB's own, and IMDb's from the daily non-commercial dataset import) and Availability are kept (cache vs table), within the limits the research tickets found. Note that the IMDb dataset covers every title, not just Movies Reelio has seen.
- Fields the picker and watchlist sorting will need locally (genres, runtime, release date).

## Comments

- From [The user–movie lifecycle](./04-user-movie-lifecycle.md) (resolved): User facts (Watchlist, Watched) must outlive catalog data: a Movie dropped or merged by the Catalog source, or purged from cache, must not delete them. See [The user–movie lifecycle](./04-user-movie-lifecycle.md).

## Answer

**Identity and URLs.** A Movie is addressed by its local id: `/movies/{slug}-{local-id}`, with the slug decorative (a wrong or stale slug 301-redirects to the canonical URL, which carries a canonical tag). Lists, search and homepage sections link to an entry URL by catalog id (e.g. `/movies/tmdb/603`). The entry route asks TMDB to confirm the id exists, finds or creates the row, then 301-redirects. It is rate-limited and creates a row only after TMDB confirms the id, so ids cannot be enumerated into rows. Listings, search and homepage sections come from cache only; only opening a Movie creates a row. (Supersedes the first proposal, a row only on save and TMDB-id URLs, rejected by the human: local-id URLs need the row to exist first.)

**The movies row** (all TMDB-derived, governed by one `metadata_synced_at`):

- Identity: local `id`, unique `tmdb_id` (plain column, not a generic provider pair: the adapter is TMDB-specific and a Catalog source change would be a data migration anyway), slug derived from the title and regenerated on refresh.
- Metadata: `title`, `original_title`, `overview`, `release_date`, `runtime`, `poster_path`, `backdrop_path`, `original_language`, `vote_average`, `vote_count`, `imdb_id`.
- Genres in a `genres` table with a `genre_movie` pivot, so the picker and sorting can filter on them.
- Bookkeeping: `metadata_synced_at`, `last_viewed_at`.
- **Cache only (Redis):** cast, Director, ranked trailer, certification. Needed only on the detail page; one TMDB call refills them after a flush.

**IMDb ratings.** The daily import lands the whole `title.ratings.tsv` dataset in its own table keyed by IMDb id, with its own `imported_at`. The detail page reads a Movie's rating through its `imdb_id` at read time; there is no copy on the movies row (a Movie created today would otherwise show no rating until the next import). Not subject to TMDB's purge cap. No IMDb API is used (see [OMDb ratings: coverage, terms and limits](./03-omdb-ratings.md)). A denormalised column for sorting can be added if a Watchlist sort by IMDb rating is ever required.

**Availability is stored in Postgres**, queryable for the P1 filter by Streaming service (the filter itself is not designed):

- `streaming_services`: TMDB provider id, name, logo path, fetch timestamp.
- `availabilities`: one row per Movie, Streaming service, country and offer type (subscription, rental, purchase). Not a plain pivot: country and type belong to the relationship.
- **Supported countries only.** TMDB returns around 120 countries per Movie; only the countries Reelio supports are stored ([Country and availability](./11-country-and-availability.md) sets them).
- Replaced wholesale per Movie on a short refresh interval (the cache-lifetimes patch sets it). The per-country TMDB watch-page link and the JustWatch credit still apply.

**The user–movie table (`user_movies`).**

- `user_id` with a cascading foreign key (account deletion removes everything).
- `movie_id` with a restrictive foreign key: a Movie row is never deleted while any user has a fact about it.
- `watchlisted_at` and `watched_at`, both nullable. They replace the PRD's `is_watchlisted` boolean; no `personal_rating` until ratings exist.
- Unique `(user_id, movie_id)`.
- A row with both timestamps empty is deleted; Undo re-creates the exact earlier state, including no row at all.

**Lifecycle of a Movie row.**

- A row with no `user_movies` entries and not viewed for a set period is purged by a scheduled job. The period (well inside the 6-month cap) is decided in the cache-lifetimes patch. Waiting out that period means an Undo right after a removal always finds the row.
- Rows with user facts are refreshed before the 6-month cap.
- **Dropped or merged by TMDB.** A refresh that gets a 404 keeps the row, clears every TMDB-derived field, its genre rows and its Availability rows, and marks it unavailable. A Watchlist or Watched entry then shows "no longer available from our data source"; the user can still remove it or clear Watched, and Undo works. The public URL returns 410. Merges are not detected: a dropped id becomes a shell and the surviving id is a separate Movie.
- **Licence loss.** The same clearing applies to every row; user facts survive.

**Carry into the spec.**

- Crawler traffic now creates rows, so the entry route's rate limit and the unviewed-row purge are hard requirements, not tuning.
- [Public pages and SEO](./14-public-pages-seo.md) inherits local-id URLs with canonical redirects; its sitemap can list only existing rows.
- [Source boundary](./10-source-boundary.md) inherits the IMDb table as a Rating source that needs no Movie row to be read.

- From [Search experience](./23-search-experience.md) (resolved): rows are also created on first save. The Watchlist and Watched endpoints are addressed by catalog id and find or create the row through `Catalog`, so "only opening a Movie creates a row" no longer holds. A row created by a save has never been viewed, so the purge clock needs a start: stamp `last_viewed_at` (or a created timestamp the purge reads) when the row is created. Undo by catalog id re-creates a purged row, so Undo no longer depends on the purge period.
