# 03: Filter bar and genre filter

Status: ready-for-agent

Parent: [spec](../spec.md). See [ADR 0003](../../../docs/adr/0003-browse-and-search-by-name-are-separate.md).

**Blocked by:** 02

**What to build:** Browse gets its filter bar and its first filter, a single-select genre for the tab you're on. The bar applies filters instantly with no Apply button, keeps them in the URL, shows how many are active and has a one-tap clear. It works on a phone and by keyboard and screen reader. The Movies tab offers the Movie genres and the TV Shows tab the TV genres; switching tabs keeps the genre if the other tab has a genre of the same name and clears it otherwise. This ticket sets up the filter plumbing the later filter tickets reuse: reading and validating filter values from the URL, passing them to the Catalog source, and the empty message when nothing matches. The Catalog source's genre read gains a kind, since today it returns Movie genres only, and both lists are cached.

- [ ] A genre single-select appears in the filter bar; choosing a genre lists only that genre's Titles, for either tab
- [ ] The genre options are the Movie genres on the Movies tab and the TV genres on the TV Shows tab
- [ ] Switching tabs keeps the genre when the other tab has the same name and clears it when it doesn't
- [ ] Filters apply as soon as they change, are kept in the URL, and the back button restores the previous filters
- [ ] The bar shows the number of active filters and has a clear-all control
- [ ] Choosing a filter clears a typed Name and shows Browse; typing a Name shows a search by Name and clears the filters; a request with both a query and filters is treated as a search by Name and ignores the filters
- [ ] An unknown genre id, or any malformed filter value, is ignored and never passed to the source
- [ ] A no-match message appears when the filters match nothing, and a loading placeholder while a filtered list loads
- [ ] The bar fits a phone and every control is operable by keyboard and announced to a screen reader
- [ ] Feature tests cover the genre reaching the source for each kind, the tab-switch rule, and the ignored invalid values; a TMDB request-mapping test covers the genre parameter and the genre read for each kind
