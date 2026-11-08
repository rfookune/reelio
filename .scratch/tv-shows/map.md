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
- Consult `grilling` and `domain-modeling` for grilling tickets; update `GLOSSARY.md` as terms resolve; offer an ADR only for hard-to-reverse, surprising, traded-off decisions.

## Decisions so far

<!-- one line per resolved ticket: [title](link): gist -->

- [What TMDB offers for TV Shows](issues/02-tmdb-tv-data-shape.md): TV Show, Season and Episode details exist; Availability at TV Show and Season level only; about 1 + one request per Season to fetch a whole TV Show; findings are on the throwaway branch `research/tv-shows-tmdb-data-shape`.

## Not yet specified

- URLs and routes for TV Shows, and how existing Movie routes, canonical URLs and the entry route relate.
- Refresh and rate-limit behaviour for TV Shows and their Episodes (follows the data-volume decision).
- What a Removed TV Show looks like (follows the model decision).
- Events and analytics for TV Show actions, and how the metrics report treats them.
- Test strategy: browser journeys and the accessibility sweep for the new pages.
- Whether the Movie picker's Watchlist page changes once TV Shows sit on the Watchlist (the picker itself stays Movies-only).

## Out of scope

- Discover sections for TV Shows, the Movie picker for TV Shows, and sitemap and SEO for TV Shows: follow-ups once the core model is settled.
- Playback, social features, "up next" and new-episode notifications, an airing calendar, per-Episode or per-Season ratings, importing history from other services.
- Local ratings, like/dislike and the Discover filters: separate efforts that wait on the model decision.
