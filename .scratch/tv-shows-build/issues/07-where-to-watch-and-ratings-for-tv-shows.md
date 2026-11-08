# 07: Where to watch and ratings for TV Shows

**What to build:** Show TV Show-level Availability in the Where to watch block (same layout, country rules, offer types, JustWatch credit and freshness notices as a Movie, driven by the TV Show's one `synced_at`) and the Watch redirect to TMDB's TV watch page with its click event and signed-in-only rule. Show IMDb then TMDB ratings with vote counts and credits on the page; the TV Show's IMDb id comes from the appended `external_ids`. IMDb for TV Shows rests on the existing dataset permission, an assumption flagged for re-confirmation before launch; the existing source switch hides it.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 05 A TV Show page appears behind the flag

**Status:** ready-for-agent

- [ ] Availability rows are stored per Title and replaced wholesale on refresh
- [ ] Where to watch shows 'Updated n days ago' and the 'may be out of date' notice from the TV Show's `synced_at`
- [ ] The Watch redirect goes to TMDB's TV watch page; it is `noindex` and `nofollow`
- [ ] The page shows IMDb then TMDB with vote counts and credits; 'Not yet rated' when neither exists
- [ ] Switching IMDb off hides it for TV Shows too
- [ ] No Season-level or Episode-level Availability exists
