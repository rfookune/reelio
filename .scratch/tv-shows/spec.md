# TV Shows

Status: ready-for-agent

Vocabulary follows `GLOSSARY.md`: Title, Name, Movie, TV Show, Season, Episode, Special, Watched, Watched date, Watched progress, Not started, In progress, Completed, Removed Title, Availability, External rating, Rating source, Catalog source. People see "movie" or "TV show", never "Title". Never the bare word "Show". This spec reverses the MVP's non-goal of TV series tracking. Every decision below is recorded in a ticket under `.scratch/tv-shows/issues/`; the map at `.scratch/tv-shows/map.md` indexes them.

## Problem Statement

Reelio only knows Movies. People also keep a list of TV shows to watch, and they lose their place in them: which Season, which Episode, whether they have finished. They can't search for a TV show, save it, see where it streams or how it is rated, or record how far they have got. They keep that in another app or in their head, so Reelio can't be the one place they decide what to watch next.

## Solution

Add TV Shows beside Movies. A visitor can find a TV Show in search, open its page (Seasons and Episodes, Availability, External ratings, trailer, cast), and a signed-in user can put it on their Watchlist and record Watched progress per Episode. A TV Show's state is derived from its Episodes: Not started, In progress or Completed. The Watchlist and Watched pages show Movies and TV Shows in one grid with an All / Movies / TV Shows filter. A TV Show leaves the Watchlist when it becomes Completed. The Watchlist is TV Show-level; Watched progress is Episode-level; the two stay separate.

The whole feature ships behind a config flag that is off until launch.

## User Stories

### Finding TV Shows

1. As a visitor, I want search results to include TV Shows beside Movies, so that I can find a TV show the way I find a movie.
2. As a visitor, I want All / Movies / TV Shows filter chips with counts on the results, so that I can narrow a mixed list quickly.
3. As a visitor, I want a TV Show's poster to carry a "TV · n Seasons" badge, so that I can tell it from a movie at a glance.
4. As a visitor, I want a TV Show card to show its Name, first-air year and one External rating, so that I can recognise the right one.
5. As a visitor, I want a TV Show with no rating to read "Not yet rated", so that a missing score never looks like zero.
6. As a visitor, I want search results to keep working when the IMDb ratings arrive late, so that cards update without a reload, as they do for movies.
7. As a visitor, I want search to behave the same whether I type a movie's or a TV show's name, so that I don't need to choose a type first.
8. As a visitor, I want a link to a TV Show that has never been opened before to still work, so that I can share or follow any TV show.

### The TV Show page

9. As a visitor, I want a TV Show page with its Name, original Name when different, year range ("2023–present" or "2023–2025"), Season count, US content rating, genres and External ratings, so that I can judge it quickly.
10. As a visitor, I want the overview and tagline, so that I know what the TV show is about.
11. As a visitor, I want to see where the TV Show streams in my country, credited to JustWatch, so that I know where to watch it.
12. As a visitor, I want that Where to watch block to say when it was last updated and warn when it may be out of date, so that I know how far to trust it.
13. As a visitor, I want the Where to watch panel to stay in view as I scroll on desktop, so that I can always act on it.
14. As a visitor, I want a Details panel with Created by, Network, Status, First aired, Last aired, Season and Episode counts, US rating, genres and original Name, so that I have the facts in one place.
15. As a visitor, I want the Episode count to leave out Specials, so that the number matches what counts toward finishing.
16. As a visitor, I want a click-to-play trailer when one exists, so that I can see what it is like before committing.
17. As a visitor, I want the top 12 cast with photos, so that I can recognise who is in it.
18. As a visitor, I want the TV Show page to load as one page with no loading placeholders, so that it reads like a movie page.
19. As a visitor, I want the page to still show what is stored when TMDB is down, with "temporarily unavailable" panels for the rest, so that an outage doesn't hide everything.
20. As a visitor, I want the TV Show page to be shareable with a proper title, description and image, so that links look right when posted.
21. As a visitor, I want a wrong or stale slug to redirect to the right URL, so that old links keep working.
22. As a visitor, I want a movie link that actually belongs to a TV show (or the reverse) to redirect to the right page, so that mixed-up links still work.

### Seasons and Episodes

23. As a visitor, I want each Season as a collapsible section, so that a long TV show doesn't become a wall of Episodes.
24. As a visitor, I want each Season header to show "n of m watched" and a progress bar, so that I see how far I have got in each.
25. As a signed-in user, I want the Season I am in the middle of to be open by default, so that I land on the next Episode to watch.
26. As a signed-in user, I want Season 1 open when I haven't started, and the latest aired Season open when I have finished everything, so that the page always opens somewhere useful.
27. As a visitor, I want each Episode row to show its number, Name, air date and runtime, so that I can tell Episodes apart.
28. As a visitor, I want each Episode's overview behind a disclosure, collapsed by default, so that I am not spoiled by accident.
29. As a visitor, I want Episode stills left out, so that images don't spoil what happens.
30. As a visitor, I want a Specials section, last and collapsed, labelled as not counting toward progress, so that extras are findable but don't confuse my progress.
31. As a user, I want to mark a Special as watched, so that I can record having seen it.
32. As a visitor, I want unaired Episodes to show "Upcoming · {date}" and undated ones "Date to be announced", so that I know they aren't out yet.
33. As a user, I want unaired and undated Episodes not to be markable, so that I can't record something I can't have seen.
34. As a visitor, I want a Season whose Episodes are all unaired to show collapsed and "Upcoming", so that announced Seasons are visible without clutter.
35. As a visitor, I want Seasons and Episodes that TMDB has dropped to disappear from the page, so that I don't see phantom Episodes.

### Marking Episodes as watched

36. As a signed-in user, I want to mark a single Episode as watched with one tap, so that I can record where I am cheaply.
37. As a signed-in user, I want to unmark an Episode, so that I can fix a mistake.
38. As a signed-in user, I want marking an Episode again to move its Watched date to now, so that a rewatch is recorded.
39. As a signed-in user, I want to mark a whole Season as watched, so that I can catch up in one action.
40. As a signed-in user, I want a Season bulk mark to skip Episodes that are unaired, undated or already watched, so that earlier dates stay and nothing impossible is marked.
41. As a signed-in user, I want to clear a whole Season, so that I can undo a bulk mark deliberately.
42. As a signed-in user, I want to mark an entire TV Show as watched, so that I can record one I finished elsewhere.
43. As a signed-in user, I want that whole-TV Show action to ask me to confirm in a dialog that names how many Episodes it will mark, so that I don't mark hundreds by accident.
44. As a keyboard user, I want that dialog to trap focus, close on Escape and return focus to where I was, so that it is usable without a mouse.
45. As a signed-in user, I want Cancel and Escape to change nothing, so that backing out is safe.
46. As a signed-in user, I want to clear everything I have watched of a TV Show in one action, so that I can start fresh.
47. As a signed-in user, I want a toast with Undo after every action, including bulk ones, so that mistakes cost nothing.
48. As a signed-in user, I want one Undo to restore the exact earlier state of a bulk action, including the Watchlist, so that Undo never leaves things half-restored.
49. As a signed-in user, I want Undo to refuse with "That can't be undone any more" if things have changed since, so that it never overwrites newer work.
50. As a signed-in user, I want an Episode toggle to respond immediately and roll back with a message if saving fails, so that the page feels fast and honest.
51. As a signed-in user, I want marking Episodes not to put the TV Show on, or take it off, the Watchlist (except on Completed), so that the two lists stay independent.
52. As a screen-reader user, I want each Episode toggle named with its number and Name and exposed as pressed or not, so that I know what each control does and its state.
53. As a screen-reader user, I want bulk actions and Undo announced politely, so that I know what changed.

### State and progress

54. As a signed-in user, I want a TV Show to be Not started, In progress or Completed, derived from my Episodes, so that I never maintain a state by hand.
55. As a signed-in user, I want Completed to mean every aired Episode in the numbered Seasons is watched, so that Specials and unaired Episodes don't block finishing.
56. As a signed-in user, I want "{n} of {m} Episodes watched" and a progress bar in the page header, so that I see my progress at a glance.
57. As a signed-in user, I want a TV Show to leave my Watchlist when it becomes Completed, so that my Watchlist only holds what is left to watch.
58. As a signed-in user, I want a Completed TV Show not to come back to the Watchlist on its own when new Episodes air, so that nothing reappears unasked.
59. As a signed-in user, I want a Completed chip with a Clear control in the header, so that I can see I'm done and reset in one step.
60. As a signed-in user, I want to add a Completed TV Show to the Watchlist again, with a new Date added and my Episode marks untouched, so that I can queue a rewatch without losing history.

### Watchlist and Watched pages

61. As a signed-in user, I want one Watchlist grid with Movies and TV Shows, so that all I want to watch is in one place.
62. As a signed-in user, I want All / Movies / TV Shows filter chips with counts on the Watchlist and Watched pages, so that I can look at one kind at a time.
63. As a signed-in user, I want the filter and sort in the URL, so that I can bookmark or reload a view.
64. As a signed-in user, I want a TV Show card on the Watchlist to show a "TV" badge, a progress bar along the poster's bottom edge and "State · watched/total" under the Name, so that I see where I am in each.
65. As a signed-in user, I want the existing sorts (newest added, oldest added, name, release date) to apply across both kinds, with a TV Show's release date being its first-air date and undated ones last, so that sorting stays predictable.
66. As a signed-in user, I want a "Mark as watched" action in a TV Show card's menu, with the same confirmation, so that I can finish a TV Show from the list.
67. As a signed-in user, I want the Watched page to list a TV Show once I have watched at least one Episode, ordered by my most recent Episode Watched date alongside Movies, so that it reflects what I actually watched.
68. As a signed-in user, I want the Watchlist's empty state to keep its Trending Movies suggestions, and an empty filter to say there are no TV Shows (or Movies) and point to search, so that I'm never at a dead end.
69. As a signed-in user, I want the Movie picker to keep choosing among Movies only, whatever the filter, so that it stays a movie-night tool.
70. As a keyboard user, I want focus to move to the next card when a card leaves my Watchlist, and back on Undo, so that I keep my place.

### Guests

71. As a guest, I want to see the same controls on a TV Show page, so that I know what I could do.
72. As a guest, I want tapping an Episode, Season or whole-TV Show control to take me to sign in and then complete that action, so that I don't lose it.
73. As a guest, I want nothing saved before I sign in, so that I'm not recorded without an account.
74. As a guest, I want the whole-TV Show confirm dialog to show before my action is stored, so that I don't commit to a bulk action unseen.

### Removed and unavailable TV Shows

75. As a visitor, I want a TV Show that TMDB has removed to give a clear "no longer available" page, so that I understand why there are no details.
76. As a signed-in user with Watchlist or Watched facts on a Removed TV Show, I want to see "It's still in your lists" with Remove and Clear, so that I can tidy up.
77. As a signed-in user, I want that page to tell me how many Episodes I had marked, so that I know what Clear will remove.
78. As a signed-in user, I want a Removed TV Show to stay in my lists as a stub in the "No longer available" group, with my own date, so that nothing silently disappears.
79. As a signed-in user, I want a stub to offer only Remove (Watchlist) or Clear Watched (Watched), so that I can't act on what no longer exists.
80. As a signed-in user, I want a TV Show whose details are temporarily unavailable to show a clear 503 page and still let me Remove and Clear, so that an outage never traps my data.

### Ratings and Availability

81. As a visitor, I want IMDb and TMDB ratings with vote counts and credits on a TV Show, so that I can compare trusted sources.
82. As a visitor, I want TMDB, JustWatch and IMDb credited wherever their data appears, so that I know where it comes from.
83. As a visitor, I want Availability for the TV Show as a whole, with the same layout, country rules and offer types as a movie, so that I know where to watch.
84. As a signed-in user, I want the Watch link to take me to TMDB's TV watch page, so that I can jump straight to the service.

### Owner and operations

85. As the owner, I want the whole feature behind a config flag that is off by default, so that I can ship the code before launch.
86. As the owner, I want the flag off to make TV Shows invisible and inert (routes 404, search drops TV results, lists hide TV Shows, no new rows) while the daily purge and cap guard still run, so that the TMDB licence rule holds regardless.
87. As the owner, I want TV Shows refreshed every 3 days while airing and every 30 days once ended, only when someone holds a fact about them, so that data stays fresh without waste.
88. As the owner, I want a TV Show with no user facts and no views in 30 days purged with its Seasons and Episodes, so that I keep no TMDB data I don't need.
89. As the owner, I want all TMDB data on a TV Show, its Seasons and Episodes cleared at 170 days without a successful sync while rows and Watched facts stay, so that the 6-month cap is honoured without losing users' data.
90. As the owner, I want `catalog:purge` to clear Seasons, Episodes and TMDB ratings of both kinds, so that I can comply if the TMDB licence ends.
91. As the owner, I want one all-or-nothing fetch per TV Show, so that a half-fetched TV Show never shows wrong progress.
92. As the owner, I want events for TV Show actions and a TV Shows block in `metrics:report`, so that I can see whether TV Shows are used.
93. As the owner, I want IMDb for TV Shows to be removable by the existing switch, so that I can drop it if IMDb withdraws permission.

## Implementation Decisions

### Data model

- **Shared Title base** ([ADR 0002](../../docs/adr/0002-shared-title-base-for-movies-and-tv-shows.md)). One `titles` table with a `kind`, unique on `(kind, tmdb_id)`. Kind-specific columns live in one-to-one `movie_details` and `tv_show_details`. `Title` is the base model; `Movie` and `TvShow` are subtypes that scope to their own kind, so Movie-only code keeps working. Genres, Availability, Streaming services, user facts and (later) likes point at a Title. Nothing is live, so the existing migrations are edited in place and the local database is rebuilt; no compatibility migration.
- **First build slice:** the behaviour-preserving generalisation of Movie to Title, user-visible no-op, with the Movie suite green.
- **Seasons and Episodes** are TV-only tables hanging off a Title, matched by TMDB id and never by number.
    - Season: TMDB id, `season_number`, name, overview, poster path, air date, episode count. Season 0 is stored like any Season and is the Specials group.
    - Episode: TMDB id, `season_number`, `episode_number`, name, overview, air date, runtime, `episode_type`. No votes, crew, guest stars or Episode IMDb ids. Stills are not displayed, so `still_path` is not stored.
    - A Season or Episode missing from a successful refresh is marked removed, not deleted: hidden, excluded from counts, Watched facts kept, restored if it reappears.
- **Watched progress** is Episode-level only: one Watched date per user per Episode. The TV Show state (Not started, In progress, Completed) is derived, never stored. Completed means every aired Episode in Seasons numbered 1 or higher is watched; Specials, unaired, undated and removed Episodes never count.
- **Watchlist** is TV Show-level, on the same user-facts mechanism as Movies. Completion removes the TV Show from the Watchlist; it never returns on its own. Re-adding sets a new Date added and leaves Episode marks untouched. "Start over" is not part of this effort.
- **Ratings** stay in the one `ratings` table with the key unchanged ([ADR 0001](../../docs/adr/0001-one-ratings-table-for-every-rating-source.md)). TMDB ids are only unique within a kind, so the TMDB source's id mapping produces a kind-prefixed `source_id` (`movie:550`, `tv:1399`). IMDb keeps its `tt` id, already unique across kinds. Seasons and Episodes get no ratings.
- **Availability** (`availabilities`, `streaming_services`) is keyed to a Title and exists at TV Show level only. Rows are replaced wholesale on each full refresh. The TV Show's single `synced_at` drives "Updated n days ago" and the 7-day "may be out of date" notice.

### Catalog fetching and refresh

- **One refresh operation per TV Show** updates the details (with `external_ids`, `content_ratings`, `videos`, `credits` and `watch/providers` appended), every Season and its Episodes, Availability and the TMDB rating, and sets one `synced_at` on the Title. About 1 + N requests, always a full refetch, never incremental.
- **In-request fetch** (first view, and a view when `synced_at` is over 24 h old): details and all Seasons concurrently under one overall interactive timeout, all-or-nothing. On any failure nothing is written: a stored TV Show renders `stale`, a first view with nothing stored gets the unavailable page. A TV Show fetch counts as one against the row-creation limit and the circuit breaker. If the timeout proves too tight, dispatching the refresh as a job is the escape hatch, decided on evidence.
- **Scheduled refresh** by TMDB status, only with user facts (a Watchlist entry or any Watched Episode): airing (Returning Series, In Production, Pilot) every 3 days; Ended or Cancelled every 30 days. One unique job per TV Show from the same planner.
- **Purge and cap guard** reuse the Title-level rules. A TV Show with no user facts, not viewed for 30 days, is deleted with its Seasons and Episodes. At 170 days without a successful sync every TMDB-derived column on the Title, its Seasons and its Episodes is cleared; rows, ids and TMDB ids stay, so Watched facts survive and re-matching works. The same 503 "Details temporarily unavailable" page applies and the next successful refresh restores everything. `catalog:purge` clears Seasons and Episodes too, and TMDB ratings of both kinds.
- **Removed Title.** A TV Show whose details call returns 404 becomes a Removed TV Show, kept as a row, never fetched again.
- **Feature flag** `catalog.tv_shows.enabled` in the catalog config, env `CATALOG_TV_SHOWS`, default off; the test setup turns it on. Config only: no database toggle, no per-user rollout. Off means TV routes 404, Search drops TV results, the Watchlist and Watched views and progress UI hide TV Shows, the planner skips TV Shows and nothing new is created. Off does not stop the daily purge, the cap guard or `catalog:purge`, because the 6-month TMDB cap is a licence obligation. Users' facts are untouched, so turning the flag back on restores everything.

### Rating sources

- The TV Show page shows IMDb then TMDB with vote counts and credits, as for a Movie; cards show one rating (IMDb if known, else TMDB, else "Not yet rated").
- A TV Show's `imdb_id` comes from `external_ids` appended to the details call, at no extra request. Search and Trending cards for TV Shows use the same after-render IMDb lookup as Movies, extended to TV ids. The daily IMDb dataset sync is unchanged: the file already contains TV series.
- IMDb's permission is for the non-commercial ratings dataset, which contains TV series. IMDb scores for TV Shows ship on the same permission and credit wording. **Assumption to re-confirm with IMDb before launch.** The existing source switch hides IMDb everywhere if permission is withdrawn.

### URLs and endpoints

- **Canonical** `/tv-shows/{slug}-{id}` using the local Title id; the slug is decorative and a wrong one gets a 301. **Entry route** `/tv-shows/tmdb/{tmdbId}`: TMDB confirms the id, the row is created, then a 301 to the canonical URL; rate-limited as for Movies. Movie routes do not change.
- Local ids are shared across kinds, so `/movies/{slug}-{id}` for a TV Show's id (and the reverse) 301s to the other kind's canonical URL. With the flag off, TV URLs and the cross-kind redirect 404.
- Watchlist, whole-TV Show Watched and the Watch redirect mirror the Movie endpoints under the TV prefix, keyed by TMDB id so cards without a row work: watchlist add and remove, whole-TV Show watched mark and clear, and the Watch redirect to TMDB's TV watch page. Same auth, pending-guest-action and throttle rules.
- Season and Episode Watched are nested and keyed by local ids (they exist only once the row does): per Season, and per Episode within a Season. Scoped bindings 404 an Episode outside its Season or TV Show, and a removed Season or Episode. Unaired Episodes are rejected with a validation error.
- Whole-TV Show "Mark as watched" with no Episodes stored (cap-cleared, or a failed fetch) first tries the in-request refresh; if that fails it changes nothing and shows "Details temporarily unavailable". It never marks a partial set.
- **Guests.** A guest's Episode, Season or whole-TV Show action is stored as their single pending action (extended to carry the Season or Episode id), they sign in, and it is replayed. Nothing is saved before sign-in. The confirm dialog for the whole-TV Show action shows before it is stored.
- **Undo.** One endpoint by TV Show TMDB id. The per-user latest-action snapshot holds, for Episode, Season and whole-TV Show actions, the previous Watched dates of the affected Episodes plus the Watchlist fact. Undo restores only if current state still matches what the action produced, otherwise "That can't be undone any more". A bulk action, including the Watchlist removal, is one Undo; there is no cap on size. "Mark everything up to this Episode" is deferred.

### Pages

- **TV Show page.** Prototype on the local branch `prototype/tv-shows-page` (variant B of the TV Show page; read it for layout, not as code to promote).
    - Hero: Name, original Name when different, year range, "{n} Seasons", US content rating badge, genres, External ratings. No runtime.
    - Header actions by state: Not started: "Add to Watchlist" (accent) and "Mark as watched". In progress: the Watchlist toggle, "Mark as watched", and "{n} of {m} Episodes watched" with the progress bar. Completed: the Watchlist toggle, a Completed chip with Clear (one Undo), and the full bar.
    - Whole-TV Show "Mark as watched" opens a modal confirm dialog naming the aired Episode count, with Cancel and a confirm button. It is guarded everywhere it appears (page header, Watchlist card menu). Season-level marks and the Clear actions are unguarded and rely on Undo.
    - Season accordion: each Season header shows "n of m watched", a progress bar and a Season bulk action; Episode rows are compact with a round toggle (`aria-pressed`). Specials is last, collapsed and labelled. Default open Season is the first with an unwatched aired Episode, else the last aired Season; Season 1 for Not started.
    - Right column (desktop): Where to watch and Details, sticky at the top as on the Movie page; below `lg` they stack under the overview.
    - Carried over unchanged from the Movie page: overview with tagline, Trailer (click to play, hidden when none), Cast (top 12; no full-cast page, no cast route for TV Shows).
    - Details rows: Created by, Network, Status (Returning, Ended, Canceled, In production), First aired, Last aired, Seasons and Episodes counts (aired, Specials excluded), Rated (US), Genres, Original title when different. No next-episode date.
    - States as the Movie page: one response, no skeletons, SSR carries the cast and structured data; `stale` renders normally with Where to watch "may be out of date"; with the source down and a row present, stored fields, Seasons and Episodes render, Trailer and Cast show "temporarily unavailable" panels, Created by, Network and Rated read "Temporarily unavailable" and the certification badge is hidden; the accordion and Watched controls keep working from stored rows.
    - SEO: title "{Name} ({first-air year}): where to watch, cast and ratings", description from the overview, Open Graph type `video.tv_show`, share image the backdrop else the poster, canonical tag, `TVSeries` JSON-LD (name, start date, genres, number of Seasons and Episodes, creators, image, description, url). Sitemap entries are out of scope.
- **Search.** One poster grid with All / Movies / TV Shows chips and counts (client-side filtering of the one grid); TV Show posters carry a "TV · n Seasons" badge. Search is `title_searched` for analytics.
- **Watchlist and Watched.** One poster grid, All / Movies / TV Shows filter (default All) with counts over the whole list, Removed Titles included. A TV Show card shows a "TV" badge, a progress bar along the poster's bottom edge and "State · watched/total". Its menu offers "Mark as watched" (confirmed) on the Watchlist and "Clear Watched" on Watched. Sorts are unchanged and apply across both kinds. The Watched page lists a TV Show once at least one Episode is watched, ordered by its latest Episode Watched date. The Movie picker stays Movies-only and is hidden when no Movie is pickable.
- **Removed Title page.** A Removed TV Show is a title-less 410 for everyone, `noindex`, linking to Discover and (signed in) Your Watchlist, with the copy "This TV show is no longer available. Our data source, TMDB, has removed this TV show, so we can't show its details any more." Both kinds share one error page and exception with the noun swapped; the Removed Movie page and behaviour do not change. A signed-in user with facts sees "It's still in your lists" with Remove (from the Watchlist) and Clear (all Watched Episodes, one Undo), plus "You marked {n} Episodes as watched" when applicable; no Season or Episode names. Removed stays removed: any action that would set a fact (including a guest's replayed pending action) gives the 410 or the existing "unknown or removed" sign-in message. A visit to the 410 does not update `last_viewed_at`. The cap-cleared 503 page is as for a cap-cleared Movie.
- **"No longer available" group.** Removed TV Shows join Removed Movies in the existing group, above the grid and not paginated; heading "TMDB has removed these, so we can't show their details." A stub is the missing-poster tile with "Removed TV show" and the user's date ("Added {date}" on the Watchlist, "Last watched {date}" on Watched). No state or progress is shown. The Watchlist stub's menu has only Remove; the Watched stub's only Clear Watched. The filter and counts include stubs.

### Events and metrics

- Events are signed-in only, recorded after the response, one per user action (never per Episode). Movie events keep their names, properties and meaning. New events:

    | Event                                                | Properties                                                                              |
    | ---------------------------------------------------- | --------------------------------------------------------------------------------------- |
    | `tv_show_viewed`                                     | `tv_show_id`, `referrer` (as `movie_viewed`, without `picker`)                          |
    | `tv_show_watchlisted`, `tv_show_unwatchlisted`       | `tv_show_id`, `source`                                                                  |
    | `episode_marked_watched`, `episode_unmarked_watched` | `tv_show_id`, `season_id`, `episode_id`, `source`, `was_on_watchlist`                   |
    | `season_marked_watched`, `season_unmarked_watched`   | `tv_show_id`, `season_id`, `episode_count`                                              |
    | `tv_show_marked_watched`, `tv_show_unmarked_watched` | `tv_show_id`, `source`, `episode_count`                                                 |
    | `tv_show_completed`                                  | `tv_show_id`                                                                            |
    | `tv_show_action_undone`                              | `tv_show_id`, `action`, plus `season_id` or `episode_id` when the undone action had one |

- Two events become kind-neutral: `movie_searched` becomes `title_searched` (same properties; `result_count` counts all page-1 results of both kinds) and `availability_link_clicked` becomes `{title_id, kind, country}`.
- `season_id` and `episode_id` are local ids stored as plain integers with no foreign key. Whole-TV Show events carry no Season or Episode ids. No view event on the Removed Title 410 or the cap-cleared 503. With the flag off no TV events occur. A guest's replayed pending action records on replay.
- `metrics:report` counts both kinds so a TV-only user is not invisible: weekly returning activates on a first `movie_watchlisted` or `tv_show_watchlisted`; search-to-watchlist counts viewers from `movie_viewed` or `tv_show_viewed` and conversion by either kind's Watchlist event; picker usage is unchanged. A new TV Shows block shows, per window, TV Shows watchlisted, users who marked at least one Episode, Episode marks per marking user, and TV Shows completed.
- The 13-month roll-up is unchanged: one row per day and event name with a count, no properties; no TV event gets a stored-totals exception.

### Glossary

`GLOSSARY.md` already holds Title, Name, Removed Title, TV Show, Season, Episode, Watched progress, Special, Not started, In progress and Completed, and the entries for Rating source, External rating and Availability now cover TV Shows.

## Testing Decisions

- **What makes a good test:** it drives the app through its highest seam, the HTTP request, and asserts what a user or client sees: Inertia props, status codes, redirects, headers and database state. It never asserts how a module does its work. No feature test knows TMDB's JSON shape.
- **Seams: no new ones.** The three from the MVP spec carry over: HTTP requests; the Catalog adapter and Rating source interfaces (bound to in-memory fakes in feature and browser tests); and the HTTP client, for the TMDB adapter alone, tested with `Http::fake()` against recorded fixtures. `Http::preventStrayRequests()` stays on.
- **Guard for the Title slice:** the existing Movie suite must stay green with no assertion changes, except where a test names a table or column directly. Test setup moves to factories that build a Title of either kind, with `Movie` and `TvShow` states. No new tests are written for the slice itself.
- **Fixtures (first build task):** record one real returning TV Show from TMDB with the appended sub-requests, with Season responses that include a Season 0 and at least one unaired or undated Episode, plus a small Ended one, beside the Movie fixtures.
- **TMDB adapter tests:** parsing, Season 0 as Specials, unaired and undated Episodes, TV Availability mapping (`free` and `ads` to `free`), kind-prefixed rating ids, `episode_type`, 404 as a Removed Title, and the all-or-nothing fetch (one failing Season call writes nothing).
- **Fakes:** the Catalog fake gains TV Shows with Seasons and Episodes and can fail per call (including one Season call) or for every call, so `fresh`, `stale` and `unavailable` stay testable. The Rating fake changes only for kind-prefixed TMDB ids.
- **Feature tests (the bulk):**
    - the derived state, with Specials, unaired, undated and removed Episodes not counting;
    - completion removing the TV Show from the Watchlist, and it not returning when new Episodes air;
    - Episode, Season and whole-TV Show actions, each with one exact Undo including the Watchlist fact;
    - guests' pending actions;
    - scoped bindings and unaired rejection;
    - whole-TV Show mark with no Episodes stored;
    - cross-kind 301s, the entry route and Removed Title rules (410, stubs, Remove and Clear, never re-fetched);
    - refresh scheduling by TMDB status, purge, cap guard and `catalog:purge` clearing Seasons and Episodes while keeping rows, vanished Episodes marked removed and restored;
    - the flag off;
    - event recording and the `metrics:report` TV block.
- **Frontend logic (browser plugin, only client logic that decides):** the optimistic Episode toggle with rollback on a failing endpoint, the Undo toast after a Season bulk action, and the whole-TV Show confirm dialog (focus in and back, Escape and Cancel change nothing). The accordion and filter chips are display-only and get no tests of their own.
- **Browser journey:** one new journey at desktop and mobile under the real CSP with `assertNoJavascriptErrors` and an axe check at the end: search for a TV Show, add it to the Watchlist, mark Episodes, mark the rest through the confirm dialog, see it leave the Watchlist and appear in Watched with its state, Undo restores it.
- **Accessibility sweep additions** (desktop and mobile, both themes): the TV Show page (Not started, In progress, Completed; accordion open and closed), Search with TV results, the Watchlist and Watched with TV cards and Removed Title stubs, and a Removed TV Show's 410.
- **Live group:** add a TV Show fetch of one stable long-running title (details with appends, one Season, watch providers) to the weekly live test, asserting whether Season-level appends work.
- **Prior art:** the Movie equivalents of every layer above in the MVP spec's test strategy, and the existing request-level Pest tests.

## Out of Scope

- Discover sections for TV Shows, the Movie picker for TV Shows, and sitemap and SEO beyond the page basics: follow-ups once the core model is settled.
- Season-level Availability (TMDB offers it at one extra provider call per Season; the TV Show-level block covers the need) and any Episode-level Availability.
- Playback, social features, "up next" and new-episode notifications, an airing calendar, a next-episode date, per-Episode or per-Season ratings, Episode stills, importing history from other services.
- "Mark everything up to this Episode" and a "start over" action for a Completed TV Show.
- A full-cast page for TV Shows.
- Local ratings, like/dislike and the Discover filters: separate efforts that wait on the model decision.

## Further Notes

- **Decision record:** the twelve tickets under `.scratch/tv-shows/issues/` hold the detail and reasons; the TMDB research findings are on the pushed throwaway branch `research/tv-shows-tmdb-data-shape` (read with `git show origin/research/tv-shows-tmdb-data-shape:.scratch/tv-shows/research/tmdb-tv-data-shape.md`), and the page prototype is on the local, unpushed branch `prototype/tv-shows-page`.
- **Open facts the build settles:** whether `append_to_response` works on Season requests, whether show-level Season and Episode counts include Season 0, and whether the in-request fetch fits the interactive timeout. The live test group asserts the first; the second is handled by counting only Seasons numbered 1 or higher for display; the third is the job-dispatch escape hatch.
- **Pre-launch checklist addition:** re-confirm with IMDb that the permission covers TV Shows; the flag stays off until then.
- **Suggested build order:** record the TV fixtures; generalise Movie to Title with the Movie suite green; add the Season and Episode tables, the TMDB TV adapter and the full-fetch refresh behind the flag; then the TV Show page, Episode, Season and whole-TV Show actions with Undo, the lists and search, Removed Title handling, and finally events and metrics.
