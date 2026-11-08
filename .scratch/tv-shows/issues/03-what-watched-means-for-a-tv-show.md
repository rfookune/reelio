# What Watched means for a TV Show

Type: grilling
Blocked by: none
Status: resolved

## Question

Watched progress is recorded per Episode. Decide what that means for the user: the Watched date per Episode, marking a Season or a whole TV Show at once, what a TV Show-level state is (not started, in progress, completed) and whether it is stored or derived, how Undo works for bulk marks, what happens when new Episodes air after a TV Show was completed, and how this interacts with the Watchlist (does finishing a TV Show leave the Watchlist, as for a Movie).

## Answer

- **Stored and derived:** only Episode-level Watched facts are stored (one Watched date per user per Episode). The TV Show state is derived: **Not started**, **In progress** or **Completed**. "Watched" is the word for a Movie or an Episode, never a TV Show. The terms are in `GLOSSARY.md`.
- **Completed** means every Episode that has aired, in Seasons numbered 1 or higher, is watched. **Specials** (Season 0) can be marked but never count. Unaired and undated Episodes can't be marked and don't count.
- **Bulk actions:** mark a Season, or a whole TV Show (aired Episodes only), as watched, and the two matching Clear actions. Each has one Undo that restores the exact prior set; no cap on size. "Mark everything up to this Episode" is deferred.
- **Dates:** marking one Episode again moves its Watched date to now. A bulk mark only touches Episodes not already watched, so earlier dates stay.
- **Watchlist:** marking an Episode does not remove the TV Show from the Watchlist; it leaves when it becomes Completed. If new Episodes air and it drops back to In progress it does not return on its own. Undo restores both the Episode marks and the Watchlist membership.
- **Watched page:** a TV Show appears once at least one Episode is watched, ordered by its most recent Episode Watched date alongside Movies by their Watched date, with its state on the card.
- **Undo and guests:** the existing Undo rules apply (latest action only, session, about 30 minutes, fails if the state changed), with a compact snapshot of only the Episodes the action changed. Guests use the same sign-in-then-complete flow as for a Movie, with the latest pending action only.
