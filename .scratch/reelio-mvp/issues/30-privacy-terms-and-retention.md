# Privacy, terms and retention

Type: grilling
Status: open
Blocked by: 26
Map: [Reelio MVP](../map.md)

## Question

What do the Privacy and Terms pages say, and what is the published retention policy?

- What's collected and why: the account, Watchlist and Watched facts, first-party analytics ([Analytics and error monitoring](./08-analytics-and-monitoring.md)), the country inferred from the connection at the edge and not stored ([Country and Availability presentation](./11-country-and-availability.md)).
- Third parties: Laravel Cloud (hosting, access logs with IPs for 7 days, which may include search queries from `?q=`; checked at build per [Search experience](./23-search-experience.md)), Nightwatch, Sentry, Resend, TMDB, IMDb, JustWatch ([Deployment and operations](./19-deployment-and-operations.md)).
- Retention: account deletion, backups (up to 7 days), logs, analytics roll-up after 13 months, cached Catalog data ([Cache lifetimes and refresh schedule](./26-cache-lifetimes-and-refresh.md)).
- The Terms: non-commercial, no guarantee that Availability is current, the attribution notices ([TMDB terms](./01-tmdb-terms.md)), and the product name ([Product name](./20-product-name.md)).

## Comments

- From [Movie detail page](./24-movie-detail-page.md) (resolved): trailers are click-to-play from `youtube-nocookie.com`. Nothing loads from YouTube until the visitor presses play, and YouTube may set cookies after that (the Movie page says so under the player). The Privacy page should say the same and list YouTube as a third party.
