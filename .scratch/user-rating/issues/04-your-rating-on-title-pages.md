# 04: "Your rating" on the Movie and TV Show pages

**What to build:** On a Movie or TV Show page, a signed-in user sees their own rating labelled "Your rating", visually distinct from the External ratings, and can set, change and clear it with the control designed in the prototype. Guests see no control and receive no rating. One user's rating is never visible to another. See [the spec](../spec.md) and the prototype's answer.

**Blocked by:** 01: Prototype the rating action; 02: Set, change and clear a User rating.

**Status:** ready-for-agent

- [ ] The page carries the signed-in user's rating (or none) and no other user's
- [ ] The control follows the design chosen in ticket 01 (a Rate button in the actions row opening a slider panel; once rated, a Watched-style chip with a × that clears) and sets, changes and clears the rating
- [ ] Unrated is visibly different from a low rating
- [ ] "Your rating" is labelled and distinct from the External ratings
- [ ] The control works with the keyboard and a screen reader, and on a phone width
- [ ] Guests see no rating control and the page carries no rating for them
- [ ] Feature tests cover the props for a signed-in user, a guest, and a second user who cannot see the first user's rating
