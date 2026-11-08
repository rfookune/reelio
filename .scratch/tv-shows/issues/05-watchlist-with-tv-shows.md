# How TV Shows sit on the Watchlist

Type: grilling
Blocked by: 01, 03
Status: resolved

## Question

One Watchlist page holding Movies and TV Shows (with a filter or tabs), or separate lists? Date added, sorts, counts, the Watchlist empty state, the Movie picker staying Movies-only, and the Removed state, all with TV Shows on the list.

## Answer

- **One page:** the Watchlist stays a single poster grid of Movies and TV Shows with an All / Movies / TV Shows filter (default All). The Watchlist / Watched switch is unchanged. Filter and sort live in the URL.
- **TV Show card:** like a Movie card (poster, bookmark toggle, name, first-air year, one External rating) plus its derived state (Not started or In progress) as a small label. The "⋯" menu offers "Mark as watched", meaning every aired Episode; the TV Show becomes Completed and leaves the Watchlist.
- **Confirm guard (amends [What Watched means for a TV Show](03-what-watched-means-for-a-tv-show.md)):** every whole-TV Show "Mark as watched", on the Watchlist card and on the TV Show page, opens a confirm dialog naming the number of aired Episodes it will mark. Season-level marks and the Clear actions stay unguarded and rely on Undo. Undo is still offered after a confirmed mark and restores both Episode marks and Watchlist membership.
- **Sorts:** the existing sorts (newest added by default, oldest added, name, release date) apply across both kinds; a TV Show's release date is its first-air date; undated Titles last. No new sort options.
- **Counts:** the filter shows a count per option over the whole Watchlist, Removed Titles included.
- **Empty states:** an empty Watchlist keeps the Trending Movies suggestions (Discover is Movies-only). An empty filter says there are no TV Shows (or Movies) on the Watchlist and points to search, with no suggestions.
- **Movie picker:** stays on the Watchlist page, chooses among Movies only whatever the filter, genre chips come from Movies only, and it is hidden when no Movie is pickable (including on the TV Shows filter).
- **Removed TV Show:** stays on the Watchlist as a stub card with only Remove in its "⋯" menu, excluded from the picker, Episode Watched facts kept. The glossary gains **Removed Title**. What the page of a Removed TV Show shows stays in the fog.
- **Date added:** same rule for TV Shows. Re-adding a Completed TV Show sets a new Date added and leaves Episode marks untouched; a "start over" action is not part of this effort.
- **Watched page:** gets the same filter and counts; its sorts stay as already decided.
