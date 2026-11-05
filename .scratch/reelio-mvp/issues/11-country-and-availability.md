# Country and Availability presentation

Type: grilling
Status: resolved
Blocked by: 01, 02
Map: [Reelio MVP](../map.md)

## Question

How does Reelio choose a country and present Availability?

- Country for guests (default, detection, manual change, persistence) vs signed-in users (`preferred_country`).
- Which countries are supported at launch.
- Grouping into stream, rent and buy, and where TMDB's extra `free` and `ads` (free with ads) categories go, since the PRD has no place for them; freshness indication; the "missing doesn't mean unavailable" messaging.
- Required attribution.
- How links are labeled honestly when they go to a general page rather than the title itself (FR-018 constraint). TMDB provides **no** per-title deep links, only one TMDB watch page per country, so decide what an Availability entry links to, if anything.
- Note: trending can't be scoped to a country (see [TMDB API coverage](./02-tmdb-api-coverage.md)); decide whether that matters for a US-first launch.

## Comments

- From [Movie identity, URLs and local storage](./09-movie-identity-and-storage.md) (resolved): `availabilities` stores rows only for the countries Reelio supports, so this ticket sets that list; the TMDB payload has about 120 countries per Movie.

## Answer

**Country**
- **Detection:** read from the edge header (`CF-IPCountry` or Laravel Cloud's equivalent). No geo-IP database, no IP stored. Absent header means US. Whether Laravel Cloud sends the header is unconfirmed; it is a check in [Deployment and operations](./19-deployment-and-operations.md).
- **Precedence:** signed-in `preferred_country`, then the guest cookie (an explicit choice), then the detected country if supported, then US.
- **Persistence:** only an explicit choice is written (guest: strictly-necessary first-party cookie, no banner; signed-in: `preferred_country`). Detection is recomputed per request and never saved. On sign-in or registration the guest's explicit choice is copied to the account only if the account has none.
- **Unsupported detected country:** fall back to US and say "Showing US availability. Your country isn't supported yet", with the selector offered.
- **Disclosure:** the block always names the country in use ("Streaming in Canada · Change"). The privacy page says the country is inferred from the connection at the edge and not stored.
- **Supported countries:** a config-driven allow-list of ten: US, CA, GB, AU, NZ, IE, DE, FR, ES, IT. Adding one is a config change plus a backfill. Unsupported countries can't be selected.

**Presentation**
- **Groups, in order:** Stream (subscription), Free (TMDB `free` and `ads` merged, labelled "Free, may include ads"), Rent, Buy. Empty groups hidden. `availabilities.offer_type` gains a `free` value.
- **Links:** entries are not clickable. One labelled link below the block, "See all watch options on TMDB", to TMDB's watch page for the selected country, in a new tab with `rel="noopener noreferrer"`. Nothing implies a deep link.
- **Attribution and caveat:** every block, empty state included, carries "Streaming data from JustWatch" and "Availability changes often and may be incomplete. Not listed doesn't mean unavailable."
- **Freshness:** a quiet "Updated 3 days ago" from the Movie's Availability refresh time; a "may be out of date" notice when the data is `stale`. The refresh interval is set by the cache-lifetimes patch.
- **Trending:** stays global and is labelled just "Trending". The country affects only Availability, never Discover rows.

- From [Cache lifetimes and refresh schedule](./26-cache-lifetimes-and-refresh.md) (resolved): the freshness line reads the Movie's `synced_at` ("Updated today / yesterday / N days ago"). The "may be out of date" notice shows when Availability is more than 7 days old. Availability is refreshed on a detail view when more than 24 h old, every 3 days for Watchlist Movies, and every 30 days for Watched-only Movies. The Movie picker's Availability block carries the same line.
