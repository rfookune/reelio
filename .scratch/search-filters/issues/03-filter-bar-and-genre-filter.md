# 03: Filter bar and genre filter

Status: resolved

Parent: [spec](../spec.md). See [ADR 0003](../../../docs/adr/0003-browse-and-search-by-name-are-separate.md).

**Blocked by:** 02

**What to build:** Browse gets its filter bar and its first filter, a single-select genre for the tab you're on. The bar applies filters instantly with no Apply button, keeps them in the URL, shows how many are active and has a one-tap clear. It works on a phone and by keyboard and screen reader. The Movies tab offers the Movie genres and the TV Shows tab the TV genres; switching tabs keeps the genre if the other tab has a genre of the same name and clears it otherwise. This ticket sets up the filter plumbing the later filter tickets reuse: reading and validating filter values from the URL, passing them to the Catalog source, and the empty message when nothing matches. The Catalog source's genre read gains a kind, since today it returns Movie genres only, and both lists are cached.

- [x] A genre single-select appears in the filter bar; choosing a genre lists only that genre's Titles, for either tab
- [x] The genre options are the Movie genres on the Movies tab and the TV genres on the TV Shows tab
- [x] Switching tabs keeps the genre when the other tab has the same name and clears it when it doesn't
- [x] Filters apply as soon as they change, are kept in the URL, and the back button restores the previous filters
- [x] The bar shows the number of active filters and has a clear-all control
- [x] Choosing a filter clears a typed Name and shows Browse; typing a Name shows a search by Name and clears the filters; a request with both a query and filters is treated as a search by Name and ignores the filters
- [x] An unknown genre id, or any malformed filter value, is ignored and never passed to the source
- [x] A no-match message appears when the filters match nothing, and a loading placeholder while a filtered list loads
- [x] The bar fits a phone and every control is operable by keyboard and announced to a screen reader
- [x] Feature tests cover the genre reaching the source for each kind, the tab-switch rule, and the ignored invalid values; a TMDB request-mapping test covers the genre parameter and the genre read for each kind

## Revised UI: Search by name as the page, Advanced for the filters

The filter bar above the results, with its tabs, was reworked after a UI prototype (the question: how do Search by name and the filters share one page, and make clear that a name can't be filtered?). The prototype is kept as the primary source on the branch `prototype/search-advanced-filters` (its last commit, `f116e48`, is the winning design; the earlier ones are the variants that lost). The rules that came out of it, which supersede the criteria above where they differ:

- Search by name is the page: no visible title, a full-width field with an **Advanced** button inside it, a bold results label, and no All / Movies / TV Shows chips on a name search.
- **Advanced** drops a typed Name, hides the search box and shows an "Advanced filters" panel with a description and **Back to search**. Back clears every filter, the sort and the kind. A URL that already holds filters opens straight into Advanced.
- Movies / TV Shows is a dropdown, and the sort a dropdown reading "Sort: Popular", both at the right of the panel's title with no visible labels (each keeps an `aria-label`).
- The other filters share one card: Genre, Era, Runtime and Original language in a row; Where to watch (its own title, and the services search); Highly rated and Show seen beneath it; and a footer inside the card with the "N filters active" count and Clear filters.
- Focus stays on the control just changed, rather than moving to the page's start after each visit.
- Searching a name keeps nothing of the filters, as before ([ADR 0003](../../../docs/adr/0003-browse-and-search-by-name-are-separate.md)); what changed is that this is now said in the interface.
