# 07: Highly rated toggle

Status: resolved

Parent: [spec](../spec.md).

**Blocked by:** 04

**What to build:** A "Highly rated" toggle in the filter bar that keeps only Titles with a TMDB score of 7 or more, ignoring Titles with only a handful of votes. The label states that the score is from TMDB, because a card often shows the IMDb External rating instead and the two can disagree. It works on both tabs.

- [x] A Highly rated toggle appears in the filter bar for both tabs; turning it on lists only Titles with a TMDB score of 7 or more above the vote-count floor
- [x] The label names TMDB as the source of the score
- [x] The toggle is kept in the URL and counted among the active filters
- [x] The TMDB request-mapping test covers the score and vote-count parameters
- [x] A feature test covers the toggle reaching the source on both tabs

## Comments

Implemented in commit `eb49fea` and verified with the feature and TMDB request-mapping tests.
