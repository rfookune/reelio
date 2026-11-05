# Images and performance budget

Type: grilling
Status: resolved
Blocked by: 22, 24
Map: [Reelio MVP](../map.md)

## Question

What image sizes, loading strategy and performance budget does the app use?

- Poster, backdrop and logo size variants per breakpoint from TMDB's image sizes ([TMDB API coverage](./02-tmdb-api-coverage.md)), with `srcset`, lazy loading and the hero's priority loading. Images are hotlinked only ([TMDB terms](./01-tmdb-terms.md)).
- The LCP, CLS and INP targets and how they're checked (warm requests only; cold-start allowance from [Deployment and operations](./19-deployment-and-operations.md)).
- The likely LCP elements are the Discover hero ([Homepage curation](./22-homepage-curation.md)) and the Movie page backdrop ([Movie detail page](./24-movie-detail-page.md)).

## Comments

- From [Movie detail page](./24-movie-detail-page.md) (resolved): the Movie page's LCP element is the hero backdrop at `w1280` with `fetchpriority="high"`; a blurred glow reuses the same URL, so it costs no second download. Without a backdrop, a blurred poster fills the hero. The poster sits beside the title from `sm`. The trailer's cover image is that same backdrop, so nothing loads from YouTube until play. Cast photos are `w185` (12, lazy) and service logos `w92`. Set the `srcset` sizes here.
- From [Watchlist and Watched pages](./25-watchlist-and-watched-pages.md) (resolved): Watchlist and Watched load 48 poster cards per page (2–6 columns), with more appended by "Show more". Set the card poster size and `srcset`/`sizes` for that grid, and lazy-load the cards below the first rows.

## Answer

The framing facts:

- **Images:** hotlinked from `image.tmdb.org` at TMDB's published sizes, never resized or rehosted ([TMDB terms, attribution and caching limits](./01-tmdb-terms.md)). They're JPEGs, with no format negotiation.
- **Fonts:** Inter is fetched at build time by `laravel-vite-plugin`'s font helper and served by Reelio itself, so there's no font third party at runtime.
- **Monitoring:** nothing measures performance in visitors' browsers (analytics has no browser script; Sentry is errors only), so there's no field Web Vitals data.

**Image sizes**

Every image uses `srcset` with width descriptors and a `sizes` hint matching its layout. `original` is never used.

| Surface                                                   | Displayed (CSS px) | `srcset` candidates                                                        |
| --------------------------------------------------------- | ------------------ | -------------------------------------------------------------------------- |
| Poster cards (Watchlist, Watched, Search; 2 to 6 columns) | about 150 to 200   | `w185`, `w342`, `w500`                                                     |
| Discover row posters                                      | about 56           | `w92`, `w154`                                                              |
| Detail hero poster (from `sm`)                            | about 200 to 260   | `w342`, `w500`                                                             |
| Picker dialog poster                                      | about 160          | `w185`, `w342`                                                             |
| Cast photos                                               | about 120          | `w185` only                                                                |
| Streaming service logos                                   | about 40           | `w92` only                                                                 |
| Discover and Movie page hero backdrops                    | full width         | `<picture>`: `w780` below 768px, `w1280` from 768px                        |
| Open Graph image                                          | —                  | backdrop `w1280` (unchanged from [Public pages](./14-public-pages-seo.md)) |

- **Why `w780` for backdrops on phones:** `srcset` with `sizes="100vw"` makes a 3× phone pick `w1280`, roughly twice the bytes, for the LCP image. That image sits under a gradient and mask, where the extra sharpness doesn't show. The blurred glow uses the same breakpoints, so it reuses the same file. This refines [Movie detail page](./24-movie-detail-page.md)'s plain `w1280`.

**Loading priority**

- `<link rel="preconnect" href="https://image.tmdb.org">` in the root layout.
- **The hero backdrop** on Discover and the Movie page loads eagerly with `fetchpriority="high"`. It's the only high-priority image on any page.
- **Grid pages** (Watchlist, Watched, Search) load the first 6 cards eagerly, because a poster is their LCP element.
- **Every other image** uses `loading="lazy"` and `decoding="async"`, including Discover's row posters.
- **The detail hero poster** loads only from `sm`, through `<picture>` with a media query, so phones never download an image they don't show.

**Layout stability**

- Every image sits in a fixed-ratio box filled with the raised colour before it loads: posters 2:3, backdrops 16:9, profiles 2:3, logos 1:1.
- An image that fails to load swaps to the missing-poster tile, or the person tile for cast.
- Fonts:
    - Inter 400 and 600 (Latin WOFF2) are preloaded through the font helper's `preload` option.
    - **`fontaine`** is added as a dev dependency (approved by the owner here), so the helper generates metric-adjusted fallback fonts and text doesn't reflow when Inter arrives.

**Targets**

| Measure                       | Target                                                                                                        |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------- |
| LCP                           | ≤ 2.5 s                                                                                                       |
| CLS                           | ≤ 0.1                                                                                                         |
| INP                           | ≤ 200 ms                                                                                                      |
| Server time, p95 (Nightwatch) | ≤ 300 ms for Discover, the Watchlist and Watched pages, and a Movie page served without an in-request refresh |
| Search, cached results        | ≤ 500 ms (PRD)                                                                                                |

The three Web Vitals are Core Web Vitals "good", at the 75th percentile on a mobile profile for warm requests. Cold starts after idle are exempt, as accepted in [Deployment and operations](./19-deployment-and-operations.md).

**How performance is checked**

- **By hand:** PageSpeed Insights (mobile) against production for Discover, one Movie page and Search. Run before launch and whenever a release touches the shell, the heroes or image handling. Results go in a short runbook table.
- **Pest browser tests (structural):**
    - every `<img>` has an aspect box or explicit dimensions;
    - exactly one image has `fetchpriority="high"` on Discover and the Movie page, and none on grid pages;
    - images below the fold are lazy.
- **JavaScript budget in CI:** the initial JavaScript for a public page (Discover, a Movie page) stays at most **150 KB gzipped**: Vue, Inertia, the shell and that page's chunk. A small script reads Vite's build output and fails the build over budget, with no new dependency. Pages stay code-split (Inertia's default).
- Rejected:
    - Lighthouse CI against preview environments: a new dependency, and noisy on cold-starting previews.
    - Collecting real-user Web Vitals: needs the browser script and endpoint that analytics deliberately avoided. It's a spec follow-up if the site grows.

**Prefetching and instant visits**

- The nav items (Discover, Search, Watchlist, Watched) use Inertia's hover prefetch.
- **Movie links are never prefetched.** A prefetch could hit the entry route, which creates rows, or set off an in-request TMDB refresh ([Cache lifetimes and refresh schedule](./26-cache-lifetimes-and-refresh.md)) for a page nobody opens.
- No instant visits for Movie pages in the MVP. The progress bar covers the wait, so the Movie page skeletons from [Movie detail page](./24-movie-detail-page.md) aren't built.

**Spec follow-up**

- Real-user Web Vitals collection.
