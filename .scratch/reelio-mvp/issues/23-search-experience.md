# Search experience

Type: grilling
Status: resolved
Map: [Reelio MVP](../map.md)

## Question

How does search behave (FR-002)?

- As-you-type suggestions vs a results page: TMDB prefix-matches the last word but ranks short queries badly (`inter` misses *Interstellar*), see [Verify TMDB behaviours with a live key](./17-tmdb-live-checks.md).
- Minimum query length, debounce, and what Enter does.
- Results layout, pagination (TMDB caps pages at 500), and the empty and error states, including when the Catalog is `stale` or `unavailable` ([Catalog and Rating source boundary](./10-source-boundary.md)).
- Whether the query lives in the URL (shareable, back-button safe). Search results are `noindex` ([Public pages](./14-public-pages-seo.md)), and the search text is never stored ([Analytics and error monitoring](./08-analytics-and-monitoring.md)).

## Answer

**One page with live results.**

- Search is its own page, `/search`, reached from the Search item in the desktop pill nav and the mobile bottom nav.
- The page has one input, and results appear below it as the user types. There is no suggestion dropdown in the top bar: short prefixes rank badly (`inter` misses *Interstellar*), so a top-5 dropdown would often hide the right Movie, while a full results list lets people scan or keep typing.
- The input is autofocused when the page opens with no query.
- Guests can search. The page is `noindex` and not server-rendered ([Public pages](./14-public-pages-seo.md)).

**Input behaviour.**

- Search starts at 2 characters, after a 300 ms debounce. Below 2 characters, the page shows its no-query state.
- Enter searches at once, skipping the debounce.
- Each query change replaces the browser history entry, so Back leaves Search rather than stepping through keystrokes. A newer request cancels the one in flight.

**The query in the URL.**

- The query is in `?q=` and the page number in `?page=`, so results can be shared, bookmarked and restored with Back.
- To keep the search text out of storage ([Analytics and error monitoring](./08-analytics-and-monitoring.md)), `q` is redacted from Laravel's request logging, from Nightwatch request URLs, and from Sentry URLs and breadcrumbs.
- Laravel Cloud's own edge access logs may still hold full URLs for their 7-day retention. That is checked at build, and if it holds it is stated on the privacy page.

**Results: the Watchlist and Watched poster card.**

- Results reuse the Watchlist and Watched card, in the same 2–6 column grid ([Visual language and app shell](./06-visual-language-and-shell.md)). Each card has:
    - the poster (or the missing-poster tile);
    - the floating bookmark toggle (Watchlist only);
    - the title, clamped to 2 lines;
    - the year (left off when there is no release date);
    - one External rating.
- The card has no "⋯" menu (so no Mark as watched) and no "Watched <date>" line.
- **The rating follows the card's rule with what is known.** Each results page already looks up existing Movie rows by catalog id (for `userMovies` and canonical links). A card whose Movie has a row with an IMDb rating shows IMDb. Every other card shows TMDB, or "Not yet rated" when TMDB has no votes. Search results carry no `imdb_id`, and fetching one per result is not worth up to 20 TMDB calls a page.
- Results come in TMDB's order with `include_adult=false`. There are no filters, year qualifiers or reranking (filters are P1, FR-003). Poster-less Movies are kept, unlike on Discover, because search is how people find obscure titles.

**Pagination.**

- 20 results per page. A "Show more results" button sits at the end (`Inertia::scroll()` with TMDB page metadata, `<InfiniteScroll manual>`).
- There is no automatic infinite scroll, which would keep the footer with the TMDB notice out of reach.
- The page number syncs to `?page=`, and loading stops at TMDB's page 500.
- A count ("4,141 results") comes from TMDB's `total_results`.

**States.**

| State | What shows |
| --- | --- |
| No query (or under 2 characters) | "Trending this week" from Discover's cached Trending section (no extra TMDB call), in the same card grid. |
| No results | "No movies match "{query}"", a tip ("Check the spelling, or try fewer or different words"), then the same Trending grid. |
| First search loading | A grid of shimmer cards. |
| Query changed while results are showing | The current results stay, slightly dimmed, until the new ones arrive. There is no flash to skeletons on each keystroke. |
| Show more loading | A spinner in the button, and shimmer cards appended for the incoming page. |
| Results settled | A polite live region announces "{n} results for {query}". |
| `stale` | Shown normally, with no notice (as on Discover). |
| `unavailable` | An inline error, "We can't search right now", with Retry. The query stays in the input and the URL. If the Trending suggestions also fail, they are hidden. |

**Opening a result.**

- A result links to the canonical Movie URL when the row exists, otherwise to the entry route ([Movie identity, URLs and local storage](./09-movie-identity-and-storage.md)).
- The link carries the `search` referrer for `movie_viewed`.
- Coming back with Back restores the query, the loaded pages and the scroll position.

**Saving a Movie that has no row yet (applies to every list surface, Discover included).**

- The Watchlist and Watched endpoints are addressed by catalog id: `PUT`/`DELETE /movies/tmdb/{tmdbId}/watchlist` and `/watched`.
- The endpoint finds or creates the Movie row through `Catalog` exactly as the entry route does (TMDB confirms the id first, under the same rate limit), then sets the fact.
- The `userMovies` page prop is keyed by catalog id, so every surface can look up a Movie's state whether or not it has a row.
- Undo snapshots and the guest pending action carry the catalog id.
- The rejected options:
    - creating rows for every listed Movie, already ruled out by [Movie identity, URLs and local storage](./09-movie-identity-and-storage.md);
    - routing through the detail page before saving, which is poor UX;
    - two endpoint families, one by local id and one by catalog id, which duplicates code.
- This amends [Watchlist interactions](./13-watchlist-interactions.md).

**Analytics.**

- `movie_searched` is recorded on each page-1 search response.
- Live search means one person typing a title can record several events, some with `result_count` 0 from half-typed prefixes. So `metrics:report` counts distinct users searching per day and does not report a zero-result rate.

**Effects on other tickets.**

- [Watchlist interactions](./13-watchlist-interactions.md): catalog-id endpoints, `userMovies` keyed by catalog id, and Search added to the surfaces table (Watchlist toggle only).
- [Homepage curation](./22-homepage-curation.md): Discover's bookmark toggles use the catalog-id endpoints.
- [Rate limiting and security headers](./28-rate-limits-and-security.md): the catalog-id action endpoints share the entry route's limit on row creation.
- [Privacy, terms and retention](./30-privacy-terms-and-retention.md): whether Cloud's access logs hold search queries.
- [Analytics and error monitoring](./08-analytics-and-monitoring.md) and [Deployment and operations](./19-deployment-and-operations.md): the `q` redaction and the distinct-searcher metric.
