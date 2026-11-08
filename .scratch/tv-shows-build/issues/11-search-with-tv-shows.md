# 11: Search with TV Shows

**What to build:** Search results include TV Shows beside Movies in one poster grid with All / Movies / TV Shows chips and counts (client-side over the one grid). A TV Show card shows the 'TV · n Seasons' badge, Name, first-air year and one rating (IMDb if known, else TMDB, else 'Not yet rated'), linking through the entry route. The after-render IMDb lookup is extended to TV ids and cards update without a reload. With the flag off, TV results are dropped.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 03 Kind-aware TMDB rating ids, 05 A TV Show page appears behind the flag

**Status:** ready-for-agent

- [ ] Search returns both kinds, told apart by `media_type`, with the right Name and date fields for each
- [ ] Chips filter the grid and show counts; All is the default
- [ ] TV cards link to the entry route (or the canonical URL when the row exists)
- [ ] The IMDb lookup resolves TV ids and fills cards in after render
- [ ] With the flag off, no TV results appear
- [ ] Existing Movie search tests stay green
