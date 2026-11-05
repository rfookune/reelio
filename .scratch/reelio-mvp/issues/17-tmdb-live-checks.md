# Verify TMDB behaviours with a live key

Type: task
Status: resolved
Map: [Reelio MVP](../map.md)

## Question

Does the live TMDB API behave the way the docs suggest on the six points the [TMDB API coverage](./02-tmdb-api-coverage.md) research couldn't confirm without a key?

1. **Partial-word search.** Does `query=inter` return _Interstellar_? This decides whether debounced as-you-type search (FR-002) is meaningful.
2. **Appended watch providers.** What are the exact `append_to_response` token and response key for `watch/providers`?
3. **Trailer language.** Is `include_video_language` honoured when videos are appended?
4. **Trending pages.** Does `/trending/movie/{window}` accept a `page` parameter?
5. **Upcoming dates.** With `region=US`, do upcoming `release_date` values stay within `dates.minimum`/`dates.maximum`?
6. **Free and ads categories.** How often do `free` and `ads` appear for US titles?

This is HITL then AFK. The human registers for a TMDB API key and adds the read access token to `.env` as `TMDB_READ_ACCESS_TOKEN`; the TMDB application should describe Reelio honestly (see [Confirm TMDB's classification of Reelio](./16-tmdb-classification.md)). The agent then runs the checks **without printing the token**, and records the observed responses here. The results inform [Catalog and Rating source boundary](./10-source-boundary.md), [Country and Availability presentation](./11-country-and-availability.md), and the homepage-curation fog.

## Comments

- From [Test strategy and tooling](./07-test-strategy.md) (resolved): while the key is live, save the raw responses (token-free) as candidate fixtures for the TMDB adapter's `Http::fake()` tests.

## Answer

Run 2026-11-04 against the live v3 API with the Read Access Token (Bearer header; nothing printed or stored). Raw, token-free responses are saved as candidate fixtures in `.scratch/reelio-mvp/research/tmdb-fixtures/` (`search_inter`, `movie_157336` with all appends, `trending_p2`, `upcoming_us`).

1. **Partial-word search: works, but ranking makes short queries noisy.** The last word is prefix-matched (`interste` finds *Interstellar*), but `inter` (4,141 hits) buries it: the top 60 are titles with the exact word "Inter". `inters` puts it 2nd and `interst` 1st; `dark kn` finds *The Dark Knight* first. Mid-word fragments don't match (`nterstellar` returns nothing). As-you-type search is meaningful, but only becomes useful at roughly 5+ characters for long titles, so the search UI should offer a full results page on Enter rather than rely on the dropdown. The minimum length and debounce are for the search UI to set.
2. **Appended watch providers:** `append_to_response=watch/providers`, and the response key is `watch/providers`, an object with `results` keyed by country code (111 countries for *Interstellar*). The US entry has `link`, `flatrate`, `rent` and `buy` here (`free`/`ads` only when present). Credits, videos and release_dates appended in the same call, in one request.
3. **Trailer language:** `include_video_language` is honoured. With `language=fr-FR` and no include, only French videos come back (9). With `language=fr-FR&include_video_language=fr,en,null`, English and French come back (54). With no `language`, the default is English (45) and adding `en,null` changes nothing. Reelio should pass `include_video_language` explicitly so a non-English locale still gets the English trailer.
4. **Trending pages:** `page` is accepted (`/trending/movie/week?page=2` returned page 2, 20 results). `page=600` returns HTTP 400 "Invalid page: Pages start at 1 and max at 500".
5. **Upcoming dates: no, they don't stay in range.** `region=US` reported `dates` of 2026-11-11 to 2026-12-02, but 5 of 20 results have a `release_date` outside it (2001, 2016, 2021, 2024, 2026-11-06). The region filter works on US release dates, while `release_date` is the movie's primary date. Reelio must not display or sort the upcoming row by `release_date` alone: use the US entry in `release_dates`, or accept the mismatch and show no date on that row.
6. **`free` and `ads` in the US:** in a sample of 149 popular and top-rated Movies, 130 had US data. Of those: `flatrate` 89, `rent` 108, `buy` 112, `free` 18 and `ads` 18 (about 12% each). 19 Movies (13%) had no US data at all. The merged "Free, may include ads" group from [Country and Availability presentation](./11-country-and-availability.md) stands, and it will be shown often enough to earn its place. `/discover/movie` can filter by `with_watch_monetization_types=free|ads` with `watch_region=US` (20,001 results).

**Effects on other tickets**
- [TMDB API coverage for MVP features](./02-tmdb-api-coverage.md): the exact append token and response key are settled, and the 500-page cap is confirmed.
- Homepage curation (fog on the map): the Upcoming row has the date problem in point 5.
- The search fog or ticket that designs as-you-type search starts from point 1.
