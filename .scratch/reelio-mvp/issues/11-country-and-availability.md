# Country and Availability presentation

Type: grilling
Status: open
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
