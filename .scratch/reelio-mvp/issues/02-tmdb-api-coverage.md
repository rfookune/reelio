# TMDB API coverage for MVP features

Type: research
Status: open
Map: [Reelio MVP](../map.md)

## Question

Which TMDB v3 endpoints and fields cover each P0 feature, and which limits will shape the design?

- Homepage sections: trending, popular, top-rated, upcoming, genre collections. Endpoints, page sizes, region and language parameters, how duplicates arise across pages.
- Search: partial-title matching behavior, pagination and page caps, and filtering out adult content.
- Movie detail: fields available, `append_to_response` (credits, videos, watch/providers, release_dates, external_ids), how to find the director, how to pick the "official trailer" from videos, and embed rules for trailers on YouTube and other sites.
- Availability: the response shape of watch/providers, how stream/rent/buy are categorized, the supported regions list, what link is provided (per-title deep link vs a JustWatch page), and logo assets.
- Images: configuration endpoint, available sizes, URL construction.
- Operational: rate limits, auth methods (v3 key vs v4 read token), error shapes, and whether ratings beyond TMDB's own vote_average and vote_count exist (feeds [OMDb ratings](./03-omdb-ratings.md)).
