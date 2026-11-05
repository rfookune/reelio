# Rate limiting and security headers

Type: grilling
Status: resolved
Blocked by: 24
Map: [Reelio MVP](../map.md)

## Question

What are the rate limits and the security baseline?

- Limits for auth (beyond Fortify's defaults), search, the picker endpoint, the Watchlist and Watched action endpoints and Undo. The catalog-id action endpoints create Movie rows, so they share the entry route's row-creation limit ([Search experience](./23-search-experience.md)). The entry-route limit and the Catalog circuit breaker are decided ([Movie identity](./09-movie-identity-and-storage.md), [Catalog and Rating source boundary](./10-source-boundary.md)).
- Security headers: CSP (TMDB images, the YouTube trailer embed from [Movie detail page](./24-movie-detail-page.md), Sentry), HSTS, and a Referrer-Policy that doesn't break YouTube embeds.
- Safe handling of third-party URLs (the TMDB watch-page link, trailer URLs) and open-redirect protection, including the intended-URL redirect after sign-in ([Watchlist interactions](./13-watchlist-interactions.md)).

## Comments

- From [Search experience](./23-search-experience.md) (resolved): the search query is in `?q=`, and search pages hotlink TMDB posters. Keep the Referrer-Policy at `strict-origin-when-cross-origin` or stricter (never `unsafe-url` or `no-referrer-when-downgrade`), so search text doesn't reach TMDB's image CDN or YouTube in Referer headers.

- From [Movie detail page](./24-movie-detail-page.md) (resolved): the trailer is click-to-play: pressing play loads `https://www.youtube-nocookie.com/embed/{key}` in an iframe with `referrerpolicy="strict-origin-when-cross-origin"`, so CSP needs `frame-src https://www.youtube-nocookie.com` (and `img-src https://image.tmdb.org` for the cover). External links (the TMDB watch page, Watch on YouTube, imdb.com in the IMDb credit) open in a new tab with `rel="noopener noreferrer"`.

## Answer

The framing facts:

- **Fortify's defaults** throttle login (5/min per email and IP), the 2FA challenge, passkeys and the verification email (6/min). Registration, forgot-password, reset-password and confirm-password have no throttle. The password broker's 60-second throttle is per email, so it doesn't stop sign-up or reset emails sprayed across many addresses.
- **Laravel Cloud's edge** ([network docs](https://laravel.com/cloud/docs/network)) sets `X-Frame-Options: DENY` and `X-Content-Type-Options: nosniff` by default. It offers HSTS as an off-by-default toggle and an opt-in basic rate limit on Growth (100 requests/min per IP, Challenge, Throttle or Ban for 5 minutes). Every environment sits behind Cloudflare's OWASP ruleset, and path blocking is on. The docs don't say how the client IP reaches the app.
- **The entry-route limit had no number.** [Movie identity, URLs and local storage](./09-movie-identity-and-storage.md) said only "rate-limited", so it is set here.

**Keying**

- Limits key by **user id when signed in, and by IP for guests**.
- **Deploy check**, alongside the `CF-IPCountry` check: on a preview environment, `$request->ip()` returns the visitor's real IP, and a forged `X-Forwarded-For` or `CF-Connecting-IP` header doesn't change it. If it fails, trust only Cloudflare's published ranges, never `*`. A shared edge IP would make Fortify's login limit global; a spoofable one would make every guest limit useless.

**Application rate limits**

| What                                                                               | Limit                                                                                  | Key        |
| ---------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------- | ---------- |
| Registration                                                                       | 10 per hour                                                                            | IP         |
| Forgot-password                                                                    | 5 per minute (the broker's 60 s per-email throttle stays)                              | IP         |
| Reset-password                                                                     | 5 per minute                                                                           | IP         |
| Confirm-password and account deletion                                              | 5 per minute                                                                           | user       |
| Profile update                                                                     | 6 per minute                                                                           | user       |
| Search                                                                             | 60 per minute (live search sends several requests per typed query)                     | user or IP |
| Movie creation: the entry route and the catalog-id Watchlist and Watched endpoints | 20 per minute, counting only requests that need a TMDB check because no row exists yet | user or IP |
| Watchlist and Watched `PUT`/`DELETE`, and Undo                                     | 60 per minute, shared (guests storing a pending action included)                       | user or IP |
| Picker                                                                             | 30 per minute                                                                          | user       |
| CSP report endpoint                                                                | 10 per minute                                                                          | IP         |

- Fortify's existing login, 2FA, passkey and verification limits are unchanged.
- **No account enumeration on forgot-password:** it says "If that email has an account, we've sent a link" whether or not the account exists. Registration still reports a taken email; there's no good way to hide it, and its limit makes probing slow.
- **Live TMDB ceiling:** all live TMDB calls (search cache misses, the entry route, in-request detail refreshes) share a **15 requests/s** throttle in Valkey, beside the background jobs' 20/s and under TMDB's ~40/s. Over it, `Catalog` doesn't wait: it returns its existing `stale` or `unavailable` result, so pages degrade exactly as in a TMDB outage. Together with the circuit breaker ([Catalog and Rating source boundary](./10-source-boundary.md)), this is the outbound protection. It replaces any global cap on Movie creation, which a crawler spread over many IPs would get past anyway.

**What a 429 looks like**

- Every 429 carries `Retry-After`, with one copy line: "You're going too fast. Try again in a moment."
- **Watchlist and Watched actions and Undo:** the existing optimistic rollback ([Watchlist interactions](./13-watchlist-interactions.md)), with that line in the error toast instead of "Couldn't save that. Try again."
- **Search and the picker:** the same line, shown inline.
- **Full-page visits** (the entry route, auth forms posting as full visits): a styled 429 error page.

**Edge rate limit**

- Cloud's basic rate limit (100 requests/min per IP) is **on in production only**, with the **Throttle** action. Challenge would break background requests (search, the picker, Watchlist actions). It's a flood backstop; the app limits above are the real policy.
- **Build check:** static assets served from the edge cache don't count toward the 100.
- Revisit it when SEO indexing is switched on ([Public pages](./14-public-pages-seo.md)), because crawlers may hit it.

**Where the headers live**

- **Cloud's edge:** `X-Frame-Options: DENY` and `nosniff` (defaults kept). HSTS uses the edge toggle in production, so it covers assets too: **max-age one year, without `includeSubDomains` or `preload`**, which browsers cache and are hard to undo. Reconsider both once the Houselight domain is settled ([Clear Houselight on the official registers](./21-houselight-register-clearance.md)).
- **One app middleware on every environment** (so Pest can assert it): CSP, `Referrer-Policy: strict-origin-when-cross-origin`, Permissions-Policy and `Cross-Origin-Opener-Policy: same-origin`. No COEP: it would break the trailer embed and TMDB images.

**The CSP**

```
default-src 'self';
script-src 'self' 'nonce-{per-request}';
style-src 'self' 'unsafe-inline';
img-src 'self' data: https://image.tmdb.org;
font-src 'self';
connect-src 'self' https://<org>.ingest.de.sentry.io;
frame-src https://www.youtube-nocookie.com;
frame-ancestors 'none';
form-action 'self';
base-uri 'none';
object-src 'none';
report-uri /csp-report
```

- **Scripts are nonce-based**, through `Vite::useCspNonce()`, plus the nonce on the root view's inline appearance script.
- **Styles allow inline**, because server-rendered Vue and reka-ui emit `style="…"` attributes. The root view's inline `<style>` is covered by this.
- The middleware is Reelio's own; no `spatie/laravel-csp` dependency.
- **Sentry** is called directly, with its "don't store IP addresses" setting on, rather than through a tunnel route.
- **Rollout:** enforced from day one on every environment. While Vite's dev server is running, its origin and websocket are added, so local development sees the same policy.
- **Violation reports** go to Reelio's own `POST /csp-report`, not Sentry's CSP endpoint, because browsers put the full page URL, `?q=` included, in every report ([Search experience](./23-search-experience.md) keeps search text out of monitoring). The endpoint:
    - needs no CSRF token or sign-in, and always returns 204;
    - strips query strings from `document-uri`, `referrer` and `blocked-uri`, keeps only the directive, those three fields and the source location, and logs a warning that Nightwatch collects (no IP);
    - is limited to 10 per minute per IP.

**Permissions-Policy**

- Denied: `camera`, `microphone`, `geolocation`, `payment`, `usb`.
- Left alone: `publickey-credentials-get` and `publickey-credentials-create`, because passkeys stay.
- Delegated to `https://www.youtube-nocookie.com`: `autoplay`, `fullscreen` and `encrypted-media`, both in the policy and in the trailer iframe's `allow` attribute (the embed uses `autoplay=1`).

**Third-party URLs**

Reelio builds every outbound URL from checked ids and never passes a URL from TMDB through.

- **TMDB watch page:** built from `tmdb_id` and the country.
- **Trailer:** the YouTube key must match `^[A-Za-z0-9_-]{11}$`, or the trailer is dropped; the embed and "Watch on YouTube" URLs are built from the key.
- **Images:** paths must match `^/[A-Za-z0-9_-]+\.(jpg|png|svg)$`, under a fixed `https://image.tmdb.org/t/p/{size}` base.
- **Rendering:** `v-html` is a lint error, except for the server-generated 2FA QR code.
- External links keep `target="_blank"` with `rel="noopener noreferrer"` ([Movie detail page](./24-movie-detail-page.md)).

**Redirect after sign-in**

- The intended URL for a guest's pending action ([Watchlist interactions](./13-watchlist-interactions.md)) is the page the session last recorded on a page load, never a header or a request parameter. The action itself arrives as a background `PUT`, so its own URL isn't the page.
- One guard checks every intended URL: only relative paths starting with a single `/` (not `//` or `/\`) are accepted; anything else falls back to Discover.

**Input and cookies**

- `q` is trimmed and at most 100 characters; `page` is 1 to 500; `sort` must be one of the fixed values ([Watchlist and Watched pages](./25-watchlist-and-watched-pages.md)).
- The country cookie must be a supported country ([Country and Availability presentation](./11-country-and-availability.md)), otherwise it's ignored.
- The `appearance` cookie must be `light`, `dark` or `system`, otherwise `system`. Today `HandleAppearance` echoes it unchecked into the root view's inline script.
- In production, the session cookie is `Secure`, `HttpOnly` and `SameSite=Lax`.

**Dependency audits**

- `composer audit` and `npm audit --audit-level=high` run as a required CI job.
- Dependabot security alerts and security updates are on for the repository. No new dependencies.

**Testing**

- **Pest feature tests:**
    - each new limit: the request after the limit gets a 429 with `Retry-After`;
    - the headers on a public page, a private page and an error page, with a fresh nonce on each request;
    - the intended-URL guard rejecting `//evil.com` and `/\evil.com`;
    - the cookie checks;
    - the CSP report endpoint's redaction.
- **Browser journeys** ([Test strategy and tooling](./07-test-strategy.md)) run under the real CSP and fail on any console error, so a violation breaks CI.
- Cloud's edge settings (HSTS, edge rate limit, frame and nosniff headers) aren't tested; they're on the deploy checklist.

**Deploy checklist additions**

- The client IP is real and can't be spoofed (see Keying).
- Static assets don't count toward the edge rate limit.
- In production: HSTS on (one year, no `includeSubDomains` or `preload`), the edge rate limit on with Throttle, and `X-Frame-Options: DENY` and `nosniff` still set.
- Sentry's "don't store IP addresses" setting is on.
