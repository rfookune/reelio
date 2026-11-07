# 24: Removed Movies in lists and on the 410 page

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Users can still see and tidy Movies TMDB has removed: they sit in a "No longer available" group on the lists, and the 410 page offers Remove and Clear. See the spec's Removed Movie bullets.

**Blocked by:** [14: Refresh on view, and Removed Movies](./14-refresh-on-view-and-removed-movies.md), [17: Undo](./17-undo.md), [23: Watched page](./23-watched-page.md)

**Status:** ready-for-agent

- [x] A "No longer available" group above each grid, hidden when empty, headed "TMDB has removed these movies, so we can't show their details.", not paginated or sorted with the grid, counted in the header
- [x] Each card is the missing-poster tile with "Removed movie" and the user's own date ("Added {date}" / "Watched {date}"), linking to the 410 page; its "⋯" offers Remove (Watchlist) or Clear Watched (Watched)
- [x] On the 410 page a signed-in user with facts sees "It's still in your lists" with "On your Watchlist" + Remove and "Watched {date}" + Clear, plus a Your Watchlist link
- [x] All these actions use the normal toast and Undo
- [x] Feature tests cover the group, the actions and Undo on a Removed Movie
