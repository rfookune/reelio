# 03: Rating survives Watchlist, Watched, undo, Removed Titles and purge

**What to build:** A user's rating is independent of everything else they do with a Title. Removing a Title from the Watchlist, clearing Watched, or undoing either never touches the rating; a Removed Title keeps it; and purge and cap paths never delete a Title someone has rated. See [the spec](../spec.md).

**Blocked by:** 02: Set, change and clear a User rating.

**Status:** ready-for-agent

- [ ] Removing a Title from the Watchlist keeps the rating
- [ ] Clearing Watched keeps the rating
- [ ] Undoing a Watchlist or Watched change leaves the rating as it is
- [ ] Restoring never deletes a facts row that holds only a rating, and never changes the rating
- [ ] A Title that becomes a Removed Title keeps each user's rating
- [ ] Purge and cap paths do not delete a Title that any user has rated
- [ ] Feature tests cover each case above, in the style of the existing undo, Removed Titles and cap tests
