# 09: Watchlist for TV Shows

**What to build:** A user can add a TV Show to the Watchlist and remove it from the TV Show page header, with the three state variants of the header actions (Not started, In progress, Completed show the right controls). Endpoints mirror the Movie ones keyed by TMDB id, so a card with no row yet works: they create the row when needed, share the Movie auth, throttle and pending-guest-action rules, and offer Undo. Re-adding a Completed TV Show sets a new Date added and leaves Episode marks untouched.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 06 TV Show page content

**Status:** ready-for-agent

- [ ] Add and remove work from the page header and by TMDB id for a TV Show with no row
- [ ] A guest's add is stored as a pending action and replayed after sign-in
- [ ] Undo restores the Watchlist fact exactly
- [ ] Re-adding a Completed TV Show sets a new Date added and keeps Episode marks
- [ ] A Removed TV Show cannot be added (covered fully by the Removed Title ticket)
