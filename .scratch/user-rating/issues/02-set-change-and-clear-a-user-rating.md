# 02: Set, change and clear a User rating (Movie and TV Show)

**What to build:** A signed-in user can give a Movie or a TV Show a User rating (a whole number from 1 to 10), change it, and clear it back to unrated, at any time and without the Title being on their Watchlist or marked as Watched. The rating is stored as a third fact beside the Date added and the Watched date, so a user's facts row is deleted only when it holds none of the three. Guests are refused, and a user can only ever rate as themselves. See [the spec](../spec.md); the glossary term is **User rating**. Verified through HTTP feature tests only, so it does not wait for the prototype.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [ ] One additive migration adds the nullable rating to the per-user Title facts; unrated is `null`, never `0`; `ratings` and ADR 0001 are untouched
- [ ] One authenticated action sets or clears the signed-in user's rating for a Title and works for both a Movie and a TV Show; it takes no user id
- [ ] Only an integer from 1 to 10, or `null` to clear, is accepted; anything else is refused with a validation error
- [ ] Changing the rating replaces the previous value; no date and no history are kept
- [ ] Rating works with the Title on neither the Watchlist nor Watched
- [ ] The change goes through the same row-locked path as the Watchlist and Watched facts
- [ ] A facts row holding only a rating persists; a row holding none of the three facts is deleted
- [ ] Guests are refused and nothing is written
- [ ] Feature tests cover set, change, clear, validation, guest refusal and rating as a Movie and as a TV Show
