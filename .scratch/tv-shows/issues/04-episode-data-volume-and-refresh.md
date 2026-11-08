# How much Episode data to store and when to refresh it

Type: grilling
Blocked by: 01, 02
Status: resolved

## Question

Given the model decision (TV-only columns live in `tv_show_details`; Seasons and Episodes in their own tables keyed to a Title) and TMDB's data shape: which Episode and Season fields Reelio stores, whether Episodes are fetched on first view or all at once, how airing TV Shows get new Episodes, the equivalent of the Movie cap guard and purge for TV Shows, and the failure states (fresh, stale, unavailable) for TV Shows.


## Answer

Settled in a grilling session; no new glossary terms and no ADR (the choices are reversible config and refresh policy).

**Stored data**
- Season: TMDB id, `season_number`, name, overview, poster path, air date, episode count.
- Episode: TMDB id, `season_number`, `episode_number`, name, overview, air date, runtime, still path, `episode_type`.
- Seasons and Episodes are matched by TMDB id, never by number. Season 0 is stored like any Season and is the Special group.
- No votes, crew, guest stars or Episode IMDb ids. The TV Show itself gets a TMDB rating row like a Movie; Seasons and Episodes get none.

**Fetching**
- One refresh operation per TV Show updates the details, every Season and its Episodes, Availability, and the TMDB rating, and sets one `synced_at` on the Title. About 1 + N requests; always a full refetch, never incremental.
- In-request (first view, and a view when `synced_at` is over 24 h old): details and all Seasons are fetched concurrently under one overall interactive timeout, all-or-nothing. On success one `synced_at` is set. On any failure nothing is written: a stored TV Show renders `stale`, a first view with nothing stored gets the unavailable page.
- A TV Show fetch counts as one against the row-creation limit and the circuit breaker. If the timeout proves too tight, dispatching the refresh as a job is the escape hatch, decided on evidence.

**Scheduled refresh** (planner, one unique job per TV Show, as for Movies)
- Airing (TMDB `status` Returning Series, In Production or Pilot) with user facts: every 3 days.
- Ended or Cancelled with user facts: every 30 days.
- No user facts: never by schedule, purged instead.

**Purge and cap guard** (Title-level rules reused, applied to the whole TV Show)
- User facts = a Watchlist entry or any Watched Episode. A TV Show with none, not viewed for 30 days, is deleted with its Seasons and Episodes.
- At 170 days without a successful sync, every TMDB-derived column on the Title, its Seasons and its Episodes is cleared. Rows, ids and TMDB ids stay, so Watched facts survive and re-matching works. The same 503 "Details temporarily unavailable" page applies; the next successful refresh restores everything. `catalog:purge` clears Seasons and Episodes too.

**Vanished data**
- A Season or Episode missing from a successful refresh is marked removed, not deleted: hidden, excluded from Completed and progress counts, Watched facts kept, restored if it reappears.
- A TV Show whose details call returns 404 becomes a Removed Title (wording is still open in the map's fog).

**Feature flag**
- `catalog.tv_shows.enabled` in `config/catalog.php`, env `CATALOG_TV_SHOWS`, default off; the test setup turns it on; production is off until launch. Config only: no database toggle, no per-user rollout.
- Off means TV Shows are invisible and inert: TV routes 404, Search drops TV results, the Watchlist and Watched views and progress UI hide TV Shows, the planner skips TV Shows and nothing new is created.
- Off does not stop the daily purge, the cap guard or `catalog:purge`: the 6-month TMDB cap is a licence obligation. Users' facts are untouched, so turning the flag back on restores everything.
