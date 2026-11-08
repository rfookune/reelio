# URLs and routes for TV Shows

Type: grilling
Blocked by: none
Status: resolved

## Question

What is the URL space for TV Shows (ADR 0002 says they get their own), and how do canonical URLs, slugs, the entry route for a TV Show with no row yet, the Watchlist and Watched action endpoints, the Undo endpoint, the Watch redirect and the Season and Episode Watched endpoints relate to the existing Movie routes? Which Movie-only routes (cast page, picker) stay Movie-only, and how do guest actions (the pending-action middleware) extend to Episodes?

## Answer

Settled in a grilling session; no new terms and no ADR.

**URL space**
- Canonical `/tv-shows/{slug}-{id}` using the local Title id; the slug is decorative and a wrong or stale slug gets a 301. Entry route `/tv-shows/tmdb/{tmdbId}`: TMDB confirms the id, the row is created, then a 301 to the canonical URL; rate-limited as for Movies. Movie routes do not change.
- Local ids are shared across kinds, so `/movies/{slug}-{id}` for a TV Show's id (and the reverse) 301s to the other kind's canonical URL, like a wrong slug. With `catalog.tv_shows.enabled` off, TV URLs and the cross-kind redirect 404.

**Endpoints**
- Watchlist, whole-TV Show Watched and the Watch redirect mirror the Movie ones under the TV prefix, keyed by TMDB id so cards without a row work: `PUT/DELETE tv-shows/tmdb/{tmdbId}/watchlist`, `PUT/DELETE tv-shows/tmdb/{tmdbId}/watched` (mark all aired Episodes, or clear all), `GET tv-shows/{id}/watch` (TMDB's TV watch page). Same auth, pending-guest-action and throttle rules.
- Season and Episode Watched are nested and keyed by local ids (they exist only once the row does): `PUT/DELETE tv-shows/{show}/seasons/{season}/watched` and `.../seasons/{season}/episodes/{episode}/watched`. Scoped bindings 404 an Episode outside its Season or TV Show, and a removed Season or Episode. Unaired Episodes are rejected with a validation error.
- Whole-TV Show "Mark as watched" with no Episodes stored (cap-cleared, or a failed fetch) first tries the in-request refresh; if that fails it changes nothing and shows "Details temporarily unavailable". It never marks a partial set.

**Guests**
- A guest's Episode, Season or whole-TV Show action is stored as their single pending action (extended to carry the Season or Episode id), they go to sign in, and it is replayed on return. Nothing is saved before sign-in. The confirm dialog for the whole-TV Show action shows before it is stored.

**Undo**
- One endpoint, `POST tv-shows/tmdb/{tmdbId}/undo`. The latest-action snapshot stays one per user; for Episode, Season and whole-TV Show actions it holds the previous Watched dates of the affected Episodes plus the Watchlist fact (completion removes the TV Show from the Watchlist). Undo restores only if the current state still matches what the action produced, otherwise "That can't be undone any more". A bulk action, including the Watchlist removal, is one Undo.

Left to other tickets: the cast page route (in the rest of the TV Show page ticket), event names (in the events ticket).
