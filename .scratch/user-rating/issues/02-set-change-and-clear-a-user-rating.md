# 02: Set, change and clear a User rating (Movie and TV Show)

**What to build:** A signed-in user can give a Movie or a TV Show a User rating (a whole number from 1 to 10), change it, and clear it back to unrated, at any time and without the Title being on their Watchlist or marked as Watched. The rating is stored as a third fact beside the Date added and the Watched date, so a user's facts row is deleted only when it holds none of the three. Guests are refused, and a user can only ever rate as themselves. See [the spec](../spec.md); the glossary term is **User rating**. Verified through HTTP feature tests only, so it does not wait for the prototype.

**Blocked by:** None (can start immediately).

**Status:** resolved

- [x] One additive migration adds the nullable rating to the per-user Title facts; unrated is `null`, never `0`; `ratings` and ADR 0001 are untouched
- [x] One authenticated action sets or clears the signed-in user's rating for a Title and works for both a Movie and a TV Show; it takes no user id
- [x] An integer from 1 to 10 sets the rating; `null`, an empty string or whitespace clears it; missing values and all other inputs are refused with a validation error
- [x] Changing the rating replaces the previous value; no date and no history are kept
- [x] Rating works with the Title on neither the Watchlist nor Watched
- [x] The change goes through the same row-locked path as the Watchlist and Watched facts
- [x] A facts row holding only a rating persists; a row holding none of the three facts is deleted
- [x] Guests are refused and nothing is written
- [x] Feature tests cover set, change, clear, validation, guest refusal and rating as a Movie and as a TV Show

## Comments

- Implemented in `05198d5`, with a subsequent change accepting empty strings and whitespace as clearing the rating, as requested. Laravel’s standard input normalization converts these values to `null`; no middleware exception is needed.
- Verified with 31 HTTP feature tests covering Movie and TV Show ratings, set/change/clear, validation, guest refusal, user isolation and facts-row lifecycle. PHP formatting and static analysis pass.
- The initial implementation also passed the full PHP suite (1,009 tests), browser suite (224 tests), JavaScript suite (43 tests) and TypeScript check. The subsequent input-normalization change was verified with the affected rating tests.
