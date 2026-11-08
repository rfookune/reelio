# What TMDB offers for TV Shows

Type: research
Blocked by: none
Status: resolved

## Question

Against TMDB's official docs and API: the endpoints and fields for a TV Show, its Seasons and Episodes; watch providers and whether Availability exists per TV Show, Season or Episode; external ids (IMDb); ratings; trending and search results mixing movies and TV; how Seasons are numbered (specials, Season 0); how the data changes while a TV Show is airing; request volume to fetch a whole TV Show; rate limits. Findings only, cited to their sources.

## Answer

Findings are on the throwaway branch `research/tv-shows-tmdb-data-shape` (commit `cbfc240`, pushed, never merged), in `.scratch/tv-shows/research/tmdb-tv-data-shape.md`. Read it with `git show origin/research/tv-shows-tmdb-data-shape:.scratch/tv-shows/research/tmdb-tv-data-shape.md`. It comes from TMDB's developer docs and published OpenAPI file only; no live API calls were made (no key), so nothing is observed behaviour.

- TV Show details list Season summaries (including a Season 0 "Specials") and the last and next Episode to air; Season details return every Episode.
- Watch providers exist at TV Show and Season level, not Episode level.
- `imdb_id` is exposed for TV Shows and Episodes, not Seasons. TMDB gives no IMDb score.
- `search/multi` and `trending/all` mix movies and TV, told apart by `media_type`, with different field names (`title`/`release_date` against `name`/`first_air_date`).
- Fetching a whole TV Show takes about 1 + one request per Season (the research's derivation); whether `season/N` can be appended on the TV Show request is not documented.
- Change feeds cover the TV Show list, one TV Show, one Season and one Episode, for up to 14 days at a time. Update latency is not documented.
- The old 40-per-10-seconds limit is disabled, and the docs say the real ceiling is "somewhere in the 40 requests per second range". Whether appended sub-requests count is not documented.
- The file ends with a Gaps section for what first-party docs do not answer (Season 0 rules, count semantics, update latency, id stability).
