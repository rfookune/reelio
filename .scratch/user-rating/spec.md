# User rating

Status: ready-for-agent

## Problem Statement

A person using Reelio can see what IMDb and TMDB think of a Movie or a TV Show, but cannot record what they think. To remember how much they liked something they have to keep it in their head or elsewhere, and Reelio gives them no way to tell their own opinion apart from the External ratings.

## Solution

A signed-in user can give any Movie or TV Show a **User rating**: a whole number from 1 to 10. They can set, change and clear it at any time on the Title's page, without having put it on their Watchlist or marked it as Watched. Their rating appears as "Your rating", clearly distinct from the External ratings, on the Title's page and on their Watchlist and Watched lists. Only they can see it. Guests see no rating action.

## User Stories

1. As a signed-in user, I want to give a Movie a rating from 1 to 10, so that I can record how much I liked it.
2. As a signed-in user, I want to give a TV Show a rating from 1 to 10, so that I can record how much I liked the show as a whole.
3. As a signed-in user, I want to rate a Title without marking it as Watched, so that I can rate something I saw before I started using Reelio.
4. As a signed-in user, I want to rate a Title without adding it to my Watchlist, so that rating is not tied to planning to watch.
5. As a signed-in user, I want to rate a TV Show before I have finished it, so that I can score it while I am still watching.
6. As a signed-in user, I want to change my rating, so that my score can follow a change of mind.
7. As a signed-in user, I want to clear my rating, so that a Title can go back to being unrated.
8. As a signed-in user, I want an unrated Title to look different from a low rating, so that I never mistake "no opinion" for "1".
9. As a signed-in user, I want my rating labelled "Your rating" and set apart from the IMDb and TMDB scores, so that I never confuse my opinion with a Rating source's.
10. As a signed-in user, I want to see my rating on the Title's page, so that I can check what I thought before watching again.
11. As a signed-in user, I want to see my rating on the cards of my Watchlist, so that I can weigh what to watch next.
12. As a signed-in user, I want to see my rating on the cards of my Watched list, so that I can look back at what I liked.
13. As a signed-in user, I want removing a Title from my Watchlist not to remove my rating, so that the two stay independent.
14. As a signed-in user, I want clearing a Watched record not to remove my rating, so that the two stay independent.
15. As a signed-in user, I want undoing a Watchlist or Watched change not to alter my rating, so that undo only reverses what I just did.
16. As a signed-in user, I want my rating kept when a Movie or a TV Show becomes a Removed Title, so that I do not lose it when the Catalog source drops the Title.
17. As a signed-in user, I want rating to work with the keyboard and a screen reader, so that I can use it without a mouse.
18. As a signed-in user, I want the rating control to work on a phone, so that I can rate from the sofa.
19. As a signed-in user, I want an invalid rating refused, so that my data stays a whole number from 1 to 10.
20. As another user, I want my ratings invisible to everyone else, so that my opinions stay private.
21. As a guest, I want no rating action shown, so that the page does not offer something I cannot use.
22. As a guest, I want a rating request I send to be refused, so that unauthenticated people cannot write data.
23. As the site operator, I want External ratings to stay unchanged, so that adding User rating cannot disturb Rating source data or syncs.

## Implementation Decisions

- **Meaning.** A **User rating** is one user's private score for a Title (Movie or TV Show). It is not a community score and not an External rating. The glossary already records the term; "Local rating" is to be avoided.
- **Value.** A whole number from 1 to 10. Unrated is `null`, never `0`. No date and no history of earlier scores; one current value per user and Title.
- **Storage.** A nullable rating on the existing per-user Title facts record, as a third fact beside the Date added and the Watched date. The `ratings` table and ADR 0001 are untouched. A community score, if ever wanted, would be a new Rating source in `ratings`.
- **Row lifecycle.** A facts row is deleted only when it holds none of the three facts (Watchlist, Watched, User rating). A rated Title can therefore not be purged, and a Removed Title keeps its user's rating, as it keeps their Watchlist and Watched facts.
- **Independence.** Rating needs neither Watchlist nor Watched. Changing either of those never changes the rating.
- **Undo.** The rating is outside the facts snapshot that undo compares and restores. Undoing a Watchlist or Watched change leaves the rating alone, and restoring must not delete a row that holds only a rating.
- **Concurrency.** Setting or clearing a rating goes through the same row-locked change path as the other facts, so it cannot race with a Watchlist or Watched action on the same Title.
- **Action.** One authenticated action sets or clears the signed-in user's rating for a Title, taking the Title and a value (1–10 or `null`). It never takes a user id; the user is always the signed-in one. It is used for both Movies and TV Shows. Validation rejects anything but an integer 1–10 or `null`.
- **Display.** The Movie and TV Show pages carry a control to set, change and clear the rating, and show "Your rating" apart from the External ratings. Watchlist and Watched card lists show the user's rating when present. Search and Discover show External ratings only. The signed-in user's rating is never sent to anyone else.
- **Guests.** The control is not rendered for guests and the action is refused for them. This is not a pending guest action; nothing is remembered or applied after login.
- **UI shape.** The visual form of the control is deliberately undecided; see the prototype ticket. Implementation of the real control waits for that outcome.
- **Schema change.** One additive migration; no existing data changes.

## Testing Decisions

- A good test drives external behaviour only: act as a user, make an HTTP request, assert on the response, the Inertia props or the stored data. No test depends on how the row is locked or snapshotted internally.
- There is one seam: HTTP feature tests, as in the existing Watchlist, Watched, undo and page tests. No new seam and no unit-level seam; the change path is covered through the actions that use it.
- Behaviours to cover:
    - Set, change and clear a rating on a Movie and a TV Show; invalid values refused; guests refused; a user cannot rate as someone else.
    - Rating without Watchlist or Watched; a rating-only row persists; removing from the Watchlist or clearing Watched keeps the rating; a row with no facts at all is gone.
    - Undo of a Watchlist or Watched change leaves the rating alone and never deletes a rating-only row.
    - Page props: the signed-in user's rating on the Title pages and on the Watchlist and Watched pages; absent on Search and Discover; never visible to another user.
    - Removed Titles keep the rating, and purge or cap paths do not remove a Title that has one.
- Prior art: the Watched, Watchlist, undo, history privacy, removed Titles and cap tests in the feature suite.

## Out of Scope

- A community or aggregate Reelio score.
- Rating Episodes or Seasons.
- Sorting or filtering by rating, and any effect on the Movie picker.
- Rating dates, history, or written reviews.
- Showing a user's rating to anyone else, or sharing it.
- Pending guest actions for rating.
- Showing the rating on Search or Discover.

## Further Notes

- The interaction design of the control is a separate prototype ticket (`issues/01-prototype-the-rating-action.md`); the build tickets follow its result.
- No ADR: the change is additive and easy to reverse.
