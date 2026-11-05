# Public pages: SSR, SEO metadata and sitemap scope

Type: grilling
Status: resolved
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

## Answer

**SSR**

- Inertia SSR runs for public pages only: Discover (home), Movie pages, About/Credits, Privacy and Terms. It's turned off per route for search, auth, Watchlist, Watched and settings.
- SSR serves crawlers that don't run JavaScript and Open Graph scrapers, and helps LCP on the Discover hero.
- If the SSR process is down, Inertia falls back to client rendering. Whether Laravel Cloud runs the SSR process is a check in [Deployment and operations](./19-deployment-and-operations.md).

**Indexing gate**

- One config flag, default **off**. Off means `robots.txt` serves `Disallow: /`, every page is `noindex`, and there's no sitemap.
- It's turned on only after [Confirm TMDB's classification of Reelio](./16-tmdb-classification.md) comes back favourable. If TMDB says indexing makes Reelio commercial, it stays off. The beta can launch before TMDB answers.

**Indexable vs private (flag on)**

- Indexable: Discover, Movie pages, About/Credits, Privacy, Terms.
- Everything else gets `X-Robots-Tag: noindex` from middleware: search results, auth, Watchlist, Watched, settings, the picker endpoint, the entry route's 301, and the 410 pages for dropped Movies.
- Private pages are not disallowed in `robots.txt`, because a crawler has to fetch a page to see its `noindex`. They also sit behind sign-in.

**Per-page metadata** (one shared helper, set through Inertia `<Head>`)

- Title: "{Title} ({year}) · Reelio". Other pages: "{Page} · Reelio".
- Description: the overview cut to about 155 characters at a word boundary. Discover has a fixed line.
- Open Graph and Twitter card image: the TMDB backdrop at `w1280`, hotlinked; else the poster; else a static Reelio card.
- Canonical: `/movies/{slug}-{id}`. A wrong slug gets a 301 to it.

**Structured data**

- Movie pages carry schema.org `Movie` JSON-LD: `name`, `datePublished`, `director`, `genre`, `duration`, `image`, `description`, `url`.
- No `aggregateRating`: Google forbids ratings taken from other sites, and the IMDb permission doesn't cover republishing by search engines.

**Sitemap**

- Every Movie row that isn't a shell, with `lastmod` set to its last metadata refresh. Generated daily as a sitemap index split at 50k URLs. It exists only while the indexing flag is on.

**Crawlers and the entry route**

- List links point at the canonical URL when the Movie already exists; the entry route is used only for first contact.
- Rows created by crawler visits get no special handling: they follow the standard purge of unviewed rows (the period is set by the cache-lifetimes patch).
