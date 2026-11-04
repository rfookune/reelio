# OMDb ratings: coverage, terms and limits

Type: research
Status: resolved
Map: [Reelio MVP](../map.md)

## Question

Can Reelio show IMDb, Rotten Tomatoes and Metacritic External ratings in the MVP, and is OMDb the right Rating source for them?

- Does TMDB provide any of these ratings itself? If not, confirm that its `external_ids` IMDb id is the correct join key for OMDb.
- OMDb's terms: API key tiers, daily limits, commercial vs non-commercial use, data licence, attribution, and caching or storage rules.
- Is OMDb authorized to redistribute Rotten Tomatoes and Metacritic scores, and what do the IMDb, Rotten Tomatoes and Metacritic terms say about displaying their scores via a third party? (The PRD forbids scraped or unauthorized ratings.)
- Coverage and freshness: how often are OMDb ratings missing or stale for recent releases, and how are missing values represented?
- Are there licensed alternatives worth noting if OMDb fails the licensing test?

## Answer

Sources: OMDb's own [Terms of Use](https://www.omdbapi.com/legal.htm) (made the API's `termsOfService` by `swagger.json`), its docs, key page and Patreon tiers, and the operator's own statements; the TMDB API reference; the terms of IMDb, Rotten Tomatoes and Metacritic (Metacritic's via its licensing partner); and the licensed alternatives' contracts.

- **TMDB has none of these ratings.** It returns only its own `vote_average` and `vote_count`. Movie details already includes `imdb_id`, the correct OMDb/IMDb join key, so no `external_ids` call is needed. Treat it as nullable, and never fall back to a title search.
- **OMDb fails the PRD's authorization test (FR-023), for three independent reasons:**
  - **Its own terms rule Reelio out.** They allow "personal use" only, say "You may not build a business utilizing the Contributions, whether or not for profit", and forbid archiving or display beyond personal, non-commercial use. A free public beta still breaches them. The CC BY-NC 4.0 footer conflicts with these terms but cannot license scores OMDb doesn't own.
  - **OMDb has no licence for these scores.** Its operator says Rotten Tomatoes data was removed after "a legal request from Fandango", that he "cannot use/store" it, and that he relays ratings "merely as a conduit" under DMCA §512(a) after dealing with Amazon (IMDb) and Fandango's lawyers. That defence protects OMDb, not Reelio as the site displaying the scores.
  - **The Rating sources forbid it.** IMDb's and Rotten Tomatoes' terms limit their content to personal, non-commercial use, and reuse needs written permission. Metacritic's Metascore is licensed content, sold through Fabric Origin under a Fandom partnership.
- **Tiers:** free at 1,000 requests a day. Patreon plans cost $1, $5 or $10 a month for 100,000 a day, 500,000 a day, or no limit. No tier grants commercial rights.
- **Coverage is weak for new releases.** Per the operator (2018), entries refresh only every 30 days, with a release-date override. Users report `"N/A"` and `Ratings: []` for films weeks after release. Missing scalars are the string `"N/A"`, partial arrays are common, and every value is a string (`"7.7/10"`, `"83%"`, `"67/100"`). The operator says his sources are "none of which are IMDb", so OMDb's "IMDb" figure can drift from IMDb's.
- **Keeping only IMDb doesn't work either.** IMDb's free datasets ban republishing to create "any kind of online/offline database" beyond individual personal use.
- **Licensed alternatives:**
  - **IMDb:** IMDb's API on AWS Data Exchange. Ratings are included; the price is quoted, not published.
  - **Rotten Tomatoes:** Fandango's Business Proposal form, or Fabric Origin. There is a distribution fee, the data must refresh daily, the logo display must be approved before launch, and no ad units may be sold against the data.
  - **Metacritic:** Fabric Origin, with the licence fee waived. Ads require Fandom's approval, and the content must be stored on U.S. servers.
  - **MDBList and Wikidata** don't meet the bar.
- **Recommendation: defer IMDb, Rotten Tomatoes and Metacritic from the MVP and don't use OMDb.** Ship the TMDB rating only (FR-022). Design the Rating source boundary for later licensed sources, with a per-source cache/TTL policy and "no row" (rather than `"N/A"`) for missing values.
- **Human decision needed:** either accept the deferral, or request quotes from IMDb (AWS Data Exchange) and Fabric Origin or Fandango. The no-ads clauses in those licences would also constrain later monetization.
- **Not established:** Fandom's own terms (fetch blocked), measured OMDb coverage (no API key), and prices.

Full findings: branch research/omdb-ratings, file .scratch/reelio-mvp/research/omdb-ratings.md (https://github.com/rfookune/reelio/blob/research/omdb-ratings/.scratch/reelio-mvp/research/omdb-ratings.md)
