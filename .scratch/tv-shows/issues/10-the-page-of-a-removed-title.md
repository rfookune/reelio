# The page of a Removed Title

Type: grilling
Blocked by: none
Status: resolved

## Question

What does a Removed TV Show's own page show for visitors and for signed-in users who hold facts (Watched Episodes, a Watchlist stub)? Does it follow the Removed Movie rule (a title-less 410, `noindex`, Remove and Clear actions), and what happens to the Episode Watched facts a user wants to clear? Does the answer change the Removed Movie behaviour, since Removed Title is now the shared term?

## Answer

Settled in a grilling session; no new terms (Removed Title already exists) and no ADR. The Removed Movie page and its behaviour do not change.

- **The page:** a Removed TV Show's page is a title-less 410 for everyone, `noindex`, with links to Discover and (signed in) Your Watchlist. Copy: "This TV show is no longer available. Our data source, TMDB, has removed this TV show, so we can't show its details any more." Both kinds share one error page and exception with the noun swapped.
- **Signed-in users with facts:** "It's still in your lists" with **Remove** (from the Watchlist) and **Clear** (all Watched Episodes, one Undo, toast). When Episodes were watched it adds "You marked {n} Episodes as watched". No Season or Episode names are shown (TMDB data, cleared).
- **Removed stays removed:** never fetched again. Adding it to the Watchlist, marking an Episode, Season or the whole TV Show, or replaying a guest's pending action gives the 410 (or the existing "unknown or removed" sign-in message); only Remove and Clear work. The Episode and Season endpoints respond as for the TV Show.
- **Lists:** Removed TV Shows join the "No longer available" group with Removed Movies, above the grid and not paginated. The heading becomes kind-neutral: "TMDB has removed these, so we can't show their details." A stub is the missing-poster tile with "Removed TV show" and the user's own date: "Added {date}" on the Watchlist, "Last watched {date}" (latest Episode date) on Watched. The Watchlist stub's "⋯" has only Remove, the Watched stub's only Clear Watched. The All / Movies / TV Shows filter and counts include stubs.
- **No state or progress** on a stub (Episodes are cleared, so it cannot be derived); it stays on the Watchlist until removed. The Completed auto-removal cannot apply.
- **Viewing and purge:** a visit to the 410 does not update `last_viewed_at`. A Removed TV Show with no user facts and 30 days unviewed is deleted with its Seasons and Episodes, and its URL then 404s. The cap-cleared 503 page stays as decided in the Episode data ticket.
