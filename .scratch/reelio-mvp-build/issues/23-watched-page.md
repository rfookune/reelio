# 23: Watched page

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** A signed-in user can review everything they've watched, newest first, and clear records from it. See the spec's "Watchlist and Watched pages" section.

**Blocked by:** [22: Watchlist page](./22-watchlist-page.md)

**Status:** ready-for-agent

- [x] `/watched` behind `auth`, client-rendered, `noindex`, reusing the Watchlist page's grid, paging and focus behaviour
- [x] Sorts: Recently watched (default), Oldest watched, Title A–Z, Newest release, Oldest release
- [x] Cards show "Watched {date}", or "Last watched {date}" with a filled bookmark when the Movie is back on the Watchlist; dates in the user's locale, short form, tabular numbers
- [x] The "⋯" menu offers Clear Watched with the toast and Undo
- [x] Empty state: "Nothing marked as watched yet" / "Mark a movie as watched from its page or your Watchlist." with a Watchlist link
- [x] Card links carry the `watched` referrer
- [x] The mark as watched → see it in Watched browser journey at desktop and mobile
