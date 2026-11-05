# Public pages: SSR, SEO metadata and sitemap scope

Type: grilling
Status: open
Blocked by: 09
Map: [Reelio MVP](../map.md)

## Question

How are public discovery and movie pages made indexable?

- Whether to enable Inertia SSR.
- Per-page titles, meta descriptions, Open Graph images, canonical URLs, and structured movie data (schema.org `Movie`).
- What the sitemap includes, given the effectively unbounded TMDB catalog.
- How private pages are kept out of indexes (`noindex`, robots).

## Comments

- From [Movie identity, URLs and local storage](./09-movie-identity-and-storage.md) (resolved): the canonical URL is `/movies/{slug}-{local-id}`, reached from lists via a rate-limited entry route by catalog id that creates the row on first open and 301-redirects. A dropped Movie returns 410. The sitemap can list only existing rows.
