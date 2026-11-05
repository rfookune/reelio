# Rate limiting and security headers

Type: grilling
Status: open
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
