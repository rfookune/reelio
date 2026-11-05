# Watchlist interactions

Type: grilling
Status: open
Blocked by: 04
Map: [Reelio MVP](../map.md)

## Question

How do watchlist and watched actions behave in the UI?

- Optimistic updates and rollback on failure (Inertia v3 optimistic updates vs `useHttp`).
- Which surfaces carry the actions (movie card, detail page, picker).
- How an unauthenticated user's intended action (e.g. "add to watchlist") is preserved through sign-in or registration and completed afterwards (Journey A step 6).
- What feedback the user sees on success or failure.

## Comments

- From [The user–movie lifecycle](./04-user-movie-lifecycle.md) (resolved): Settled actions and rules are in [The user–movie lifecycle](./04-user-movie-lifecycle.md). The UI must support Undo that restores the exact earlier state (including the previous Watched date); marking as watched takes a Movie off the Watchlist.
