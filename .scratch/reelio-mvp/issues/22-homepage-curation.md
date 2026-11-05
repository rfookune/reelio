# Homepage curation

Type: grilling
Status: resolved
Map: [Reelio MVP](../map.md)

## Question

What exactly does Discover show, and in what order?

- Which sections appear (trending, popular, top rated, upcoming, genre collections) and how many Movies each holds.
- Which genres get a collection, and whether the set is fixed or rotates.
- Which Movie the hero features and how often it changes.
- Whether Movies are deduplicated across sections (the PRD only requires it within a section).
- How the Upcoming row deals with `release_date` values outside the stated range ([Verify TMDB behaviours with a live key](./17-tmdb-live-checks.md)).
- Trending is global, not per country ([Country and Availability presentation](./11-country-and-availability.md)); page sizes are capped at 500.

## Answer

**Sections, in order** (10 Movies each, no "See all" pages in the MVP; Search covers depth, and browsing by filters is P1, FR-003):

| # | Section | Source |
| --- | --- | --- |
| 0 | Hero | The highest-ranked Trending Movie with a backdrop and an overview. Changes once a day. The "Watch trailer" button only appears when a trailer exists. |
| 1 | Trending this week (numbered) | `/trending/movie/week` (global, not per country) |
| 2 | Popular | `/movie/popular` |
| 3 | Coming soon | `discover` with `region=US`, release types theatrical and digital, US release dates from tomorrow to 60 days out. Each Movie's `release_dates` gives the **US date** shown on the row ("Opens 21 Nov"). Movies whose US date is past or missing are dropped. That's 10 extra calls per refresh, cached. This works around `/movie/upcoming` returning out-of-range `release_date`s ([Verify TMDB behaviours with a live key](./17-tmdb-live-checks.md)). |
| 4 | Top rated | `/movie/top_rated` |
| 5–7 | Three genre collections | Picked each day, deterministically from the date, from a curated config list: Action, Comedy, Drama, Science Fiction, Horror, Animation, Thriller, Romance. Each uses `discover` sorted by popularity with at least 300 votes. |

**Rules**
- **Deduplicated across sections:** a Movie appears only in the earliest section in page order. Later sections fill up to 10 from their next TMDB page. The hero is the one exception, so Trending keeps its true ranks.
- **Quality:** every query passes `include_adult=false`. Movies with no poster are left out of Discover sections; the FR-001 placeholder is still used elsewhere.
- **Same for everyone:** assembled on the server once per refresh (the lifetime is set by [Cache lifetimes and refresh schedule](./26-cache-lifetimes-and-refresh.md)). No personalisation and no country dependence; only bookmark states (`userMovies`, [Watchlist interactions](./13-watchlist-interactions.md)) differ per user.
- **Failure states:** a section whose source fails with no stale copy is hidden quietly. If every section fails, the page shows "We can't reach our movie data right now" with Retry, and Search stays reachable. A stale section shows normally, with no per-row notice.

- From [Search experience](./23-search-experience.md) (resolved): Discover's bookmark toggles use the catalog-id Watchlist endpoints, which create the Movie row on first save, and `userMovies` is keyed by catalog id.

- From [Cache lifetimes and refresh schedule](./26-cache-lifetimes-and-refresh.md) (resolved): the Discover payload is rebuilt once a day by a job dispatched at 05:00 UTC, so the daily hero and genre rotation change at that time. Visitors only read the payload, and the first request builds it if it's missing. A failed section keeps its previous version as `stale` for up to 7 days, then is hidden.
