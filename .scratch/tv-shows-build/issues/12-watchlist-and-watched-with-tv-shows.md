# 12: Watchlist and Watched with TV Shows

**What to build:** The Watchlist and Watched pages show Movies and TV Shows in one poster grid with an All / Movies / TV Shows filter (default All, in the URL with the sort) and counts over the whole list including Removed Titles. TV Show cards carry a 'TV' badge, a progress bar along the poster's bottom edge and 'State · watched/total'; the Watchlist menu offers 'Mark as watched' (confirmed) and the Watched menu 'Clear Watched'. The existing sorts apply across both kinds (a TV Show's release date is its first-air date, undated last). The Watched page lists a TV Show once at least one Episode is watched, ordered by its latest Episode Watched date. The Movie picker stays Movies-only, ignores the filter and is hidden when no Movie is pickable. Empty states: an empty Watchlist keeps Trending Movies; an empty filter points to search. With the flag off, TV Shows are hidden.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 10 Season and whole-TV Show bulk actions, 11 Search with TV Shows

**Status:** ready-for-agent

- [ ] One keyset-paged query serves both kinds with a stable order across sorts
- [ ] Filter, sort and counts work and persist in the URL
- [ ] TV cards show badge, bottom progress bar and state with counts
- [ ] 'Mark as watched' from a card opens the same confirm dialog and offers Undo
- [ ] The Watched page orders a TV Show by its latest Episode Watched date alongside Movies
- [ ] The picker chooses among Movies only and is hidden when none are pickable
- [ ] Movie list tests stay green; focus moves to the next card when a card leaves the Watchlist
