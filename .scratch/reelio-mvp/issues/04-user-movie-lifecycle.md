# The user–movie lifecycle

Type: grilling
Status: open
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
