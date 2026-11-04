# TMDB API coverage for MVP features

Type: research
Status: resolved
Map: [Reelio MVP](../map.md)

## Question

Which TMDB v3 endpoints and fields cover each P0 feature, and which limits will shape the design?

- Homepage sections: trending, popular, top-rated, upcoming, genre collections. Endpoints, page sizes, region and language parameters, how duplicates arise across pages.
- Search: partial-title matching behavior, pagination and page caps, and filtering out adult content.
- Movie detail: fields available, `append_to_response` (credits, videos, watch/providers, release_dates, external_ids), how to find the director, how to pick the "official trailer" from videos, and embed rules for trailers on YouTube and other sites.
- Availability: the response shape of watch/providers, how stream/rent/buy are categorized, the supported regions list, what link is provided (per-title deep link vs a JustWatch page), and logo assets.
- Images: configuration endpoint, available sizes, URL construction.
- Operational: rate limits, auth methods (v3 key vs v4 read token), error shapes, and whether ratings beyond TMDB's own vote_average and vote_count exist (feeds [OMDb ratings](./03-omdb-ratings.md)).

## Answer

- **Every P0 feature is covered by TMDB v3:**
    - Homepage: `/trending/movie/{day|week}`, `/movie/popular`, `/movie/top_rated`, `/movie/upcoming`, and `/discover/movie?with_genres=` (genre names from `/genre/movie/list`).
    - Search: `/search/movie`.
    - Detail page: one `/movie/{id}?append_to_response=credits,videos,release_dates,watch/providers` call (20 appends max).
- **Pagination:** every list returns 20 items per page, and `page` is capped at 500 (error 22). Responses still report a larger `total_pages`, so paginators must clamp.
- **Duplicates across pages:** no doc explains them. They most likely come from popularity and trending scores changing between page fetches. Dedupe by TMDB `id` and cache a section's pages together.
- **Country scope:** trending has no `region`, so it is global trending. `region` on popular, top-rated and upcoming only filters on regional release dates.
- **Upcoming:** without `region`, the "upcoming" list can show primary release dates in the past.
- **Search:** matches original, translated and alternative titles, and `include_adult` defaults to false. Partial-word or prefix matching isn't documented, so check it with a key. Trending has no adult filter, so also drop `adult: true` items server-side.
- **Detail fields:** `/movie/{id}` has every FR-004 field and every column of PRD §8.2, plus `imdb_id`.
- **Director:** `credits.crew[]` entries with `job == "Director"`. Allow more than one.
- **Trailer:** `videos.results[]` has no documented order. Rank Trailer (else Teaser), then official, then YouTube/Vimeo, then language match, then earliest `published_at`.
    - `include_video_language` is documented only in a TMDB staff forum post.
    - YouTube embeds: at least 200×200 px, no overlays, the Referer header must be sent (don't use a global `no-referrer` policy), `youtube-nocookie` is available, and a fallback link is needed when embedding is disabled.
- **Availability:** `/movie/{id}/watch/providers` returns every country in one call (no region parameter): `results.{CC}.{link, flatrate[], rent[], buy[]}`, sometimes with `ads[]` or `free[]`.
    - Each entry is `{provider_id, provider_name, logo_path, display_priority}`.
    - Mapping: `flatrate` → subscription stream, `rent` → rental, `buy` → purchase. `free` and `ads` are an open decision.
    - Supported countries come from `/watch/providers/regions` (120 in the doc example).
- **Availability links:** there are no per-title deep links. There is one `link` per country, and it points to TMDB's `/movie/{id}-{slug}/watch?locale=XX` page. JustWatch attribution is mandatory, and TMDB threatens to revoke API access if it's missing.
- **Images:** `secure_base_url` + size + path (`https://image.tmdb.org/t/p/w500/…`).
    - Posters: `w92`–`w780` or `original`.
    - Backdrops: `w300`, `w780`, `w1280` or `original`.
    - Logos: `w45`–`w500`.
- **Operational:**
    - Auth: a Bearer read token or `api_key`, with equal access. Use Bearer from the backend so the key stays out of URLs and logs.
    - Rate limit: about 40 requests per second with HTTP 429 (error 25). No SLA.
    - Errors: `{status_code, status_message, success:false}`.
    - Refresh: `/movie/changes` (14-day window) can drive refresh jobs.
- **Ratings:** TMDB has only `vote_average` and `vote_count`. No IMDb, Rotten Tomatoes or Metacritic scores, so OMDb is still needed, joined on `imdb_id`.

Full findings: branch research/tmdb-api-coverage, file .scratch/reelio-mvp/research/tmdb-api-coverage.md (https://github.com/rfookune/reelio/blob/research/tmdb-api-coverage/.scratch/reelio-mvp/research/tmdb-api-coverage.md)
