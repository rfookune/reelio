# Movie picker

Type: grilling
Status: resolved
Blocked by: 04, 09
Map: [Reelio MVP](../map.md)

## Question

How does the P0 random movie picker behave?

- Which filters it offers in P0, given that "respect applied filters" is P0 but the filter dimensions listed are P1.
- Whether selection happens server-side.
- What "Pick Again" does: may it return the same Movie twice in a row?
- The empty-candidate message.
- Where the picker appears (modal vs page) and what "essential details" it shows.

## Comments

- From [The user–movie lifecycle](./04-user-movie-lifecycle.md) (resolved): The Watchlist is exactly the picker's candidate set before filters (a Watched Movie is only on the Watchlist if the user re-added it to watch again). See [The user–movie lifecycle](./04-user-movie-lifecycle.md).

## Answer

**Candidates**

- Exactly the user's Watchlist, including Movies re-added to watch again. There is no "exclude watched" toggle: the [user–movie lifecycle](./04-user-movie-lifecycle.md) already takes a Movie off the Watchlist when it's marked as watched.
- Shells (Movies TMDB has dropped) are excluded, so the picker never offers a Movie it can't show.

**Filters (P0)**

- Genre only: an optional multi-select, any-match. It lists only the genres present among the current candidates, so a selection can't empty the pool. The P1 filters (FR-015) arrive later through the same criteria object.
- The selection belongs to the dialog and resets each time it opens. Nothing is stored.

**Selection**

- Server-side, uniform random over the candidates (Postgres `random()`), through one authenticated endpoint the dialog calls with `useHttp`. The response is the chosen Movie's details plus `pool_size` for `movie_picker_used`. The client never receives the Watchlist to pick from.
- Pick again sends the previous id and excludes it when two or more candidates qualify, staying uniform among the rest. The server keeps no state.

**Placement and content**

- A reka-ui `Dialog` (a bottom sheet on mobile) opened from a "Pick for me" button in the Watchlist page header. There's no route or nav entry, and guests never see it.
- Details shown: poster, title, year, runtime, genres, External ratings (TMDB and IMDb, each credited), an overview cut to about three lines, and Streaming service logos in the Stream and Free groups for the user's country, from stored Availability, with the JustWatch credit.
- Actions: **View details** (analytics referrer `picker`) and **Pick again**. Whether Mark as watched or Remove also appear is for [Watchlist interactions](./13-watchlist-interactions.md).
- Reveal: a crossfade of about 200 ms, instant under `prefers-reduced-motion`. No shuffle animation.

**Edge states**

- Empty Watchlist: the "Pick for me" button is hidden. If the endpoint finds no candidates anyway (a race), the dialog says "Your Watchlist is empty. Save a few Movies to pick from." and links to Discover.
- The genre filter matches nothing (a race only): "No Movies on your Watchlist match these genres", with a "Clear genres" action.
- One candidate: it's shown, Pick again is disabled, and visible text says "This is the only match".

- From [Watchlist and Watched pages](./25-watchlist-and-watched-pages.md) (resolved): "Pick for me" is hidden when the picker has **no candidates**, not only when the Watchlist is empty, because a Watchlist holding only Removed Movies has none.
- From [Cache lifetimes and refresh schedule](./26-cache-lifetimes-and-refresh.md) (resolved): Watchlist Movies are refreshed every 3 days, so the picker's stored Stream/Free Availability is at most about 3 days old. The picker shows the same "Updated N days ago" line and 7-day "may be out of date" notice as the detail page.
