# 04: "Your rating" on the Movie and TV Show pages

**What to build:** On a Movie or TV Show page, a signed-in user sees their own rating labelled "Your rating", visually distinct from the External ratings, and can set, change and clear it with the control designed in the prototype. Guests see no control and receive no rating. One user's rating is never visible to another. See [the spec](../spec.md) and the prototype's answer.

**Blocked by:** 01: Prototype the rating action; 02: Set, change and clear a User rating.

**Status:** resolved

- [x] The page carries the signed-in user's rating (or none) and no other user's
- [x] The control follows the design chosen in ticket 01 (a Rate button in the actions row opening a slider panel; once rated, a Watched-style chip with a × that clears) and sets, changes and clears the rating
- [x] Unrated is visibly different from a low rating
- [x] "Your rating" is labelled and distinct from the External ratings
- [x] The control works with the keyboard and a screen reader, and on a phone width
- [x] Guests see no rating control and the page carries no rating for them
- [x] Feature tests cover the props for a signed-in user, a guest, and a second user who cannot see the first user's rating

## Comments

- Pages carry a top-level `userRating` prop (the signed-in viewer's own rating, else `null`) read through `UserTitle::ratingFor`. The shared `UserRating` control sits in the actions row of both pages for signed-in users only: a Rate button opening a 1–10 slider panel; once rated, a "Your rating 7/10" chip with a × that clears. The panel has no Clear link (the chip's × does it). On phone widths the panel is a bottom sheet; it closes on Escape, an outside click or tabbing out.
- Saves are optimistic and roll back with a toast if refused; only the latest request may roll back.
- Limitations: no browser-level test of the panel interaction (only SSR render tests and the HTTP feature tests); the unrated slider starts at 5 and only Save records it; the TV Show control simply follows "Mark as watched" in the wrapping actions row.
