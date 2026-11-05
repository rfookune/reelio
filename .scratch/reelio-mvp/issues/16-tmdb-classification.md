# Confirm TMDB's classification of Reelio

Type: task
Status: claimed
Map: [Reelio MVP](../map.md)

## Question

Will TMDB classify Reelio, a free, permanently non-commercial public site with indexable movie pages and a sitemap, as non-commercial use?

This is HITL: the human emails TMDB (sales@themoviedb.org, or applies for the API key with an honest description) using the five questions listed in the [TMDB terms research](https://github.com/rfookune/reelio/blob/research/tmdb-terms/.scratch/reelio-mvp/research/tmdb-terms.md), then records TMDB's written answer here. A "commercial" answer is a **launch** blocker, not a spec blocker. [Public pages: SSR, SEO metadata and sitemap scope](./14-public-pages-seo.md) should design for either answer (e.g. the sitemap scope can be toggled) rather than wait on this.

## Comments

- From [Public pages: SSR, SEO metadata and sitemap scope](./14-public-pages-seo.md) (resolved): indexing ships behind a config flag that defaults to off. A favourable written answer from TMDB is what turns it on; an unfavourable one keeps it off.
- Email draft (2026-11-04), updated from the research's five questions for later decisions: OMDb is replaced by IMDb's non-commercial datasets, the commercial-pricing question is moot because Reelio is permanently non-commercial, and the name is a working name pending [Product name](./20-product-name.md). Send it to sales@themoviedb.org. If there's no API key yet, apply for one at the same time with the same description; the key also unblocks [Verify TMDB behaviours with a live key](./17-tmdb-live-checks.md).

    > **Subject:** Non-commercial classification for a free movie watchlist site
    >
    > Hello TMDB team,
    >
    > I'm building a free, public movie website (working name "Reelio"; the final name may change) on the TMDB API, and I'd like your written confirmation that it counts as non-commercial use under §2.A of the API Terms before launch.
    >
    > What it does: visitors browse and search movies, open a page per movie (overview, cast, trailer, TMDB rating, and where to stream it in their country), and signed-in users keep a private Watchlist and Watched list and use a random picker. What it doesn't do: it will never earn money (no ads, affiliate links, paid tier, sponsorships, or data sales), it uses no TMDB data for AI or ML, and it doesn't link directly to Streaming services, only to TMDB's watch page. Images are hotlinked from your CDN, stored data is refreshed or purged within 6 months, and the TMDB logo and notice appear in the footer and on an About page, with a JustWatch credit on every availability block. It also shows IMDb ratings from IMDb's own non-commercial datasets (with IMDb's permission, requested separately).
    >
    > My questions:
    >
    > 1. Is this non-commercial under §2.A? In particular, does making the movie pages indexable by search engines make it a "destination website"?
    > 2. Does the site Terms' "index" clause restrict publishing a sitemap of those movie pages for an API licensee?
    > 3. Does refreshing a stored record restart the 6-month cap? May a bare TMDB movie ID be kept in a user's private watchlist for longer than that?
    > 4. What form should the JustWatch credit take? I plan a text credit ("Streaming data from JustWatch") next to every availability block. Is there an approved JustWatch logo for API users?
    > 5. Is showing IMDb ratings next to TMDB data on the same page acceptable?
    >
    > Thank you,
    > Rhyan

- What closes this task: TMDB's written reply, pasted or summarised here with its date. If question 1 is answered "non-commercial, indexing fine", the indexing flag from [Public pages](./14-public-pages-seo.md) may be turned on at launch. Any other answer keeps it off and comes back to the map.
