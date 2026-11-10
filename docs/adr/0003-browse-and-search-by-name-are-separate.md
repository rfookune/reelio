# Browse and search by Name are separate modes

Search by Name and Browse (filters: Streaming service, genre, Release stage, runtime, era, original language, rating) are never combined in one request. A text search reads the Catalog source's `search/multi`, which takes no genre, provider or release-type filter; Browse reads `discover/movie` or `discover/tv`, which take those filters but no text. Choosing a filter leaves search by Name, and typing a Name leaves Browse. The Search page shows Browse when there is no query.

We chose this over combining them because a combined request can't be answered by one source call, and every way of faking it gives wrong pages.

## Considered Options

- **Filter the search results after fetching them**: a page of 20 results could hold 3 matches or none, "Show more" paging stops making sense, and the total count is wrong. Genre alone is possible (results carry `genre_ids`); Streaming service and Release stage would need a per-Title lookup.
- **Fetch many search pages and filter on our side**: multiplies source calls and cache load, and still misses matches beyond the source's page limit.
- **Keep two modes** (chosen): each mode is one source call per page, with true paging and totals. The cost is that "action movies with 'bond' in the name" isn't possible.

## Consequences

- **Browse has a Movies tab and a TV Shows tab, with no "All"**, because `discover/movie` and `discover/tv` are separate endpoints and interleaving them breaks sorting and paging. Search by Name keeps its All / Movies / TV Shows chips, since `search/multi` is one mixed list.
- Genre lists are per kind, as the source's Movie and TV genres differ.
- If the source ever gains a filtered text search, this decision can be revisited without a data change: the modes are a UI and query-routing choice only.
