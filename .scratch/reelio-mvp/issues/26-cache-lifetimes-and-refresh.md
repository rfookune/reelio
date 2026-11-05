# Cache lifetimes and refresh schedule

Type: grilling
Status: open
Map: [Reelio MVP](../map.md)

## Question

What are the concrete lifetimes and schedules for TMDB- and IMDb-derived data?

- Cache TTL per data kind (lists, search, detail-only data, configuration) and how long a `stale` entry may be served ([Catalog and Rating source boundary](./10-source-boundary.md)).
- Metadata refresh cadence for stored Movies within TMDB's 6-month cap, with a faster cadence for rows that carry user facts.
- The Availability refresh interval, and the purge period for unviewed Movie rows ([Movie identity, URLs and local storage](./09-movie-identity-and-storage.md)), including rows created by a save from a list and never viewed ([Search experience](./23-search-experience.md)).
- The IMDb import time of day, and how refresh work is chunked into jobs under 90 seconds on Flex managed queues ([Deployment and operations](./19-deployment-and-operations.md)).
- What the freshness line on Availability shows ([Country and Availability presentation](./11-country-and-availability.md)).
