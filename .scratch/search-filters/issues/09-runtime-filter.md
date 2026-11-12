# 09: Runtime filter

Status: resolved

Parent: [spec](../spec.md).

**Blocked by:** 03

**What to build:** A runtime single-select with three bands (under 90 minutes, 90 to 120 minutes, over 120 minutes) for "I only have tonight". It is Movies only, because a TV Show's runtime is per Episode. Choosing a band on the TV Shows tab switches to Movies and says "Movies only".

- [x] A runtime single-select appears in the filter bar; choosing a band lists only Movies in it
- [x] Choosing a band on the TV Shows tab switches to the Movies tab and shows "Movies only"
- [x] The choice is kept in the URL and counted among the active filters; an unknown band is ignored
- [x] The TMDB request-mapping test covers the runtime parameters for each band
- [x] A feature test covers each band reaching the source and the tab switch

## Comments

Implemented in commit `b907a20`. TMDB supports the Movie Discover runtime bounds (`with_runtime.gte` and `with_runtime.lte`); request-mapping tests cover all three bands. The source applies the bounds before paging, so ADR 0003's paging constraint is preserved.
