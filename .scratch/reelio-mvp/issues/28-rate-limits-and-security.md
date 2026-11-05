# Rate limiting and security headers

Type: grilling
Status: open
Blocked by: 24
Map: [Reelio MVP](../map.md)

## Question

What are the rate limits and the security baseline?

- Limits for auth (beyond Fortify's defaults), search, the picker endpoint, the Watchlist and Watched action endpoints and Undo. The entry-route limit and the Catalog circuit breaker are decided ([Movie identity](./09-movie-identity-and-storage.md), [Catalog and Rating source boundary](./10-source-boundary.md)).
- Security headers: CSP (TMDB images, the YouTube trailer embed from [Movie detail page](./24-movie-detail-page.md), Sentry), HSTS, and a Referrer-Policy that doesn't break YouTube embeds.
- Safe handling of third-party URLs (the TMDB watch-page link, trailer URLs) and open-redirect protection, including the intended-URL redirect after sign-in ([Watchlist interactions](./13-watchlist-interactions.md)).
