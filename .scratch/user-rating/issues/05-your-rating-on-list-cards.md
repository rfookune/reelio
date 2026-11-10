# 05: "Your rating" on Watchlist and Watched cards

**What to build:** The cards on a user's Watchlist and Watched lists show their own rating when they have one, using the look settled in ticket 04. Search and Discover keep showing External ratings only. See [the spec](../spec.md).

**Blocked by:** 02: Set, change and clear a User rating; 04: "Your rating" on the Movie and TV Show pages.

**Status:** resolved

- [x] A Watchlist card shows the user's rating when present and nothing when unrated
- [x] A Watched card shows the user's rating when present and nothing when unrated
- [x] The cards carry only the signed-in user's ratings
- [x] Search and Discover cards and props carry no User rating
- [x] Feature tests cover the Watchlist and Watched page props and the absence on Search and Discover

## Comments

- Watchlist and Watched cards carry a `userRating` (the signed-in user's own, else `null`), read in one extra query per page in `UserListPage`; `PosterCard` shows "Your rating 7/10" with a star when it is set. Search, Discover and the Watchlist empty-state Trending cards carry no such key.
- Limitations: the placeholder cards for unavailable or Removed Titles and the empty-state Trending cards show no rating; the card's rating line repeats the chip's look rather than reusing `UserRating.vue` (which is the interactive control); no browser-level test.
