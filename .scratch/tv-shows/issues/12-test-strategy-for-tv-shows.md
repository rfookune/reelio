# Test strategy for TV Shows

Type: grilling
Blocked by: none
Status: resolved

## Question

How do the MVP spec's test layers (feature tests through HTTP, the TMDB adapter with recorded fixtures, fakes for the Catalog and Rating source seams, frontend-logic and browser journeys, the accessibility sweep, the live group) extend to TV Shows? What fixtures must be recorded, which journeys and pages are added, and what guards the behaviour-preserving Movie to Title slice?

## Answer

Settled in a grilling session; no new terms and no ADR. No new seams: the three in the MVP spec (HTTP, the Catalog and Rating source interfaces, the HTTP client for the TMDB adapter) carry over.

- **Movie to Title slice guard:** the existing Movie suite must stay green with no assertion changes, except where a test names a table or column directly. Test setup moves to factories that build a Title of either kind, with `Movie` and `TvShow` states. No new tests are written for the slice itself.
- **Fixtures (first build task):** record one real returning TV Show from TMDB with `external_ids`, `content_ratings`, `videos`, `credits` and `watch/providers` appended, with Season responses that include a Season 0 and at least one unaired or undated Episode, plus a small Ended one, beside the Movie fixtures. The TMDB adapter tests cover parsing, Season 0 as Specials, unaired and undated Episodes, TV Availability mapping, kind-prefixed rating ids, `episode_type`, 404 as a Removed Title, and the all-or-nothing fetch (one failing Season call writes nothing).
- **Fakes:** the Catalog fake gains TV Shows with Seasons and Episodes and can fail per call (including one Season call) or for every call, so `fresh`, `stale` and `unavailable` stay testable. The Rating fake changes only for kind-prefixed TMDB ids.
- **Feature tests (the bulk):** the derived state (Specials, unaired, undated and removed Episodes not counting); completion removing the TV Show from the Watchlist and not returning on new Episodes; Episode, Season and whole-TV Show actions each with one exact Undo including the Watchlist fact; guests' pending actions; scoped bindings and unaired rejection; whole-TV Show mark with no Episodes stored; cross-kind 301s, the entry route and Removed Title rules; refresh scheduling by TMDB status, purge, cap guard and `catalog:purge` clearing Seasons and Episodes while keeping rows, vanished Episodes marked removed and restored; the `catalog.tv_shows.enabled` flag off; event recording and the `metrics:report` TV block.
- **Frontend logic (browser plugin):** the optimistic Episode toggle with rollback on a failing endpoint, the Undo toast after a Season bulk action, and the whole-TV Show confirm dialog (focus in and back, Escape and Cancel change nothing). The accordion and filter chips are display-only and get no tests of their own.
- **Browser journey:** one new journey at desktop and mobile under the real CSP with `assertNoJavascriptErrors` and an axe check at the end: search for a TV Show, add it to the Watchlist, mark Episodes, mark the rest through the confirm dialog, see it leave the Watchlist and appear in Watched with its state, Undo restores it.
- **Accessibility sweep additions** (desktop and mobile, both themes): the TV Show page (Not started, In progress, Completed; accordion open and closed), Search with TV results, the Watchlist and Watched with TV cards and Removed Title stubs, and a Removed TV Show's 410.
- **Live group:** add a TV Show fetch of one stable long-running title (details with appends, one Season, watch providers) to the weekly live test, asserting whether Season-level appends work to settle that open question from the research.
