# 06: Release stage filter

Status: wontfix

Parent: [spec](../spec.md). Uses the glossary term **Release stage**.

**Blocked by:** 04

**What to build:** A Release stage single-select (In theater, At home, Coming soon), Movies only, in the visitor's country. In theater is a theatrical release within the last 8 weeks; At home is a digital or physical release within the last 6 months that is not within the theatrical window; Coming soon is a theatrical or digital release dated up to 6 months ahead. The stages are an estimate from release dates, not showtimes, and the UI does not claim otherwise. Because it applies to Movies only, choosing it on the TV Shows tab switches to Movies and says "Movies only". In theater and Coming soon keep the Popular sort, since an unreleased Title has no meaningful rating.

- [ ] A Release stage single-select appears in the filter bar; choosing a stage lists only Movies in that stage for the visitor's country, with the three windows above
- [ ] Choosing a stage on the TV Shows tab switches to the Movies tab and shows "Movies only"
- [ ] In theater and Coming soon default to Popular; At home follows the normal filtered default; an explicit sort still wins
- [ ] The UI does not present the stages as showtimes
- [ ] The selection is kept in the URL and counted among the active filters; an unknown stage is ignored
- [ ] The TMDB request-mapping test covers the release types and dates for each stage and the country
- [ ] Feature tests cover each stage reaching the source, the tab switch and the sort exception

## Comments

Closed without implementation. The At home filter requires excluding Movies with recent theatrical releases by checking their country-specific release dates after fetching a Browse page. That adds per-Movie Catalog requests and leaves source candidate totals inaccurate, conflicting with [ADR 0003](../../../docs/adr/0003-browse-and-search-by-name-are-separate.md), which keeps one source call per page to preserve paging and totals. TMDB's discover endpoint does not provide a way to exclude that overlapping theatrical window. Revisit only if the source can return this filter with correct paging and totals.
