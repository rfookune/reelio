# Movie picker

Type: grilling
Status: open
Blocked by: 04, 09
Map: [Reelio MVP](../map.md)

## Question

How does the P0 random movie picker behave?

- Which filters it offers in P0, given that "respect applied filters" is P0 but the filter dimensions listed are P1.
- Whether selection happens server-side.
- What "Pick Again" does: may it return the same Movie twice in a row?
- The empty-candidate message.
- Where the picker appears (modal vs page) and what "essential details" it shows.

## Comments

- From [The user–movie lifecycle](./04-user-movie-lifecycle.md) (resolved): The Watchlist is exactly the picker's candidate set before filters (a Watched Movie is only on the Watchlist if the user re-added it to watch again). See [The user–movie lifecycle](./04-user-movie-lifecycle.md).
