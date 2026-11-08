# Map: TV Shows

Type: map (`wayfinder:map`)

## Destination

A buildable spec at `.scratch/tv-shows/spec.md` for adding TV Shows to Reelio, with Watched progress per Episode. The map ends when the way is clear; the handoff is `/to-spec`, then `/to-tickets`.

## Notes

- Domain: Reelio today only knows Movies. The PRD and the MVP spec list TV series tracking as a non-goal, so this effort reverses that scope decision. See [GLOSSARY.md](../../GLOSSARY.md) (TV Show, Season and Episode are defined; "Movie" has `_Avoid_: Title`) and [ADR 0001](../../docs/adr/0001-one-ratings-table-for-every-rating-source.md).
- Existing behaviour to keep consistent: the [MVP spec](../reelio-mvp/spec.md) (Watchlist, Watched, Undo, Removed Movie, Availability, External ratings, Catalog source).
- Vocabulary: always TV Show, Season and Episode. Never the bare word "Show".
- Scope settled with the user: in are search, the TV Show page (Seasons and Episodes), the Watchlist, Watched progress, Availability and External ratings. The Watchlist is TV Show-level; Watched progress is Episode-level; the two stay separate.
- Sequencing: local ratings, like/dislike and the Discover filters wait for the model decision in the first ticket below. They are not part of this map.
- The first build slice is the behaviour-preserving generalisation of Movie to Title; nothing is live, so migrations are edited in place.
- Consult `grilling` and `domain-modeling` for grilling tickets; update `GLOSSARY.md` as terms resolve; offer an ADR only for hard-to-reverse, surprising, traded-off decisions.

## Decisions so far

<!-- one line per resolved ticket: [title](link): gist -->

- [What TMDB offers for TV Shows](issues/02-tmdb-tv-data-shape.md): TV Show, Season and Episode details exist; Availability at TV Show and Season level only; about 1 + one request per Season to fetch a whole TV Show; findings are on the throwaway branch `research/tv-shows-tmdb-data-shape`.
- [Shared title model or a parallel TV Show model](issues/01-shared-title-or-parallel-tv-show-model.md): a shared `titles` base with a kind, detail tables per kind and `Title` as the base model; Movie URLs unchanged; migrations edited in place; see [ADR 0002](../../docs/adr/0002-shared-title-base-for-movies-and-tv-shows.md).
- [What Watched means for a TV Show](issues/03-what-watched-means-for-a-tv-show.md): Episode-level Watched facts with a derived TV Show state (Not started, In progress, Completed); Season and TV Show bulk actions with one Undo; the TV Show leaves the Watchlist on Completed and never returns on its own.
- [How much Episode data to store and when to refresh it](issues/04-episode-data-volume-and-refresh.md): minimal Season and Episode fields matched by TMDB id; one all-or-nothing full fetch per TV Show (details plus every Season, concurrently in-request); scheduled by TMDB status (airing 3 days, ended 30 days); Title-level purge and 170-day cap guard clear Seasons and Episodes but keep rows; vanished Episodes are marked removed, not deleted; `catalog.tv_shows.enabled` flag (default off) hides TV Shows but never stops the licence rules.
- [How TV Shows sit on the Watchlist](issues/05-watchlist-with-tv-shows.md): one Watchlist grid with an All / Movies / TV Shows filter and counts (same filter on Watched); TV Show cards show their state; whole-TV Show "Mark as watched" needs a confirm dialog everywhere; picker stays Movies-only; Removed TV Shows stay as stubs under the new term Removed Title.
- [The TV Show page and search results](issues/06-tv-show-page-and-search.md): TV Show page is a Season accordion with Season bulk actions and sticky right panels; whole-TV Show "Mark as watched" opens a modal confirm dialog; search and Watchlist/Watched are a poster grid with All / Movies / TV Shows chips and counts, TV Show posters badged and (on lists) with a progress bar; prototype on `prototype/tv-shows-page`.
- [Availability and External ratings for TV Shows](issues/07-availability-and-ratings-for-tv-shows.md): Availability at TV Show level only, stored and refreshed with the TV Show; TMDB scores keyed by a kind-prefixed `source_id` in the same `ratings` table; IMDb then TMDB shown as for Movies, none on Seasons or Episodes; IMDb for TV Shows rests on the existing dataset permission, flagged to re-confirm.
- [URLs and routes for TV Shows](issues/08-urls-and-routes-for-tv-shows.md): `/tv-shows/{slug}-{id}` canonical with a `/tv-shows/tmdb/{tmdbId}` entry route; cross-kind ids 301; Watchlist, Watched, Watch and Undo endpoints mirror the Movie ones by TMDB id; Season and Episode Watched nested by local ids; guests' Episode actions replay after sign-in; one Undo per bulk action.
- [The rest of the TV Show page and its first view](issues/09-the-rest-of-the-tv-show-page.md): Trailer, Cast (top 12, no full-cast page), overview and Where to watch carry over; hero and Details panel rows; hero actions by state; spoiler-safe Episode rows (no stills); Specials, unaired and empty-Season rules; Movie-page loading, stale and unavailable states; `TVSeries` JSON-LD.
- [The page of a Removed Title](issues/10-the-page-of-a-removed-title.md): a Removed TV Show is a title-less 410 like a Removed Movie, shared page with the noun swapped; Remove and Clear for users with facts; removed stays removed; stubs join the "No longer available" group with no state or progress; purged like any unviewed title.
- [Events and metrics for TV Show actions](issues/11-events-and-metrics-for-tv-shows.md): Movie events untouched; parallel `tv_show_*`, `season_*` and `episode_*` events (Season and Episode local ids recorded; whole-TV Show events carry only a count); search and the Watch link click become kind-neutral; `metrics:report` counts both kinds plus a TV Shows block; roll-up unchanged.
- [Test strategy for TV Shows](issues/12-test-strategy-for-tv-shows.md): the existing Movie suite guards the Title slice; record TV fixtures first (Season 0, unaired Episodes); extend the Catalog fake; feature tests for state, bulk actions, Undo, Removed Title, refresh and the flag; browser tests only for optimistic toggle, bulk Undo and the confirm dialog; one new journey and sweep pages; a TV fetch in the live group.

## Not yet specified

## Out of scope

- Discover sections for TV Shows, the Movie picker for TV Shows, and sitemap and SEO for TV Shows: follow-ups once the core model is settled.
- Season-level Availability (TMDB offers it, at one extra provider call per Season; the TV Show-level block covers the need): see [Availability and External ratings for TV Shows](issues/07-availability-and-ratings-for-tv-shows.md).
- Playback, social features, "up next" and new-episode notifications, an airing calendar, per-Episode or per-Season ratings, importing history from other services.
- Local ratings, like/dislike and the Discover filters: separate efforts that wait on the model decision.
