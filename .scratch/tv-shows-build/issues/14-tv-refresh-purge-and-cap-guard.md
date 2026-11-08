# 14: TV refresh, purge and cap guard

**What to build:** Keep TV data fresh and within the TMDB 6-month cap. The planner schedules a unique refresh job per TV Show with user facts (a Watchlist entry or any Watched Episode): every 3 days while airing (Returning Series, In Production, Pilot), every 30 days when Ended or Cancelled. The daily cleanup deletes a TV Show with no user facts not viewed for 30 days, with its Seasons and Episodes. At 170 days without a successful sync every TMDB-derived column on the Title, its Seasons and its Episodes is cleared while rows, ids and TMDB ids stay; the next successful refresh restores them. A Season or Episode missing from a successful refresh is marked removed and restored if it reappears. `catalog:purge` clears Seasons and Episodes too. With the flag off, the planner skips TV Shows but the purge, cap guard and `catalog:purge` still run.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 08 Mark Episodes as watched, 09 Watchlist for TV Shows

**Status:** ready-for-agent

- [ ] Refresh cadence follows TMDB status and only applies with user facts
- [ ] Purge deletes unviewed fact-less TV Shows with their Seasons and Episodes
- [ ] The cap guard clears TMDB data but keeps rows and Watched facts, and the next refresh restores everything
- [ ] Vanished Seasons and Episodes are hidden, excluded from counts, keep facts and are restored if they return
- [ ] `catalog:purge` and the cap guard clear TMDB rating rows of both kinds
- [ ] With the flag off the purge and cap guard still run and the planner skips TV Shows
