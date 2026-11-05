# Verify TMDB behaviours with a live key

Type: task
Status: open
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
