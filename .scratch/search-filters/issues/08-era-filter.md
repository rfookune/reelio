# 08: Era filter

Status: resolved

Parent: [spec](../spec.md).

**Blocked by:** 03

**What to build:** A single-select decade filter (2020s, 2010s, 2000s, 1990s, Older) for "something recent" or "a classic". It works on both tabs, using the release date for Movies and the first air date for TV Shows. "Older" means before 1990.

- [x] An Era single-select appears in the filter bar for both tabs; choosing a decade lists only Titles from it
- [x] Movies use the release date and TV Shows use the first air date, and "Older" means before 1990
- [x] The choice is kept in the URL and counted among the active filters; an unknown value is ignored
- [x] The TMDB request-mapping test covers the date range for each decade and each kind
- [x] A feature test covers an era reaching the source on both tabs

## Comments

Implemented in commit `e86ca90` and verified with the feature and TMDB request-mapping tests.
