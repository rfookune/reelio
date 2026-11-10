# 09: Runtime filter

Status: ready-for-agent

Parent: [spec](../spec.md).

**Blocked by:** 06

**What to build:** A runtime single-select with three bands (under 90 minutes, 90 to 120 minutes, over 120 minutes) for "I only have tonight". It is Movies only, because a TV Show's runtime is per Episode, and it reuses the "Movies only" behaviour from the Release stage filter: choosing it on the TV Shows tab switches to Movies and says so.

- [ ] A runtime single-select appears in the filter bar; choosing a band lists only Movies in it
- [ ] Choosing a band on the TV Shows tab switches to the Movies tab and shows "Movies only", using the same behaviour as the Release stage filter
- [ ] The choice is kept in the URL and counted among the active filters; an unknown band is ignored
- [ ] The TMDB request-mapping test covers the runtime parameters for each band
- [ ] A feature test covers each band reaching the source and the tab switch
