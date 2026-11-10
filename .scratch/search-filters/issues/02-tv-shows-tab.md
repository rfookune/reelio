# 02: TV Shows tab

Status: ready-for-agent

Parent: [spec](../spec.md). See [ADR 0003](../../../docs/adr/0003-browse-and-search-by-name-are-separate.md): Browse has a Movies tab and a TV Shows tab and no "All".

**Blocked by:** 01

**What to build:** Browse gains a Movies / TV Shows tab pair, with Movies the default. The TV Shows tab browses TV Shows through the source's TV discover list, with the same Popular default, infinite scroll and cards as Movies, and TV cards carry their season count and link through the TV Show entry route. The tab is a URL parameter, so a TV Shows view can be shared. The search-by-Name All / Movies / TV Shows chips are unchanged.

- [ ] A Movies / TV Shows tab appears in Browse, Movies by default, and the tab is kept in the URL
- [ ] The TV Shows tab lists TV Shows by popularity through the Catalog source's new read for the TV kind, covered by a TMDB request-mapping test and by a feature test through the fake source
- [ ] TV cards show the External rating by the same rule as search, the season count where known, a link through the TV Show entry route with the `search` referrer, and the TV Show Watchlist toggle with the viewer's facts
- [ ] Switching tabs keeps the page usable with loading and empty states
- [ ] An unknown tab value falls back to Movies
- [ ] The search-by-Name chips and results are unchanged
- [ ] Browse stays available when TV Shows are switched off in configuration: the TV Shows tab is not offered then
