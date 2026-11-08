# The TV Show page and search results

Type: prototype
Blocked by: 02, 03
Status: resolved

## Question

How should the TV Show page present Seasons and Episodes with per-Episode Watched controls, at desktop and mobile, how should search present TV Shows next to Movies, and how should the Watchlist and Watched pages show TV Shows beside Movies, with their state (Not started, In progress, Completed) and progress? Make a rough artifact to react to (UI or layout) and settle the shape. Accessibility targets from the MVP spec apply.

## Answer

Prototype (throwaway branch `prototype/tv-shows-page`, never merged; route `/prototype/tv-shows`, file `resources/js/pages/prototype/TvShows.vue`). Verdict, per surface:

- **TV Show page: variant B (accordion).** Every Season is a collapsible section whose header shows "n of m watched", a progress bar and a Season bulk action ("Mark all" / "Clear"); Episodes are compact rows with a round toggle button (`aria-pressed`). The Season in progress opens by default. The right-hand panels (Where to watch, Details) are **sticky at the top** on desktop, as on the Movie page; below `lg` they stack under the overview. The header shows the derived state (Not started, In progress, Completed), "n of m aired Episodes watched" and a progress bar. Upcoming (unaired) Episodes show as "Upcoming" and cannot be marked.
- **"Mark as watched" for the whole TV Show opens a popup (modal) dialog** with a Cancel and a confirm button, replacing the inline confirm in the prototype. The dialog says how many aired Episodes will be marked and that the TV Show leaves the Watchlist. Season-level bulk actions apply at once and offer the single Undo (no dialog).
- **Search: variant C.** One poster grid with All / Movies / TV Shows filter chips and counts (the same control as the Watchlist and Watched pages). A TV Show's poster carries a "TV · n Seasons" badge.
- **Watchlist and Watched: variant C.** Poster grid; a TV Show's poster carries a "TV" badge and a progress bar along its bottom edge, and its caption reads "State · watched/total". A Movie's caption keeps its year.

Not carried over: "Continue" actions on the lists, and "next to watch" (both brush the out-of-scope "up next" feature). Unanswered by the user, so defaulted to the narrower scope.

## Accessibility notes for the spec

Poster-bar progress needs a text equivalent (the caption "State · watched/total" is it); the bar itself is decorative. Season sections use a disclosure button with `aria-expanded`; Episode toggles need names like "Episode 3, Quiet Hours, watched" with `aria-pressed`. Bulk actions and Undo announce through the existing live region. Check the accordion and sticky panels at phone width in the browser journeys.
