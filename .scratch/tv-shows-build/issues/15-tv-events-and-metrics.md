# 15: TV events and metrics

**What to build:** Record events for TV Show actions and report them. Movie events keep their names and meaning. Add the TV events with their properties as listed in the spec's Events and metrics section (viewed, watchlisted and unwatchlisted, Episode, Season and whole-TV Show marks and unmarks with local Season and Episode ids and counts, completed, undone), make search `title_searched` (result count across both kinds) and the Watch-link event kind-neutral with `title_id`, `kind` and country. No view event on the Removed Title 410 or the cap-cleared 503, and none with the flag off. `metrics:report` counts both kinds in weekly returning and search-to-watchlist and gets a TV Shows block. The roll-up is unchanged.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 07 Where to watch and ratings for TV Shows, 10 Season and whole-TV Show bulk actions, 11 Search with TV Shows

**Status:** ready-for-agent

- [ ] Each TV action records exactly one event with the documented properties; whole-TV Show events carry only a count
- [ ] Season and Episode ids are plain integers with no foreign key
- [ ] Guest replays record on replay; no events on the 410, the 503 or with the flag off
- [ ] `title_searched` and the kind-neutral Watch-link event replace the old names, with Movie tests updated accordingly
- [ ] `metrics:report` shows both kinds in the existing metrics and the new TV Shows block
- [ ] The 13-month roll-up handles the new event names without change
