# 12: Watchlist and Watched with TV Shows

**What to build:** The Watchlist and Watched pages show Movies and TV Shows in one poster grid with an All / Movies / TV Shows filter (default All, in the URL with the sort) and counts over the whole list including Removed Titles. TV Show cards carry a 'TV' badge, a progress bar along the poster's bottom edge and 'State · watched/total'; the Watchlist menu offers 'Mark as watched' (confirmed) and the Watched menu 'Clear Watched'. The existing sorts apply across both kinds (a TV Show's release date is its first-air date, undated last). The Watched page lists a TV Show once at least one Episode is watched, ordered by its latest Episode Watched date. The Movie picker stays Movies-only, ignores the filter and is hidden when no Movie is pickable. Empty states: an empty Watchlist keeps Trending Movies; an empty filter points to search. With the flag off, TV Shows are hidden.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 10 Season and whole-TV Show bulk actions, 11 Search with TV Shows

**Status:** resolved

- [x] One keyset-paged query serves both kinds with a stable order across sorts
- [x] Filter, sort and counts work and persist in the URL
- [x] TV cards show badge, bottom progress bar and state with counts
- [x] 'Mark as watched' from a card opens the same confirm dialog and offers Undo
- [x] The Watched page orders a TV Show by its latest Episode Watched date alongside Movies
- [x] The picker chooses among Movies only and is hidden when none are pickable
- [x] Movie list tests stay green; focus moves to the next card when a card leaves the Watchlist

## Comments

### 2026-11-08 — Implemented

- One keyset-paged query (`UserListPage`) serves both kinds. The Watchlist reads `user_titles`; Watched unions the Movies' Watched date with, for each TV Show, the latest of the user's Episode marks (a TV Show has no Watched fact of its own and usually no `user_titles` row). Ties break on `titles.id` in every sort, a TV Show's release date is its first-air date, and undated Titles sort last. TV Shows are left out while the flag is off, and a bookmarked `?filter=tv-shows` then shows All.
- The filter is `?filter=movies|tv-shows` (`ListFilter`; left out for All), kept beside `sort` in every cursor link and restore URL; unknown or non-text values give 422.
- Counts and which cards show come from two full fact maps replaced on each reload: `userMovies` and the new `userTvShows` (watchlisted, watched, latest Watched date, state, `watchedCount`, `total`). `ProgressSummaries` computes TV progress for many TV Shows in one query by the same rule as `WatchedProgress` (aired Episodes of the numbered Seasons; Removed Episodes, Removed Seasons and Specials never count) and a test compares the two. A TV Show with only a Special marked is on Watched as "Not started · 0/n".
- TV cards: "TV" badge, a progress bar along the poster's bottom edge and "State · watched/total". The menu offers "Mark as watched" on the Watchlist, through the confirm dialog now shared with the TV Show page (`TvShowMarkDialog`), and "Clear Watched" on Watched. Both go through the existing TV Show endpoints with one Undo, reload `userTvShows`, move focus to the next card when the card leaves, and return it to the restored card on Undo.
- The Movie picker stays Movies-only whatever the filter, and is hidden when no Movie is pickable. An empty filter points to search; an empty Watchlist keeps Trending Movies.
- Renamed, since these now serve both kinds: `UserMovieListPage`, `UserMovieListController`, `UserMovieList` (enum and `.vue`), `MovieListSort` and `UserMovieCardMenu.vue` became `UserListPage`, `UserListController`, `UserList`, `ListSort`, `UserList.vue` and `UserListCardMenu.vue`, and the page prop `movies` became `titles` (merged on `data.key`, a Movie's key being its catalog id and a TV Show's `tv:` and it). Movie-only names stay: `UserMovie`, `userMovies`, `unavailableMovies`, `MoviePicker`, `BookmarkToggle`, `userMovieActions.ts`. A Movie's Undo reveal now matches Movie cards only, and keeps the page's filter.
- Validation: 1080 feature/browser tests, 31 frontend tests, type check, PHPStan, Pint and `vp check` on changed files passed. Browser tests cover the Watchlist journey (chips, confirm, Cancel, focus, Undo) and Watched's Clear with Undo.
- Known limitations: a TV Show with no details is left out of the cards, the facts and the counts until ticket 13 adds its stub (and renames `unavailableMovies`). TV cards have no bookmark toggle on the lists, so a TV Show leaves the Watchlist from its own page or by being marked watched. A TV Show's Undo relies on its card still being in the loaded pages; there is no `restore` URL for it. `userTvShows` is not paged, so each reload re-aggregates every TV Show the user holds a fact about.
