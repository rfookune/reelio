# 31: SEO, SSR and the indexing flag

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Public pages render on the server with complete metadata, and one config flag (off by default) controls whether anything is indexable. See the spec's "Public pages, SSR and SEO" section.

**Blocked by:** [07: Movie page layout and details](./07-movie-page-layout-and-details.md), [19: Discover homepage](./19-discover-homepage.md)

**Status:** ready-for-agent

- [ ] Inertia SSR is on for Discover, Movie pages, About, Privacy, Terms and Accessibility only, off for Search, auth, Watchlist, Watched and settings; client rendering takes over if SSR is down
- [ ] One `<Head>` helper sets titles ("{Title} ({year}) · Reelio", "{Page} · Reelio"), descriptions (overview cut to ~155 characters at a word boundary; a fixed line for Discover), Open Graph and Twitter images (backdrop `w1280`, else poster, else a static card) and the canonical URL
- [ ] Movie pages carry schema.org `Movie` JSON-LD (`name`, `datePublished`, `director`, `genre`, `duration`, `image`, `description`, `url`), no `aggregateRating`
- [ ] An indexing flag, default off: off means `robots.txt` is `Disallow: /` and every page is `noindex`; on means only Discover, Movie pages, About, Privacy, Terms and Accessibility are indexable
- [ ] `X-Robots-Tag: noindex` on Search, auth, Watchlist, Watched, settings, the picker endpoint, the entry route's 301, the TMDB watch-page redirect, 410 pages and the cap-cleared 503, regardless of the flag
- [ ] SSR output contains the Movie page's cast and Director
- [ ] Feature tests cover both flag states, the header list and the metadata
