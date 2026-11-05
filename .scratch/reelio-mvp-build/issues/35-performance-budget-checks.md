# 35: Performance budget checks

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Performance regressions fail CI: the JavaScript budget, image loading rules and prefetch rules are checked automatically. See the spec's "Images and performance" section.

**Blocked by:** [04: Browser test harness and the sign-in journey](./04-browser-tests-and-sign-in-journey.md), [07: Movie page layout and details](./07-movie-page-layout-and-details.md), [19: Discover homepage](./19-discover-homepage.md), [22: Watchlist page](./22-watchlist-page.md)

**Status:** ready-for-agent

- [ ] A small script reads Vite's build output and fails CI when initial JavaScript for Discover or a Movie page exceeds 150 KB gzipped (no new dependency); pages stay code-split
- [ ] Browser checks: every `<img>` has an aspect box or explicit dimensions; exactly one `fetchpriority="high"` image on Discover and on a Movie page and none on grid pages; images below the fold are lazy
- [ ] `preconnect` to `image.tmdb.org` in the root layout; nav items prefetch on hover; Movie links are never prefetched; no instant visits
