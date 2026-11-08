# 03: Kind-aware TMDB rating ids

**What to build:** Make the TMDB Rating source's id mapping produce kind-prefixed ids (`movie:550`, `tv:1399`) in the one `ratings` table, because TMDB ids are only unique within a kind (ADR 0001, ADR 0002). IMDb ids stay as they are. Movie behaviour does not change; the 170-day cap, the purge and `catalog:purge` clear TMDB rating rows of both kinds.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 02 Movie becomes Title, no visible change

**Status:** ready-for-agent

- [ ] Stored TMDB rows are keyed by kind-prefixed ids; IMDb rows are unchanged
- [ ] A Movie and a TV Show sharing a TMDB id keep separate scores
- [ ] Reads, writes, the cap guard, the purge and `catalog:purge` use the new ids
- [ ] No new column on the ratings table
- [ ] The Movie suite stays green and tests cover the id collision
