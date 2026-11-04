# TMDB API coverage for MVP features

Research for [TMDB API coverage for MVP features](../issues/02-tmdb-api-coverage.md). Researched on 2026-11-04 from TMDB's developer docs (pages stamped `updatedAt` 2025-10-01 to 2025-12-12), the TMDB API Terms of Use, TMDB's contribution guide, and YouTube's and Vimeo's embed docs. No API key was used, so every response shape below comes from documented schemas and example responses, not live calls. Where a fact comes from a TMDB staff forum post and not the reference, the text says so.

## Answer

- **Every P0 feature is covered by TMDB v3.** Homepage sections use `/trending/movie/{day|week}`, `/movie/popular`, `/movie/top_rated`, `/movie/upcoming` and `/discover/movie?with_genres=…`. Search uses `/search/movie`. The detail page is one `/movie/{id}?append_to_response=credits,videos,release_dates,watch/providers` call. Availability uses `/movie/{id}/watch/providers`. Images use `/configuration` plus `image.tmdb.org`.
- **Every list returns 20 items per page, and `page` is capped at 500.** Responses still report `total_pages` far above 500 (1,000 for trending, 38,029 for popular in the doc examples). Paginators must clamp to `min(total_pages, 500)`.
- **Trending can't be scoped by country.** Its documented parameters are only `time_window` and `language`, with no `region` and no `page`. Popular, top-rated and upcoming accept `region`, but `region` filters by release date. It does not mean "popular in that country".
- **Search matches original, translated and alternative titles** and defaults to `include_adult=false`. TMDB doesn't document partial-word or prefix matching, so verify it with a key before promising as-you-type search.
- **The Availability link is not a deep link.** Each country has one `link`, a TMDB page (`themoviedb.org/movie/{id}-{slug}/watch?locale=XX`), and the docs say outright that the endpoint does not return full deep links. JustWatch attribution is mandatory, and TMDB threatens to revoke API access if it's missing.
- **Watch providers returns every country in one response.** Each country has `flatrate` (subscription), `rent` and `buy` arrays, sometimes with `ads` or `free` arrays too. Each entry has `provider_id`, `provider_name`, `logo_path` and `display_priority`. `/watch/providers/regions` lists the supported countries (120 in the doc example).
- **Director:** `credits.crew[]` entries where `job == "Director"`. There can be several.
- **Trailer:** `videos.results[]` has `site`, `key`, `type`, `official`, `iso_639_1`, `size` and `published_at`, in no documented order. Reelio has to choose one deterministically. Only YouTube and Vimeo appear as sites, and embedding YouTube brings YouTube's own player rules.
- **Ratings:** TMDB provides only `vote_average` (0–10) and `vote_count`. No IMDb, Rotten Tomatoes or Metacritic scores. `/movie/{id}` does return `imdb_id`, which is the join key for [OMDb ratings](../issues/03-omdb-ratings.md).
- **Operational:**
  - **Auth:** a Bearer "API Read Access Token" or an `api_key` query parameter, with equal access.
  - **Rate limit:** roughly 40 requests per second, with a 429 response. No SLA.
  - **Errors:** shaped as `{status_code, status_message, success:false}`.
  - **Caching:** the API terms cap caching at 6 months.

## Feature → endpoint/field map

All paths are relative to `https://api.themoviedb.org`. "List item" means the standard movie list object: `id, title, original_title, original_language, overview, release_date, poster_path, backdrop_path, genre_ids[], popularity, vote_average, vote_count, adult, video`.

| Reelio feature (PRD) | Endpoint | Fields used | Key parameters / notes |
|---|---|---|---|
| Homepage: Trending (FR-001) | `GET /3/trending/movie/{time_window}` | List item + `media_type` | `time_window` = `day`/`week`; `language`. No `region`, no documented `page` |
| Homepage: Popular | `GET /3/movie/popular` | List item | `language`, `page`, `region`. Equivalent to `discover` sorted by `popularity.desc` |
| Homepage: Top rated | `GET /3/movie/top_rated` | List item | `language`, `page`, `region`. Equivalent to `discover` with `vote_count.gte=200`, `without_genres=99,10755`, sorted by `vote_average.desc` |
| Homepage: Upcoming | `GET /3/movie/upcoming` | List item + `dates.{minimum,maximum}` | `language`, `page`, `region`. Equivalent to `discover` with `with_release_type=2\|3` and a date window |
| Homepage: Genre collections | `GET /3/discover/movie?with_genres={id}` + `GET /3/genre/movie/list` | List item; `genres[{id,name}]` | `sort_by`, `vote_count.gte`, `region`, `with_release_type`, `include_adult=false` |
| Movie card (FR-001) | (any list above) | `poster_path`, `title`, `release_date` (→ year), `vote_average`, `vote_count` | `poster_path` can be null, so show the placeholder |
| Search (FR-002) | `GET /3/search/movie` | List item | `query` (required), `page`, `include_adult` (default false), `language`, `region`, `year`, `primary_release_year` |
| Detail (FR-004) | `GET /3/movie/{id}` | `title`, `original_title`, `overview`, `release_date`, `runtime`, `genres[]`, `poster_path`, `backdrop_path`, `vote_average`, `vote_count`, `imdb_id`, `status`, `tagline`, `original_language` | `append_to_response` (max 20), `language` |
| Cast & director (FR-004) | `credits` (append) / `GET /3/movie/{id}/credits` | `cast[]{id,name,character,order,profile_path}`; `crew[]{id,name,job,department}` | Director = `crew[].job == "Director"` |
| Trailer (FR-005) | `videos` (append) / `GET /3/movie/{id}/videos` | `results[]{key,site,type,official,iso_639_1,iso_3166_1,size,published_at,name}` | `language`; `include_video_language` (documented only in a staff forum post) |
| Availability (FR-016) | `watch/providers` (append) / `GET /3/movie/{id}/watch/providers` | `results.{CC}.{link, flatrate[], rent[], buy[], ads[], free[]}`, entries `{provider_id, provider_name, logo_path, display_priority}` | No region parameter, so every country comes back |
| Country picker (FR-016, FR-020) | `GET /3/watch/providers/regions` | `results[]{iso_3166_1, english_name, native_name}` | `language` |
| Streaming service catalog (P1 FR-017) | `GET /3/watch/providers/movie` | `provider_id`, `provider_name`, `logo_path`, `display_priorities{CC:n}` | `watch_region` |
| Primary rating (FR-022) | `/3/movie/{id}` or any list item | `vote_average`, `vote_count` | Label the source as TMDB |
| External ratings join (FR-023 → OMDb) | `/3/movie/{id}` | `imdb_id` | `/external_ids` also gives `imdb_id` and `wikidata_id` |
| Images (all) | `GET /3/configuration` | `images.secure_base_url`, `*_sizes[]` | URL = `secure_base_url + size + file_path` |
| Metadata refresh (map: not yet specified) | `GET /3/movie/changes` | `results[]{id, adult}` | `start_date`/`end_date` (≤ 14 days), 100 per page |

## Detailed findings

### 1. Homepage sections

**Endpoints and parameters.**

- **Trending: `GET /3/trending/movie/{time_window}`.**
  - `time_window` is `day` or `week` (default `day`), and `language` is the only other documented parameter.
  - The example response has `page: 1, total_pages: 1000, total_results: 20000` and 20 results.
  - [Trending reference](https://developer.themoviedb.org/reference/trending-movies)
- **Trending is a separate score from popularity.** The docs describe it as a popularity score with much shorter time windows ("daily, weekly") than the "lifetime" popularity score. [Popularity & Trending](https://developer.themoviedb.org/docs/popularity-and-trending)
- **Popular: `GET /3/movie/popular`.**
  - Parameters: `language` (default `en-US`), `page` (default 1) and `region` (ISO‑3166‑1).
  - The docs say: "This call is really just a discover call behind the scenes", equivalent to `discover/movie?include_adult=false&include_video=false&sort_by=popularity.desc`.
  - [Popular reference](https://developer.themoviedb.org/reference/movie-popular-list)
- **Top rated: `GET /3/movie/top_rated`.**
  - Same parameters as popular.
  - Equivalent to discover with `sort_by=vote_average.desc&without_genres=99,10755&vote_count.gte=200`.
  - [Top Rated reference](https://developer.themoviedb.org/reference/movie-top-rated-list)
- **Upcoming: `GET /3/movie/upcoming`.**
  - Same parameters as popular.
  - Equivalent to discover with `sort_by=popularity.desc&with_release_type=2|3&release_date.gte={min_date}&release_date.lte={max_date}`.
  - Returns a `dates: {maximum, minimum}` object.
  - [Upcoming reference](https://developer.themoviedb.org/reference/movie-upcoming-list)
- **Genre collections: `GET /3/discover/movie`, using `with_genres`.**
  - `with_genres` takes comma for AND and pipe for OR.
  - Other parameters: `sort_by` (popularity, vote_average, vote_count, primary_release_date, revenue, title, original_title, each asc/desc), `vote_count.gte`, `region`, `with_release_type`, `include_adult` (default false) and `with_watch_providers`/`watch_region`.
  - [Discover reference](https://developer.themoviedb.org/reference/discover-movie)
- **Genre IDs and names: `GET /3/genre/movie/list`.** It returns 19 genres in the example and takes `language`. [Genre list](https://developer.themoviedb.org/reference/genre-movie-list)

**Page size and page cap.**

- Every list example returns 20 results per page. None of the reference pages states the page size as a contract, so 20 is observed, not guaranteed. [Popular](https://developer.themoviedb.org/reference/movie-popular-list), [Search](https://developer.themoviedb.org/reference/search-movie)
- Error code 22 (HTTP 400): "Invalid page: Pages start at 1 and max at 500." [Errors](https://developer.themoviedb.org/docs/errors)
- `total_pages` in responses ignores that cap: popular reports 38,029 and trending 1,000. A TMDB forum moderator confirms "you will only be able to access up to page 500" and that no change is planned. [Forum thread](https://www.themoviedb.org/talk/66f6d91fb9fd27627950d0b4)

**Language and region.**

- **`language`** is `ISO-639-1` plus `ISO-3166-1` (e.g. `en-US`, `pt-BR`) and defaults to `en-US`. Person names and character names are not translated. [Languages](https://developer.themoviedb.org/docs/languages)
- **`region`** controls which release dates are used. It does not make a list "popular in country X":
  - On search it is "simply acting as a presentation filter": the regional release date is shown when one exists, and the primary release date otherwise.
  - On discover without `with_release_type`, it "simply acts as a filter looking for any movies that … has at a minimum, one matching release date for the country specified".
  - [Regions](https://developer.themoviedb.org/docs/region-support)
  - Since popular, top-rated and upcoming are documented as discover calls, `region` most likely has the same meaning on them. That is an inference: their reference pages describe `region` only as "ISO-3166-1 code".
- **Discover with `region`** uses the regional release date in place of the primary one, and the order of release types matters: `2|3` yields the limited theatrical date, `3|2` the wide one. [Discover](https://developer.themoviedb.org/reference/discover-movie)
- **Trending has no `region` parameter**, so a US-first trending row is global trending. [Trending](https://developer.themoviedb.org/reference/trending-movies)

**How duplicates arise across pages. No TMDB doc covers this, so the following is inference.**

- **Popularity changes between fetches.** It is recomputed from that day's votes, views, favourites and watchlist adds, plus the previous day's score. [Popularity & Trending](https://developer.themoviedb.org/docs/popularity-and-trending) Two pages of the same list fetched at different times can therefore overlap or skip items as rankings move. Trending's short windows make this worse.
- **Ties under one sort key have no documented tie-breaker.** `vote_average.desc` on top-rated has many movies at 8.7 in the example. Rows can swap across a page boundary.
- **Movies can appear in several sections.** A movie can be in trending, popular and a genre row at once. The PRD only forbids duplicates within a section.

**Surprise in the upcoming example.**

- The example has `dates.minimum = 2023-05-04` but includes *Evil Dead Rise* with `release_date: 2023-04-12`. [Upcoming](https://developer.themoviedb.org/reference/movie-upcoming-list)
- Without `region`, the window seems to match any country's theatrical release, while the displayed `release_date` is the primary date. "Upcoming" rows can therefore show dates in the past.
- Passing `region=US` should align the two, per the discover note on regional release dates. Verify this with a key.

**Ambiguities to flag.**

- **Trending `page`.** The trending reference lists no `page` parameter, but the response has `total_pages`.
- **The 10755 genre ID.** Top-rated's `without_genres=99,10755` excludes genre 10755, which is not in the public genre list (99 is Documentary).
- **Upcoming window.** The `{min_date}`/`{max_date}` window for upcoming is never defined. The example spans 19 days.

**Design implications.**

- **Dedupe by TMDB `id`** after merging any multi-page fetch for a section, and fetch and cache a section's pages together. Never stitch pages cached at different times.
- **Cap section size** at a page or two (20–40 items). The homepage never needs deep pagination.
- **Decide trending scope.** Use `/trending/movie/week` as global trending (the window is a product choice), or drop trending in favour of region-scoped popular. This feeds the "Homepage curation" item on the map.
- **Build genre collections with discover.** Use `with_genres` plus a `vote_count.gte` floor so obscure titles don't dominate, and always pass `include_adult=false`.

### 2. Search

- **Endpoint: `GET /3/search/movie`.**
  - Parameters: `query` (required), `include_adult` (default `false`), `language` (default `en-US`), `primary_release_year`, `page` (default 1), `region` and `year`.
  - It searches "by their original, translated and alternative titles".
  - The example returns 20 per page (`total_results: 39`, `total_pages: 2` for "Fight Club").
  - [Search Movie reference](https://developer.themoviedb.org/reference/search-movie)
  - "You provide a query string and we provide the closest match. Searching by text takes into account all original, translated, alternative names and titles." [Finding Data](https://developer.themoviedb.org/docs/finding-data)
- **Partial-title matching isn't documented.** No TMDB page specifies prefix, substring or fuzzy semantics. TMDB support has described it as "more of a fuzzy search" and suggested adding a year to sharpen results. [Forum thread](https://www.themoviedb.org/talk/5ff17aad1fb94f003ef540f4) Whether "inter" finds "Interstellar" is unverified, so check it with a key before relying on it for debounced as-you-type search (FR-002).
- **The page cap is the same 500** as other lists ([Errors](https://developer.themoviedb.org/docs/errors), code 22). It barely matters for title search, but the paginator must still clamp.
- **Adult content.**
  - `include_adult` defaults to `false` on search and discover. [Search](https://developer.themoviedb.org/reference/search-movie), [Discover](https://developer.themoviedb.org/reference/discover-movie)
  - Every list item carries an `adult` boolean.
  - Trending has no `include_adult` parameter. [Trending](https://developer.themoviedb.org/reference/trending-movies)
  - Reelio should pass `include_adult=false` explicitly and also drop any item with `adult: true` server-side, which covers trending and any future change of default.
- **Design implications.**
  - Debounce on the client, but also rate-limit and cache search on the server, keyed by normalized query + page + language. Every keystroke otherwise becomes a TMDB call against a limit of about 40 requests per second shared by all users (§6).
  - Treat search results as display-only list items, and fetch details only when a movie is opened.

### 3. Movie detail

**Fields.**

- `GET /3/movie/{movie_id}` returns:
  - `adult`, `backdrop_path`, `belongs_to_collection`, `budget`, `genres[{id,name}]`, `homepage`, `id`, `imdb_id`, `origin_country[]`, `original_language`, `original_title`
  - `overview`, `popularity`, `poster_path`, `production_companies[]`, `production_countries[]`, `release_date`, `revenue`, `runtime`, `spoken_languages[]`
  - `status` (e.g. "Released"), `tagline`, `title`, `video`, `vote_average`, `vote_count`
  - [Movie Details reference](https://developer.themoviedb.org/reference/movie-details)
- That covers every FR-004 field and every column of PRD §8.2 `movies` (`title`, `original_title`, `overview`, `release_date`, `runtime`, `poster_path`, `backdrop_path`, `original_language`, `vote_average`, `vote_count`).

**`append_to_response`.**

- The movie, TV, season, episode and person detail methods accept a comma-separated list of sub-requests, and "each request will get appended to the response as a new JSON object". [Append To Response](https://developer.themoviedb.org/docs/append-to-response)
- The parameter is described as "20 items max" ([Movie Details](https://developer.themoviedb.org/reference/movie-details)). Error code 27 reads: "Too many append to response objects: The maximum number of remote calls is 20." ([Errors](https://developer.themoviedb.org/docs/errors))
- Sub-requests still honour their own parameters, notably `language` filtering images. [Append To Response](https://developer.themoviedb.org/docs/append-to-response)
- **Ambiguity:**
  - No fetched example shows the exact token or the response key for watch providers when appended. The sub-endpoint path is `/movie/{id}/watch/providers`, so the token would be `watch/providers`, and the response key is very likely the same string.
  - The docs never state either one. Confirm with a key, and fall back to a separate call if needed.
- **Cache freshness differs within one call.** One request for `credits,videos,release_dates,watch/providers` replaces five calls. Caching that one combined response means everything in it shares one TTL, although Availability changes much faster than credits.

**Sub-resources.**

- **Credits:** `GET /3/movie/{id}/credits`. [Credits reference](https://developer.themoviedb.org/reference/movie-credits)
  - `cast[]`: `{id, name, original_name, character, order, profile_path, known_for_department, credit_id, gender, popularity, adult}`, sorted by `order`.
  - `crew[]`: `{id, name, department, job, profile_path, …}`.
  - The Fight Club example has 76 cast entries and 175 crew entries, so trim them server-side.
- **Director lookup.** In the example the director appears as `"known_for_department":"Directing", "department":"Directing", "job":"Director"` (David Fincher).
  - Filter `crew` on `job === "Director"`, not `department === "Directing"`. That department also includes jobs other than Director.
  - Allow several directors (co-directed films) and dedupe by person `id`.
  - [Credits](https://developer.themoviedb.org/reference/movie-credits)
- **Release dates:** `GET /3/movie/{id}/release_dates`. [Release Dates reference](https://developer.themoviedb.org/reference/movie-release-dates)
  - Shape: `results[{iso_3166_1, release_dates[{certification, descriptors[], iso_639_1, note, release_date, type}]}]`.
  - Types: 1 Premiere, 2 Theatrical (limited), 3 Theatrical, 4 Digital, 5 Physical, 6 TV.
  - Use: a country-specific release date or certification. Not required by P0, but cheap to append.
- **External IDs:** `GET /3/movie/{id}/external_ids`. [External IDs reference](https://developer.themoviedb.org/reference/movie-external-ids)
  - Shape: `{id, imdb_id, wikidata_id, facebook_id, instagram_id, twitter_id}`.
  - Not needed for the IMDb join, because `imdb_id` is already on the details response.
- **Reverse lookup:** `GET /3/find/{external_id}?external_source=imdb_id`. [Find By ID](https://developer.themoviedb.org/reference/find-by-id)

**Trailers (FR-005).**

- **Shape.** `GET /3/movie/{id}/videos` returns `results[{iso_639_1, iso_3166_1, name, key, site, size, type, official, published_at, id}]`. [Videos reference](https://developer.themoviedb.org/reference/movie-videos)
  - The example lists two `Trailer`s on `YouTube`. The first has `official: false` (size 720), the second `official: true` (size 1080).
  - **The array has no documented order, and the first trailer is not necessarily the official one.**
- **Parameters.**
  - The reference documents only `language` (default `en-US`), which filters videos by language.
  - TMDB staff (Travis Bell, Jan 2024) confirmed that `include_video_language` exists and works like `include_image_language`, for example `en,null`. They said it was lost from the docs in a migration. [Forum thread](https://themoviedb.org/talk/65b332d057530e0161d9338e)
  - Without it, a non-English film with only an original-language trailer may return no videos under `language=en-US`.
- **Allowed values.**
  - `site`: "we only support YouTube and Vimeo right now". This is a 2020 staff reply quoted by a moderator in 2022. [Forum thread](https://themoviedb.org/talk/62db7e45ea84c7004fc5a8c7)
  - `type`: the contribution guide lists Trailer, Teaser, Clip, Behind the Scenes, Bloopers, Featurette and Opening Credits.
  - "Official" means videos from production companies or distributors, on channels that moderators mark as official.
  - Fan-made videos and full movies are not allowed.
  - [TMDB contribution bible: movie videos](https://www.themoviedb.org/bible/movie/59f3b16d9251414f2000000a)
- **Recommended selection.** This is a design choice, not a TMDB rule. Request `videos` with `include_video_language={ui_lang},{original_language},null`, then rank:
  1. `type == "Trailer"`, else fall back to `"Teaser"`.
  2. `official == true`.
  3. `site == "YouTube"`, then `"Vimeo"`. Drop anything else.
  4. `iso_639_1` matching the UI language, then the original language.
  5. Earliest `published_at`, to prefer the original trailer over late re-uploads. Break ties on larger `size`.

  If nothing qualifies, show no trailer.

**Embed rules (FR-005: don't download or rehost).**

- **YouTube embed URL:** `https://www.youtube.com/embed/{key}`. [YouTube player parameters](https://developers.google.com/youtube/player_parameters)
- **YouTube privacy-enhanced mode:** swap the domain to `https://www.youtube-nocookie.com`. In this mode, views are not used to personalize YouTube or ads. [YouTube Help 171780](https://support.google.com/youtube/answer/171780)
- **YouTube Required Minimum Functionality**, which is part of the YouTube API Terms. [Required Minimum Functionality](https://developers.google.com/youtube/terms/required-minimum-functionality)
  - The player viewport must be at least 200×200 px. 480×270 is recommended for 16:9.
  - No overlays or frames in front of any part of the player.
  - At most one autoplaying player per page.
  - **Embeds must send an `HTTP Referer`, and sites must not suppress it through Referrer-Policy.** `strict-origin-when-cross-origin` is recommended. A global `Referrer-Policy: no-referrer` in Reelio's security headers would break compliance, which matters for the map's "Rate limiting and security specifics" item.
- **Embedding can be disabled.** Uploaders can turn off "Allow embedding" ([YouTube Help 171780](https://support.google.com/youtube/answer/171780)). The UI needs a fallback "Watch on YouTube" link when the embed refuses.
- **Vimeo embed URL:** `https://player.vimeo.com/video/{key}`. `dnt=1` blocks session data and analytics collection. [Vimeo player parameters](https://help.vimeo.com/hc/en-us/articles/12426260232977-Player-parameters-overview)

### 4. Availability (watch providers)

- **Endpoint: `GET /3/movie/{movie_id}/watch/providers`.** It takes only `movie_id`, with no region parameter. [Watch Providers reference](https://developer.themoviedb.org/reference/movie-watch-providers)
  - The response is `{id, results: {CC: {link, flatrate?, rent?, buy?, ads?, free?}}}`, keyed by ISO‑3166‑1 country.
  - Each array entry is `{logo_path, provider_id, provider_name, display_priority}`.
  - The Fight Club example covers **95 countries**, and the doc page is about 360 KB. Fetch once per movie and pick out the user's country.
- **Categories.**
  - The schema shows `flatrate`, `rent` and `buy`. The example data also has `ads` in 2 countries.
  - Discover's `with_watch_monetization_types` lists the full enum: `flatrate, free, ads, rent, buy`. [Discover](https://developer.themoviedb.org/reference/discover-movie)
  - Mapping to Glossary **Availability**: `flatrate` → subscription stream, `rent` → rental, `buy` → purchase.
  - **Open decision:** `free` and `ads` (free with ads) fit none of the PRD's three categories. Show them as a fourth "Free" group, fold them under streaming, or drop them.
  - Empty categories are omitted, not sent as `[]` (AL has only `buy`). Countries with no data are absent from `results`. "Unavailable" therefore has two cases: no key for the country, or a key with no category arrays.
- **Link.**
  - The docs say: "This is *not* going to return full deep links, but rather, it's just enough information to display what's available where. You can link to the provided TMDB URL to help support TMDB and provide the actual deep links to the content." [Watch Providers](https://developer.themoviedb.org/reference/movie-watch-providers)
  - There is one `link` per country, never per Streaming service: `https://www.themoviedb.org/movie/550-fight-club/watch?locale=US`.
  - **Every Availability link in the MVP is therefore an indirect link to TMDB.** FR-018's rule applies: the UI must not imply that a service logo opens the title on that service.
  - Per-title deep links (P1 FR-018) would need a direct JustWatch partnership or another source. TMDB can't supply them.
- **Attribution.** "In order to use this data you must attribute the source of the data as **JustWatch**. If we find any usage not complying with these terms we will revoke access to the API." [Watch Providers](https://developer.themoviedb.org/reference/movie-watch-providers)
- **Supported countries: `GET /3/watch/providers/regions`.**
  - Returns `results[{iso_3166_1, english_name, native_name}]`, 120 countries in the doc example. [Available Regions](https://developer.themoviedb.org/reference/watch-providers-available-regions)
  - This is narrower than `/configuration/countries`, which has 251 entries. [Countries](https://developer.themoviedb.org/reference/configuration-countries)
  - The FR-020 country picker should offer the Availability regions list, or label countries outside it as "no streaming data".
- **Streaming service catalog: `GET /3/watch/providers/movie?watch_region=XX`.** [Movie Providers](https://developer.themoviedb.org/reference/watch-providers-movie-list)
  - Returns `{provider_id, provider_name, logo_path, display_priority, display_priorities{CC:n}}`, 529 entries in the example.
  - `provider_id` is TMDB's stable key for a Streaming service. This is the basis for P1 FR-017 preferences.
- **Logos.** `logo_path` values are `.jpg` paths on the image CDN (e.g. `/peURlLlr8jggOwK53fJ5wdQl05y.jpg`). Build URLs as in §5.
  - **Ambiguity:** the docs don't say which `*_sizes` list applies to Streaming service logos, which are square artwork rather than company logos. `logo_sizes` (`w45…w500`) is the natural reading.
- **Ordering.** Sort each category by the per-country `display_priority`, lowest first. That is TMDB's display order. [Watch Providers](https://developer.themoviedb.org/reference/movie-watch-providers)
- **Design implications.**
  - Cache Availability per movie, for all countries, with a shorter TTL than metadata.
  - Store `provider_id` with its source (PRD §7.4).
  - Render one "View on TMDB / JustWatch" link per country, plus the JustWatch attribution, near the Availability block.

### 5. Images

- **Configuration: `GET /3/configuration`.** The response has `images.base_url` (`http://image.tmdb.org/t/p/`), `images.secure_base_url` (`https://image.tmdb.org/t/p/`), the size lists below and `change_keys[]`. [Configuration reference](https://developer.themoviedb.org/reference/configuration-details)

| Kind | Sizes |
|---|---|
| poster | `w92, w154, w185, w342, w500, w780, original` |
| backdrop | `w300, w780, w1280, original` |
| logo | `w45, w92, w154, w185, w300, w500, original` |
| profile | `w45, w185, h632, original` |
| still | `w92, w185, w300, original` |

- **URL construction:** `secure_base_url + size + file_path`. For example, `https://image.tmdb.org/t/p/w500/1E5baAaEse26fej7uHcjOgEE2t2.jpg`. [Image Basics](https://developer.themoviedb.org/docs/image-basics)
  - Use `secure_base_url`, because `base_url` is plain http.
  - Company and network logos also come in SVG form. Request SVGs at `original`, because SVGs aren't resized. [Image Basics](https://developer.themoviedb.org/docs/image-basics)
- **Which artwork `poster_path` and `backdrop_path` return.**
  - `poster_path` picks the requested language's highest-rated poster, falling back to the original language's and then to the overall highest-rated. Regional variants such as `en-US` vs `en-GB` are not supported for images.
  - `backdrop_path` prefers the highest-rated backdrop with no language.
  - [Image Languages](https://developer.themoviedb.org/docs/image-languages)
- **Design implications.**
  - Fetch `/configuration` rarely and cache it. The docs give no change frequency, so a daily refresh is a reasonable guess.
  - Build image URLs server-side or in one helper, so the base URL and sizes stay in one place.
  - Map poster `w185`/`w342` and backdrop `w780`/`w1280` to breakpoints through `srcset` (feeds the map's "Images and performance budget").
  - Null `poster_path` or `backdrop_path` means show the placeholder (FR-001).

### 6. Operational

- **Authentication.**
  - v3 accepts either an `api_key` query parameter or the "API Read Access Token" sent as `Authorization: Bearer …`.
  - "Both authentication methods provide the same level of access." The Bearer token also works on v4.
  - [Authentication: Application](https://developer.themoviedb.org/docs/authentication-application)
  - `GET /3/authentication` validates a key. [Validate Key](https://developer.themoviedb.org/reference/authentication-validate-key)
  - **Recommendation:** use the Bearer read token from the backend only. It keeps the credential out of URLs and therefore out of access logs and HTTP-client exception messages (PRD §7.4).
- **Rate limits.**
  - The legacy limit of 40 requests per 10 seconds was disabled on 2019‑12‑16.
  - Upper limits remain "somewhere in the 40 requests per second range. This limit could change at any time … respect the `429` if you receive one."
  - [Rate Limiting](https://developer.themoviedb.org/docs/rate-limiting)
  - Error code 25 / HTTP 429: "Your request count (#) is over the allowed limit of (40)." [Errors](https://developer.themoviedb.org/docs/errors)
  - No `Retry-After` or rate-limit headers are documented, so back off with exponential delay plus jitter and a bounded number of retries.
- **No SLA.** "We do not currently provide an SLA." The status page is at status.themoviedb.org. [FAQ](https://developer.themoviedb.org/docs/faq) Graceful fallbacks (PRD §7.4) are therefore required, not optional.
- **Error shape.** `{"status_code": 7, "status_message": "Invalid API key: You must be granted a valid key.", "success": false}`. [Validate Key](https://developer.themoviedb.org/reference/authentication-validate-key) Codes to handle: [Errors](https://developer.themoviedb.org/docs/errors)

| Code | HTTP | Meaning | Handling |
|---|---|---|---|
| 6 / 34 | 404 | Invalid ID / resource not found | Treat as a missing movie |
| 7 / 3 / 10 | 401 | Invalid, unauthorized or suspended key | Alert. Retrying won't help |
| 22 | 400 | Page out of range (1–500) | Clamp the page |
| 25 | 429 | Over the rate limit | Back off and retry |
| 27 | 400 | More than 20 appends | Reduce the appends |
| 9 / 46 | 503 | Offline or maintenance | Retry, then fall back |
| 11 / 43 / 24 | 500 / 502 / 504 | Internal error, backend unreachable, or timeout | Retry, then fall back |

- **Format:** JSON only. [JSON & JSONP](https://developer.themoviedb.org/docs/json-and-jsonp)
- **Ratings beyond TMDB's own.**
  - None. The details and list objects carry only `vote_average` and `vote_count`. [Movie Details](https://developer.themoviedb.org/reference/movie-details)
  - TMDB exposes IMDb only as an identifier (`imdb_id`), and has no Rotten Tomatoes or Metacritic data. [External IDs](https://developer.themoviedb.org/reference/movie-external-ids)
  - The Rating sources IMDb, Rotten Tomatoes and Metacritic therefore have to come from another source (ticket 03), joined on `imdb_id`.
- **Refreshing stored movies.**
  - `GET /3/movie/changes` lists movie IDs changed in a window of up to 14 days (`start_date`/`end_date`), 100 per page. [Changes: Movie List](https://developer.themoviedb.org/reference/changes-movie-list)
  - Per-movie changes are at `/3/movie/{id}/changes`. [Movie Changes](https://developer.themoviedb.org/reference/movie-changes)
  - This supports a scheduled job that refreshes only stored movies that changed (feeds the map's "Cache lifetimes and metadata refresh").
- **Caching ceiling.**
  - The API Terms of Use forbid caching "for longer than 6 months, any information obtained through or from TMDB or the TMDB APIs" (§1.C), and require purging cached content on termination (§1.D). [TMDB API Terms of Use](https://www.themoviedb.org/api-terms-of-use)
  - Commercial use needs a separate agreement (§2.A).
  - Licensing and attribution are owned by [TMDB terms](../issues/01-tmdb-terms.md). These facts are noted here only because they cap TTLs.

## Things to verify with a real key (cheap spike before implementation)

1. **Partial-word search.** Does a partial word (e.g. `query=inter`) return *Interstellar*?
2. **Appended watch providers.** What are the exact `append_to_response` token and response key for watch providers (`watch/providers`)?
3. **`include_video_language` with append.** Is it honoured when videos are appended?
4. **Trending `page`.** Is a `page` parameter accepted on `/trending/movie/{window}`?
5. **Upcoming with `region=US`.** Do `release_date` values stay inside `dates.minimum`/`dates.maximum`?
6. **Watch provider keys.** How often do `free` and `ads` appear for US titles?

## Sources

- TMDB developer docs (guides): [Getting Started](https://developer.themoviedb.org/docs/getting-started), [FAQ](https://developer.themoviedb.org/docs/faq), [Popularity & Trending](https://developer.themoviedb.org/docs/popularity-and-trending), [Authentication: Application](https://developer.themoviedb.org/docs/authentication-application), [Append To Response](https://developer.themoviedb.org/docs/append-to-response), [Finding Data](https://developer.themoviedb.org/docs/finding-data), [Languages](https://developer.themoviedb.org/docs/languages), [Regions](https://developer.themoviedb.org/docs/region-support), [Image Basics](https://developer.themoviedb.org/docs/image-basics), [Image Languages](https://developer.themoviedb.org/docs/image-languages), [Rate Limiting](https://developer.themoviedb.org/docs/rate-limiting), [Errors](https://developer.themoviedb.org/docs/errors), [JSON & JSONP](https://developer.themoviedb.org/docs/json-and-jsonp)
- TMDB API reference: [Movie Details](https://developer.themoviedb.org/reference/movie-details), [Credits](https://developer.themoviedb.org/reference/movie-credits), [Videos](https://developer.themoviedb.org/reference/movie-videos), [Watch Providers](https://developer.themoviedb.org/reference/movie-watch-providers), [Release Dates](https://developer.themoviedb.org/reference/movie-release-dates), [External IDs](https://developer.themoviedb.org/reference/movie-external-ids), [Trending Movies](https://developer.themoviedb.org/reference/trending-movies), [Popular](https://developer.themoviedb.org/reference/movie-popular-list), [Top Rated](https://developer.themoviedb.org/reference/movie-top-rated-list), [Upcoming](https://developer.themoviedb.org/reference/movie-upcoming-list), [Discover Movie](https://developer.themoviedb.org/reference/discover-movie), [Search Movie](https://developer.themoviedb.org/reference/search-movie), [Genre List](https://developer.themoviedb.org/reference/genre-movie-list), [Configuration](https://developer.themoviedb.org/reference/configuration-details), [Countries](https://developer.themoviedb.org/reference/configuration-countries), [Available Regions](https://developer.themoviedb.org/reference/watch-providers-available-regions), [Movie Providers](https://developer.themoviedb.org/reference/watch-providers-movie-list), [Find By ID](https://developer.themoviedb.org/reference/find-by-id), [Changes: Movie List](https://developer.themoviedb.org/reference/changes-movie-list), [Movie Changes](https://developer.themoviedb.org/reference/movie-changes), [Validate Key](https://developer.themoviedb.org/reference/authentication-validate-key)
- TMDB terms and community: [API Terms of Use](https://www.themoviedb.org/api-terms-of-use) (last updated 2023-10-20), [Contribution bible: movie videos](https://www.themoviedb.org/bible/movie/59f3b16d9251414f2000000a), staff forum posts on [include_video_language](https://themoviedb.org/talk/65b332d057530e0161d9338e), [supported video sites](https://themoviedb.org/talk/62db7e45ea84c7004fc5a8c7), [page cap](https://www.themoviedb.org/talk/66f6d91fb9fd27627950d0b4), [search matching](https://www.themoviedb.org/talk/5ff17aad1fb94f003ef540f4)
- Video platforms: [YouTube Required Minimum Functionality](https://developers.google.com/youtube/terms/required-minimum-functionality), [YouTube player parameters](https://developers.google.com/youtube/player_parameters), [YouTube privacy-enhanced mode](https://support.google.com/youtube/answer/171780), [Vimeo player parameters](https://help.vimeo.com/hc/en-us/articles/12426260232977-Player-parameters-overview)
