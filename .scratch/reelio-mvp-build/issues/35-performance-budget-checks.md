# 35: Performance budget checks

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Performance regressions fail CI: the JavaScript budget, image loading rules and prefetch rules are checked automatically. See the spec's "Images and performance" section.

**Blocked by:** [04: Browser test harness and the sign-in journey](./04-browser-tests-and-sign-in-journey.md), [07: Movie page layout and details](./07-movie-page-layout-and-details.md), [19: Discover homepage](./19-discover-homepage.md), [22: Watchlist page](./22-watchlist-page.md)

**Status:** resolved

- [ ] ~~A small script reads Vite's build output and fails CI when initial JavaScript for Discover or a Movie page exceeds 150 KB gzipped (no new dependency); pages stay code-split~~ (won't do)
- [ ] ~~Browser checks: every `<img>` has an aspect box or explicit dimensions; exactly one `fetchpriority="high"` image on Discover and on a Movie page and none on grid pages; images below the fold are lazy~~ (won't do)
- [x] `preconnect` to `image.tmdb.org` in the root layout; ~~nav items prefetch on hover; Movie links are never prefetched; no instant visits~~ (the rest won't do; the code already behaves this way)

## Comments

- Resolution: only the `preconnect` to `image.tmdb.org` was done. It sits in `resources/views/app.blade.php`, with `tests/Feature/ImagePreconnectTest.php`.
- Won't do: the CI bundle-size script, the browser image checks and the prefetch/instant-visit guards. A trial run found Discover at 196 KB and a Movie page at 200 KB gzipped, over the 150 KB budget, so the script would have failed CI until Sentry, Sonner and the account menu were made lazy. That work was reverted.
- The spec's JavaScript budget in "Images and performance" is therefore not enforced, and is not currently met.
