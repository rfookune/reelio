# OMDb ratings: coverage, terms and limits

Type: research
Status: open
Map: [Reelio MVP](../map.md)

## Question

Can Reelio show IMDb, Rotten Tomatoes and Metacritic External ratings in the MVP, and is OMDb the right Rating source for them?

- Does TMDB provide any of these ratings itself? If not, confirm that its `external_ids` IMDb id is the correct join key for OMDb.
- OMDb's terms: API key tiers, daily limits, commercial vs non-commercial use, data licence, attribution, and caching or storage rules.
- Is OMDb authorized to redistribute Rotten Tomatoes and Metacritic scores, and what do the IMDb, Rotten Tomatoes and Metacritic terms say about displaying their scores via a third party? (The PRD forbids scraped or unauthorized ratings.)
- Coverage and freshness: how often are OMDb ratings missing or stale for recent releases, and how are missing values represented?
- Are there licensed alternatives worth noting if OMDb fails the licensing test?
