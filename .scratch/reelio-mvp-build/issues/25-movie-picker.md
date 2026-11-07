# 25: Movie picker

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** From the Watchlist, "Pick for me" chooses a Movie at random (optionally by genre), shows what's needed to decide, and lets the user pick again or mark it watched. See the spec's "Movie picker" section.

**Blocked by:** [12: Where to watch on the Movie page](./12-where-to-watch-on-the-movie-page.md), [16: Mark as watched and Clear Watched](./16-mark-as-watched-and-clear-watched.md), [22: Watchlist page](./22-watchlist-page.md)

**Status:** ready-for-agent

- [x] One authenticated endpoint (path set at build), called with `useHttp`, picks uniformly at random (Postgres `random()`) from the Watchlist minus Movies without details; the client never receives the Watchlist; the response has the Movie's details and `pool_size`
- [x] An optional any-match genre multi-select listing only genres present among the candidates; it resets each time the dialog opens
- [x] Pick again sends the previous id, excluded when two or more candidates qualify; the server keeps no state
- [x] A reka-ui Dialog (bottom sheet on mobile) from "Pick for me" in the Watchlist header, hidden when there are no candidates; focus trapped and returned
- [x] Shows poster (`w185`/`w342`), title, year, runtime, genres, credited External ratings, a ~3-line overview, Stream and Free Streaming service logos for the user's country from stored Availability with the JustWatch credit and the freshness line
- [x] Actions: View details (`picker` referrer), Pick again, Mark as watched (closes the dialog, the card leaves, toast with Undo that doesn't reopen the dialog)
- [x] ~200 ms crossfade reveal (instant under reduced motion); the live region announces "Picked: {title} ({year})"
- [x] Edge states: no candidates, no genre match with "Clear genres", and one candidate ("This is the only match", Pick again disabled)
- [x] Limited to 30/min per user; `movie_picker_used` with `pool_size`
- [x] The pick-from-the-Watchlist browser journey at desktop and mobile
