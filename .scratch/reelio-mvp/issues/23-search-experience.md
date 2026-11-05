# Search experience

Type: grilling
Status: open
Map: [Reelio MVP](../map.md)

## Question

How does search behave (FR-002)?

- As-you-type suggestions vs a results page: TMDB prefix-matches the last word but ranks short queries badly (`inter` misses *Interstellar*), see [Verify TMDB behaviours with a live key](./17-tmdb-live-checks.md).
- Minimum query length, debounce, and what Enter does.
- Results layout, pagination (TMDB caps pages at 500), and the empty and error states, including when the Catalog is `stale` or `unavailable` ([Catalog and Rating source boundary](./10-source-boundary.md)).
- Whether the query lives in the URL (shareable, back-button safe). Search results are `noindex` ([Public pages](./14-public-pages-seo.md)), and the search text is never stored ([Analytics and error monitoring](./08-analytics-and-monitoring.md)).
