# The user–movie lifecycle

Type: grilling
Status: resolved
Map: [Reelio MVP](../map.md)

## Question

What are the states a user's relationship with a Movie can be in, the transitions between them, and the rules for each, and what are they called?

Cover at least the following:

- Watchlist membership and Watched status as independent facts.
- What happens to watchlist membership when a Movie is marked Watched. The PRD says the Movie drops out of the default view but may stay on the watchlist; who chooses?
- Restoring a watched Movie to the watchlist.
- Removing from the watchlist vs clearing the Watched record.
- When the watched date is recorded automatically (the P0 journey) when manual dates are P1.
- What account deletion does to this data.

Settle the glossary terms (e.g. Watchlist, Watched, the per-user record itself) and test them against edge-case scenarios.

## Answer

**Model.** A user and a Movie have two independent facts: being on the **Watchlist** (with a date added) and being **Watched** (with a **Watched date**). That gives four states and nothing else in P0. The record holding both has no domain term (the PRD's `user_movies` is an implementation detail). Both facts are wholly private to the user: no public profile, no sharing, no aggregate counts. Neither fact is ever deleted as a side effect of catalog changes (a Movie dropped or merged by the Catalog source, or its cached metadata purged).

**Actions.**

| Action                                                    | Effect                                                                                    |
| --------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| Add to Watchlist                                          | Adds it with today's date added. Already on the Watchlist: no change, original date kept. |
| Remove from Watchlist                                     | Removes only the Watchlist membership. Re-adding later gets a new date added.             |
| Mark as watched (not yet Watched)                         | Sets Watched and the Watched date to now; takes it off the Watchlist if it was on.        |
| Mark as watched (Watched and on the Watchlist: a rewatch) | Takes it off the Watchlist and moves the Watched date to now.                             |
| Mark as watched (Watched, not on the Watchlist)           | No change.                                                                                |
| Clear Watched                                             | Removes only Watched and its date. Does not touch the Watchlist.                          |
| Undo (immediately after an action)                        | Restores the exact earlier state, including the previous Watched date.                    |
| Delete account                                            | Hard-deletes all of this data with the user; no anonymised copy.                          |

**Consequences.**

- The Watchlist always means "want to watch (again)". The PRD's "watched Movie stays on the watchlist, hidden" (§8.3) is rejected.
- Watchlist page: exactly the Watchlist, with a "Watched" marker on rewatch Movies. FR-008's "filter by watched status" is dropped. Watched page: every Watched Movie, newest Watched date first.
- Movie picker candidates are exactly the Watchlist, before filters.
- The Watched date is the most recent time the user saw the Movie; a single value. P1 manual dates edit the same value; a rewatch log is a follow-up.
- Analytics records one event per user action; marking as watched does not also record a Watchlist removal.
- Glossary terms settled: Watchlist, Watched, Watched date.

- From [Watchlist and Watched pages](./25-watchlist-and-watched-pages.md) (resolved): the "Watched" marker on rewatch Movies is a quiet "Last watched {date}" line on the Watchlist card, matching the detail page. The Watched page's default sort is Recently watched, as decided here.
