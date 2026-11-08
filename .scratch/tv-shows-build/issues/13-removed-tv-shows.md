# 13: Removed TV Shows

**What to build:** Handle a TV Show TMDB has removed, as a Removed Title. Its page is a title-less 410 for everyone (`noindex`), sharing one error page and exception with the Removed Movie (noun swapped; the Removed Movie behaviour is unchanged), linking to Discover and Your Watchlist when signed in. A signed-in user with facts sees 'It's still in your lists' with Remove and Clear (all Watched Episodes, one Undo) and 'You marked {n} Episodes as watched', with no Season or Episode names. A Removed TV Show is never fetched again, and every action that would set a fact (including a replayed guest action) gives the 410 or the existing sign-in message. In the lists it joins the 'No longer available' group (heading 'TMDB has removed these, so we can't show their details.') as a missing-poster stub with 'Removed TV show' and the user's own date, with only Remove (Watchlist) or Clear Watched (Watched), no state or progress, included in filter and counts. A visit does not update `last_viewed_at`.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 12 Watchlist and Watched with TV Shows

**Status:** ready-for-agent

- [ ] The 410 page, copy and links match the spec for visitors and signed-in users with facts
- [ ] Remove and Clear work from the page and the stubs, each with Undo
- [ ] Setting a fact on a Removed TV Show is refused; guest replay shows the existing message
- [ ] Stubs appear in the group with the right date and menu, and count in the filter
- [ ] `last_viewed_at` is not updated on the 410; the Removed Movie page and tests are unchanged
- [ ] The cap-cleared 503 page behaves as for a cap-cleared Movie
