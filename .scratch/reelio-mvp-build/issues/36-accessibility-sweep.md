# 36: Accessibility sweep

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** An automated axe sweep covers every page in its main states, in both themes at both sizes, so accessibility regressions fail CI. See the spec's "Accessibility" and "Testing Decisions" sections.

**Blocked by:** [09: Trailer](./09-trailer.md), [13: Choose a country](./13-choose-a-country.md), [20: Coming soon and genre collections](./20-coming-soon-and-genre-collections.md), [21: Search page](./21-search-page.md), [23: Watched page](./23-watched-page.md), [24: Removed Movies in lists and on the 410 page](./24-removed-movies-in-lists.md), [25: Movie picker](./25-movie-picker.md), [28: Auth rate limits and the 429 experience](./28-auth-rate-limits-and-429.md), [33: About, Privacy, Terms and Accessibility pages](./33-about-privacy-terms-accessibility-pages.md)

**Status:** ready-for-agent

- [ ] A browser test visits, at desktop and mobile in both themes (via the `appearance` cookie): Discover; Search with no query, results and no results; a Movie page and a Removed Movie (410); the Watchlist empty and full; Watched; the picker open; the auth and settings pages; the 404, 429 and 500 pages
- [ ] Each runs `assertNoAccessibilityIssues()` at level 1 and the suite is green
- [ ] Any issue it finds is fixed in this ticket
