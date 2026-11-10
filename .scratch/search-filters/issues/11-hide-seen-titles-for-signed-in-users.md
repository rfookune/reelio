# 11: Hide seen Titles for signed-in users

Status: ready-for-agent

Parent: [spec](../spec.md). Uses the glossary terms **Watched**, **Completed**, **In progress**, **Not started**, **User rating** and **Watchlist**.

**Blocked by:** 02, 03

**What to build:** For a signed-in user, Browse hides Titles they have already seen: a Movie with a Watched record, a Completed TV Show, and any Title with a User rating. TV Shows that are In progress or Not started, and Titles on the Watchlist, stay visible. A "Show seen" toggle in the filter bar turns the hiding off, and is kept in the URL. Guests see no hiding and no toggle. The source returns 20 results a page, so hiding happens on our side and a page can come back short: the next source page is read until the page fills or the source runs out, up to a cap on source pages per request. A page that can't be filled within the cap still reports that more pages exist.

- [ ] A signed-in user's Browse excludes Watched Movies, Completed TV Shows and rated Titles on both tabs, and keeps In progress, Not started and Watchlisted Titles
- [ ] A "Show seen" toggle in the filter bar disables the exclusion, is kept in the URL and is counted among the active filters
- [ ] A guest sees no hiding and no toggle
- [ ] A short page is filled from the next source page, up to a cap on source pages per request, and "more pages" is still reported when the cap is reached
- [ ] The viewer's facts for the Titles shown are still returned for the Watchlist toggles
- [ ] Feature tests cover each hidden and each kept case, the toggle, guests, and the page-filling cap
