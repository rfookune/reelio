# 36: Accessibility sweep

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** An automated axe sweep covers every page in its main states, in both themes at both sizes, so accessibility regressions fail CI. See the spec's "Accessibility" and "Testing Decisions" sections.

**Blocked by:** [09: Trailer](./09-trailer.md), [13: Choose a country](./13-choose-a-country.md), [20: Coming soon and genre collections](./20-coming-soon-and-genre-collections.md), [21: Search page](./21-search-page.md), [23: Watched page](./23-watched-page.md), [24: Removed Movies in lists and on the 410 page](./24-removed-movies-in-lists.md), [25: Movie picker](./25-movie-picker.md), [28: Auth rate limits and the 429 experience](./28-auth-rate-limits-and-429.md), [33: About, Privacy, Terms and Accessibility pages](./33-about-privacy-terms-accessibility-pages.md)

**Status:** resolved

- [x] A browser test visits, at desktop and mobile in both themes (via the `appearance` cookie): Discover; Search with no query, results and no results; a Movie page and a Removed Movie (410); the Watchlist empty and full; Watched; the picker open; the auth and settings pages; the 404, 429 and 500 pages
- [x] Each runs `assertNoAccessibilityIssues()` at level 1 and the suite is green
- [x] Any issue it finds is fixed in this ticket

## Comments

- Resolution: `tests/Browser/AccessibilitySweepTest.php` visits 19 page states at desktop (1440) and mobile (390) in both themes, 76 cases in all, and runs `assertNoAccessibilityIssues(level: 1)` and `assertNoJavaScriptErrors()` on each. The states are Discover; Search with no query, results and no results; a Movie page; a Removed Movie (410); the Watchlist empty and full; Watched; the picker open; login, register and forgot-password; the profile, security and appearance settings; and the 404, 429 and 500 pages. It was committed in `7c6a584`.
- Helpers in `tests/Pest.php`: `visitWithAppearance()` writes the `appearance` cookie in the browser and reloads, and `visitWithCsp()` takes a `colorScheme`.
- Issue found and fixed: the Warning text in the Delete account block on the profile settings page (`DeleteUser.vue`) was `text-red-600` on `bg-red-50`, 4.46:1 against the 4.5:1 minimum. It is now `text-red-700` in light mode.
- Limitation: the 404 and 500 pages are Laravel's built-in views, not the shared `Error` page. They follow the operating system's colour scheme, not the `appearance` cookie, so a visitor who chose Dark in Reelio while their OS is light sees them in light. The sweep sets the OS scheme to match for those two pages and passes axe in both. It skips the check that the `dark` class is present on them. Rendering 404 and 500 through the shared `Error` page would fix the mismatch, but it is a product change and was not made here.
- Validation: Pint, vp check, vue-tsc, PHPStan, the Unit and Feature suites (705 tests) and the Browser suite (161 tests) all pass. An earlier Browser run showed 3 database errors in `WatchlistToggleTest`, caused by a second test run using the same `reelio_testing` database at the same time. They did not recur on a quiet database.
