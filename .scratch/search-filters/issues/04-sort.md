# 04: Sort

Status: ready-for-agent

Parent: [spec](../spec.md).

**Blocked by:** 03

**What to build:** A sort choice in the filter bar with Top rated, Popular and Newest. Unfiltered Browse sorts by Popular. Once any filter is chosen the default becomes Top rated, ranked by score with a vote-count floor so a Title with a few perfect votes doesn't win. A sort in the URL always wins over the default. The Release stage filter (ticket 06) will add the one exception, which keeps Popular; this ticket should make the default rule easy for it to extend.

- [ ] The filter bar offers Top rated, Popular and Newest, and the choice is kept in the URL
- [ ] With no filters the sort is Popular; with any filter chosen it is Top rated
- [ ] Top rated ranks by score above a vote-count floor and is covered by a TMDB request-mapping test
- [ ] Newest orders by release date for Movies and first air date for TV Shows
- [ ] An explicit sort in the URL wins over the default, and an unknown sort value falls back to the default
- [ ] The default-sort rule lives in one place that a Release stage exception can extend
- [ ] Feature tests cover the defaults, the explicit choice and the invalid value
