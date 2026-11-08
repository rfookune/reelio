# TMDB data shape for TV Shows

Ticket: [02 - What TMDB offers for TV Shows](../issues/02-tmdb-tv-data-shape.md). Findings only; no recommendations.

## Method and limits

- Researched 2026-11-08 against TMDB's first-party documentation: the developer docs at developer.themoviedb.org and the OpenAPI reference that site publishes at <https://developer.themoviedb.org/openapi/tmdb-api.json> (API version "3"). Field lists, parameters and example payloads below come from that OpenAPI file unless another source is named. Call it **[OpenAPI]**.
- No live API calls were made (no API key was used; `.env` was not read). Everything is therefore what the docs and their example payloads say, not observed behaviour. Example payloads in the OpenAPI file are illustrative (mostly Game of Thrones, series id 1399).
- The OpenAPI schemas are loose: many fields have no nullability marked even though examples show `null`, and some types disagree with examples. Where this matters it is noted.
- The repo has no `.scratch/reelio-mvp/research/` directory and no `tmdb-fixtures/` directory on this branch, so no existing convention was found to match. This file sits at the path the ticket asked for.
- Docs pages cited: [Rate Limiting](https://developer.themoviedb.org/docs/rate-limiting), [Append To Response](https://developer.themoviedb.org/docs/append-to-response), [Popularity & Trending](https://developer.themoviedb.org/docs/popularity-and-trending), [Finding Data](https://developer.themoviedb.org/docs/finding-data), and per-endpoint reference pages under `https://developer.themoviedb.org/reference/`. Also the TMDB "Bible" page <https://www.themoviedb.org/bible/tv> (first-party, on themoviedb.org, not the developer site).

## 1. Endpoints and fields

All paths are under `https://api.themoviedb.org`, authenticated with an `Authorization` bearer header [OpenAPI `components.securitySchemes`].

### TV Show: `GET /3/tv/{series_id}` (`tv-series-details`)

Parameters: `series_id` (path, int), `append_to_response` (string, "comma separated list of endpoints within this namespace, 20 items max"), `language` (default `en-US`). [OpenAPI; [reference](https://developer.themoviedb.org/reference/tv-series-details)]

Response fields: `adult`, `backdrop_path`, `created_by[]` (`id`, `credit_id`, `name`, `gender`, `profile_path`), `episode_run_time[]`, `first_air_date`, `genres[]`, `homepage`, `id`, `in_production`, `languages[]`, `last_air_date`, `last_episode_to_air` (object: `id`, `name`, `overview`, `vote_average`, `vote_count`, `air_date`, `episode_number`, `production_code`, `runtime`, `season_number`, `show_id`, `still_path`), `name`, `next_episode_to_air` (no type declared; example is `null`), `networks[]`, `number_of_episodes`, `number_of_seasons`, `origin_country[]`, `original_language`, `original_name`, `overview`, `popularity`, `poster_path`, `production_companies[]`, `production_countries[]`, `seasons[]`, `spoken_languages[]`, `status`, `tagline`, `type`, `vote_average`, `vote_count`. [OpenAPI]

`seasons[]` items (a summary, not the full Season): `air_date`, `episode_count`, `id`, `name`, `overview`, `poster_path`, `season_number`, `vote_average`. `vote_average` is typed `integer` but the example values are decimals. [OpenAPI]

`status` example: "Returning Series"; `type` example: "Scripted". [OpenAPI] The documented value sets are on the TMDB Bible page:
- Types: "Documentary, News, Miniseries, Reality, Scripted, Talk Show and Video."
- Statuses: Pilot, In Production, Returning Series ("status we use from the premiere to the very last episode of a series"), Cancelled ("cancelled by the network"), Ended ("the series is over and the end was planned in advance"). [<https://www.themoviedb.org/bible/tv>]

Other TV Show sub-resources exist in the same namespace (usable standalone or via `append_to_response`): `account_states`, `aggregate_credits`, `alternative_titles`, `changes`, `content_ratings`, `credits`, `episode_groups`, `external_ids`, `images`, `keywords`, `lists`, `recommendations`, `reviews`, `screened_theatrically`, `similar`, `translations`, `videos`, `watch/providers`. [OpenAPI paths list]

### Season: `GET /3/tv/{series_id}/season/{season_number}` (`tv-season-details`)

Parameters: `series_id`, `season_number` (path, int), `append_to_response` (same 20-item wording), `language`. [OpenAPI; [reference](https://developer.themoviedb.org/reference/tv-season-details)]

Response fields: `_id` (string), `id`, `name`, `overview`, `air_date`, `poster_path`, `season_number`, `vote_average` (number), `networks[]`, and `episodes[]`. [OpenAPI]

Each `episodes[]` item carries: `air_date`, `episode_number`, `episode_type` (string; example values in the file are "standard" and "finale"), `id`, `name`, `overview`, `production_code`, `runtime`, `season_number`, `show_id`, `still_path`, `vote_average`, `vote_count`, `crew[]`, `guest_stars[]`. [OpenAPI] So one Season request returns every Episode of that Season, with episode-level ratings.

Season sub-resources: `account_states`, `aggregate_credits`, `credits`, `external_ids`, `images`, `translations`, `videos`, `watch/providers`; changes are at `/3/tv/season/{season_id}/changes`. [OpenAPI paths list]

### Episode: `GET /3/tv/{series_id}/season/{season_number}/episode/{episode_number}` (`tv-episode-details`)

Parameters: three path ints, `append_to_response`, `language`. [OpenAPI; [reference](https://developer.themoviedb.org/reference/tv-episode-details)]

Response fields: `air_date`, `episode_number`, `id`, `name`, `overview`, `production_code`, `runtime`, `season_number`, `still_path`, `vote_average`, `vote_count`, `crew[]`, `guest_stars[]`. The single-Episode schema does not list `episode_type` or `show_id`, which the Season endpoint's `episodes[]` items do list. [OpenAPI, comparing the two schemas]

Episode sub-resources: `account_states`, `credits`, `external_ids`, `images`, `translations`, `videos`, `rating`; changes at `/3/tv/episode/{episode_id}/changes`. There is no Episode `watch/providers` endpoint. [OpenAPI paths list]

### Identity of Seasons and Episodes

Seasons and Episodes each have their own numeric `id`, and are also addressed by `(series_id, season_number[, episode_number])` in the detail endpoints. The changes endpoints for Seasons and Episodes are addressed by `season_id` / `episode_id` instead. [OpenAPI paths and parameters]

## 2. Watch providers and where Availability exists

- **TV Show level**: `GET /3/tv/{series_id}/watch/providers`. "Get the list of streaming providers we have for a TV show." [OpenAPI; [reference](https://developer.themoviedb.org/reference/tv-series-watch-providers)]
- **Season level**: `GET /3/tv/{series_id}/season/{season_number}/watch/providers`. "Get the list of streaming providers we have for a TV season." [OpenAPI; [reference](https://developer.themoviedb.org/reference/tv-season-watch-providers)]
- **Episode level**: no such endpoint exists in the OpenAPI paths list (the Episode namespace has only account_states, credits, external_ids, images, translations, videos, rating, and changes).
- Shape (both levels): `{ id, results: { <ISO-3166-1 country>: { link, flatrate[], buy[], rent[], free[], ads[] } } }`. Each provider entry has `provider_id`, `provider_name`, `logo_path`, `display_priority`. In the OpenAPI file the TV Show example contains the keys `link, flatrate, buy, rent, free, ads` across countries, while its declared schema only lists `link, free, buy, flatrate`; the Season example contains `link, flatrate, buy, rent` and its schema lists `link, flatrate, buy`. Not every category appears for every country. [OpenAPI, inspected from schemas and examples]
- Region and provider catalogue: `GET /3/watch/providers/tv` lists TV providers, with `watch_region` and `language` query parameters. Items have `provider_id`, `provider_name`, `logo_path`, `display_priority`, `display_priorities` (per-region). [OpenAPI]
- Attribution: the data is from JustWatch; the reference pages say JustWatch must be credited, that no deep links are returned, and that non-compliant usage can lose API access. [reference pages above]
- The Season endpoint example payload in the OpenAPI file is for series 1399, season id 3624. [OpenAPI]

## 3. External ids

- TV Show: `GET /3/tv/{series_id}/external_ids` returns `id`, `imdb_id`, `freebase_mid`, `freebase_id`, `tvdb_id`, `tvrage_id`, `wikidata_id`, `facebook_id`, `instagram_id`, `twitter_id`. [OpenAPI; [reference](https://developer.themoviedb.org/reference/tv-series-external-ids)]
- Season: `.../season/{season_number}/external_ids` returns `id`, `freebase_mid`, `freebase_id`, `tvdb_id`, `tvrage_id`, `wikidata_id`. No `imdb_id` field in the schema. [OpenAPI]
- Episode: `.../episode/{episode_number}/external_ids` returns `id`, `imdb_id`, `freebase_mid`, `freebase_id`, `tvdb_id`, `tvrage_id`, `wikidata_id`. [OpenAPI]
- So IMDb ids are exposed for TV Shows and Episodes, not Seasons.
- Reverse lookup: `GET /3/find/{external_id}?external_source=...`; allowed sources: `imdb_id, facebook_id, instagram_id, tvdb_id, tiktok_id, twitter_id, wikidata_id, youtube_id`. The response has `movie_results`, `person_results`, `tv_results`, `tv_episode_results`, `tv_season_results`. [OpenAPI]. The Finding Data doc page only says an IMDb id can be used to get "movies, TV shows, or people" and does not discuss which sources work for which type. [<https://developer.themoviedb.org/docs/finding-data>]
- The external-id endpoints are also members of the `append_to_response` namespace (section 8).

## 4. Ratings

- TMDB's own community rating is `vote_average` (number) plus `vote_count` on the TV Show; `vote_average` and `vote_count` on every Episode (in both the Season `episodes[]` and Episode detail); and a `vote_average` (no `vote_count`) on the Season object, and on Season summaries inside the TV Show's `seasons[]`. `last_episode_to_air` also carries episode `vote_average`/`vote_count`. [OpenAPI schemas]
- No IMDb, Rotten Tomatoes or other third-party rating appears in any TV schema; IMDb is only available as an id (section 3). [OpenAPI]
- Age ratings are separate: `GET /3/tv/{series_id}/content_ratings` returns `{ id, results: [ { iso_3166_1, rating, descriptors[] } ] }`. [OpenAPI; [reference](https://developer.themoviedb.org/reference/tv-series-content-ratings)]
- User rating endpoints (`POST/DELETE /3/tv/{series_id}/rating`, and the Episode equivalent; none for Seasons) and account-state endpoints exist but are tied to a TMDB account or guest session. [OpenAPI paths list]

## 5. Search and trending returning mixed movie and TV results

- `GET /3/search/multi`: "Use multi search when you want to search for movies, TV shows and people in a single request." Params: `query` (required), `include_adult` (default false), `language`, `page`. Result items share `adult, backdrop_path, id, original_language, overview, poster_path, media_type, genre_ids, popularity, vote_average, vote_count`. Movie items use `title`, `original_title`, `release_date`, `video`; TV items use `name`, `original_name`, and (in examples, not in the schema) `first_air_date`, `origin_country`. The `media_type` values shown are "movie" and "tv"; the schema has no enum, and person results are not documented in the examples. [OpenAPI; [reference](https://developer.themoviedb.org/reference/search-multi)]
- `GET /3/trending/all/{time_window}` (`day` or `week`): "Get the trending movies, TV shows and people." Items carry `media_type`; the declared schema is movie-shaped (`title`, `original_title`, `release_date`, `video`), and TV items use `name`, `original_name`, `first_air_date`, `origin_country` as seen in examples. Also available: `/3/trending/tv/{time_window}` ("Get the trending TV shows on TMDB"), `/trending/movie/...`, `/trending/person/...`. [OpenAPI; [reference](https://developer.themoviedb.org/reference/trending-all)]
- TV-only search: `GET /3/search/tv` ("Search for TV shows by their original, translated and also known as names"), with `query`, `first_air_date_year`, `year` ("Search the first air date and all episode air dates"), `include_adult`, `language`, `page`. [OpenAPI]
- Trending and popularity: popularity is "a lifetime popularity score" computed differently from trending; for TV Shows its inputs match movies but use "Next/last episode to air date" instead of release date. Trending is tracked on shorter windows (daily, weekly). Update frequency is not stated. [<https://developer.themoviedb.org/docs/popularity-and-trending>]
- Other TV list endpoints: `/3/tv/popular`, `/3/tv/top_rated`, `/3/tv/airing_today` ("TV shows airing today"), `/3/tv/on_the_air` ("TV shows that air in the next 7 days"), both with a `timezone` parameter, and `/3/discover/tv`. [OpenAPI]
- The Search and Query docs page does not describe multi search or pagination limits. [<https://developer.themoviedb.org/docs/search-and-query-for-details>]

## 6. Season numbering and specials (Season 0)

- The TV Show's `seasons[]` in the OpenAPI example includes an entry named "Specials" with `season_number: 0`, `episode_count: 272`, alongside seasons 1 to 6 (episode counts and ids in that example are illustrative). [OpenAPI, `tv-series-details` example]
- Season and Episode numbers are plain integers, and a Season is fetched by `season_number` (so `/season/0` is the addressing form for specials). The official docs do not define Season 0 explicitly. Neither the developer docs nor the Bible TV page contains a written rule on Season 0 or on how specials are categorised (searched; nothing found). [OpenAPI; <https://www.themoviedb.org/bible/tv>]
- `number_of_seasons` and `number_of_episodes` on the TV Show: the docs do not say whether they include Season 0. This is undocumented.
- TMDB also offers alternative orderings: `GET /3/tv/{series_id}/episode_groups` lists groups (`id`, `name`, `description`, `episode_count`, `group_count`, `network`, `type` as an unexplained integer), and `GET /3/tv/episode_group/{tv_episode_group_id}` returns groups with `episodes`. The meaning of the `type` integer is not documented. [OpenAPI; [reference](https://developer.themoviedb.org/reference/tv-series-episode-groups)]
- Community discussion on themoviedb.org Talk (not official documentation, written by ordinary users per the page) says the Season 0 on TMDB mixes recaps, making-ofs and other specials, and that guidance on categorising them is lacking ("The TMDB Bible is currently insufficient to the task..."). [<https://www.themoviedb.org/talk/65003246d7dcd200acb03370>] Treat as context only.

## 7. How data changes while a TV Show is airing

What the docs state or imply:
- `status` moves through the documented values (Returning Series means "from the premiere to the very last episode"); `in_production` is a boolean. `last_episode_to_air` and `next_episode_to_air` live on the TV Show object; the example `next_episode_to_air` is `null`. [OpenAPI; Bible TV page]
- `number_of_episodes`, `number_of_seasons`, and `seasons[].episode_count` are fields on the TV Show, so new Episodes change them. (Inference from the field list; the docs do not describe the update mechanics.)
- `episode_type` on Season `episodes[]` has example values "standard" and "finale". [OpenAPI]
- Vote averages and popularity are live community scores. The popularity doc says a TV Show's popularity uses next/last episode air date as an input. [popularity doc]
- Change feeds exist: `GET /3/tv/changes` (TV Show ids changed; 100 items per page; "up to 14 days at a time"), `GET /3/tv/{series_id}/changes` (default last 24 hours, up to 14 days via `start_date`/`end_date`, with `page`), `GET /3/tv/season/{season_id}/changes`, `GET /3/tv/episode/{episode_id}/changes`. The TV Show changes page says some Season and Episode edits also appear at show level under `season` and `episode` keys, including `series_id` and `episode_id`. Season changes include keys such as `episode`, `air_date`, `name`, `overview`, with actions such as `added`, `updated`, `deleted`. [OpenAPI; references [tv-series-changes](https://developer.themoviedb.org/reference/tv-series-changes), [changes-tv-list](https://developer.themoviedb.org/reference/changes-tv-list), [tv-season-changes-by-id](https://developer.themoviedb.org/reference/tv-season-changes-by-id)]
- Not documented: how quickly new Episodes or air dates are added, whether Episode ids are stable if an Episode is renumbered or moved, and when `next_episode_to_air` is populated or cleared.

## 8. Requests needed to fetch a whole TV Show

Documented facts:
- `append_to_response` is supported on the movie, TV show, TV season, TV episode and person detail methods. "Each request will get appended to the response as a new JSON object." Sub-requests accept their own query parameters. The `append_to_response` parameter text in the OpenAPI file reads "comma separated list of endpoints within this namespace, 20 items max". The Append To Response docs page itself does not state a limit, nesting rules, or give TV examples (its examples are movie requests, e.g. `/movie/11?append_to_response=videos,images`). [OpenAPI parameter text; [Append To Response](https://developer.themoviedb.org/docs/append-to-response)]
- Season details return all Episodes of a Season in one response (section 1).
- The TV Show detail response lists Seasons only as summaries, with no Episodes. [OpenAPI]

Counting (derived from the above, not stated by TMDB). For a TV Show with N Seasons including Season 0 if present:
- One request for the TV Show (with `append_to_response=external_ids,content_ratings,watch/providers`, all within the namespace listed in section 1) plus N Season requests (one per `season_number`) gives show, Seasons, Episodes, external ids (Episode external ids are not in the Season response and need one request each, or an append of `external_ids` at Episode level), and show-level watch providers: about 1 + N requests.
- Season-level watch providers and Season external ids are separate sub-resources of the Season namespace; they can be appended to a Season request via `append_to_response` if the same namespace rule applies to them (the docs state the namespace rule, but do not enumerate which names are valid for Seasons).
- Whether `season/1,season/2,...` can be appended on the TV Show request itself is **not documented** on the pages read. The docs say sub-requests are "within the same namespace in a single HTTP request" and the endpoint text says "endpoints within this namespace", and give no season example. This was not verified live.
- Episode-level IMDb ids need one request per Episode (`.../episode/{n}/external_ids`) or an Episode-detail request with `append_to_response=external_ids`; no list-level equivalent appears in the Season schema. [OpenAPI]

## 9. Rate limits

- "As of December 16, 2019, we have disabled the original API rate limiting (40 requests every 10 seconds.)"
- "While our legacy rate limits have been disabled for some time, we do still have some upper limits ... They sit somewhere in the 40 requests per second range."
- "This limit could change at any time so be respectful of the service we have built and respect the `429` if you receive one."
[<https://developer.themoviedb.org/docs/rate-limiting>]
- Nothing in the docs states whether an appended sub-request counts as one request or many against the limit. The FAQ page does not address rate limits. [<https://developer.themoviedb.org/docs/faq>]

## Gaps (not answerable from first-party docs read)

- Season 0 rules and whether show-level counts include it.
- Maximum nesting/validity of `season/N` appends on the TV Show endpoint.
- Whether appended sub-requests count individually toward rate limits.
- Update latency and stability of Season/Episode ids while a TV Show airs.
- Meaning of the `type` integer on episode groups.
- Nullability of fields (the OpenAPI schemas do not mark it; examples show `null` for e.g. `next_episode_to_air`, `logo_path`).
