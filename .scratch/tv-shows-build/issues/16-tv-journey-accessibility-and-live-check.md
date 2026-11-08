# 16: TV journey, accessibility sweep and live check

**What to build:** Finish verification. Add one browser journey at desktop and mobile under the real CSP with `assertNoJavascriptErrors` and an axe check at the end: search for a TV Show, add it to the Watchlist, mark Episodes, mark the rest through the confirm dialog, see it leave the Watchlist and appear in Watched with its state, and Undo restores it. Extend the accessibility sweep (desktop and mobile, both themes) with the TV Show page in Not started, In progress and Completed with the accordion open and closed, Search with TV results, the Watchlist and Watched with TV cards and Removed Title stubs, and a Removed TV Show's 410. Add a TV Show fetch of one stable long-running title (details with appends, one Season, watch providers) to the weekly live test group, asserting whether Season-level appends work.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 13 Removed TV Shows, 14 TV refresh, purge and cap guard

**Status:** ready-for-agent

- [ ] The journey passes at desktop and mobile with no JavaScript errors and no axe issues
- [ ] The sweep covers every added page and state in both themes
- [ ] The live group fetches a TV Show and records whether Season-level appends work
- [ ] The full CI check passes with the flag on in tests and off by default
