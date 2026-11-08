# The rest of the TV Show page and its first view

Type: grilling
Blocked by: none
Status: resolved

## Question

Beside the Season accordion: what do the overview, the Details panel (creators, network, status, first and last air date, content rating), trailer and cast show for a TV Show, and which of the Movie page's blocks carry over unchanged? How do Special (Season 0), unaired and removed Episodes appear? What does the first view of a TV Show with nothing stored show while the full fetch runs, and what does a `stale` TV Show look like? Which SEO and share-card basics apply (title, description, structured data), given sitemap and wider SEO are out of scope?

## Answer

Settled in a grilling session; no new terms and no ADR. The Season accordion, sticky right panels and whole-TV Show confirm dialog are already decided in the TV Show page and search ticket.

**Carried over from the Movie page, unchanged:** overview with tagline, Where to watch (sticky right column), Trailer (click to play, same player and caption, hidden when there is none) and Cast (top 12 billed, same grid). There is no full-cast page: the `casts` route and its filter stay Movie-only and the Cast section has no "see all" link. TMDB's `videos`, `credits`, `content_ratings` and `external_ids` are appended to the details call.

**Hero:** name, original name when different, "2023–present" or "2023–2025", "{n} Seasons", US content rating badge, genres, External ratings. No runtime (unreliable per show; Episode rows carry it).

**Details panel rows:** Created by, Network, Status (Returning, Ended, Canceled, In production), First aired, Last aired, Seasons and Episodes counts (aired Episodes, Specials excluded), Rated (US), Genres, Original title when different. No "next episode" date (airing calendar is out of scope and the date goes stale).

**Hero actions**

| State | Shown |
| --- | --- |
| Not started | "Add to Watchlist" (accent) and "Mark as watched" (opens the confirm dialog) |
| In progress | the Watchlist toggle (pressed if on it), "Mark as watched", and "{n} of {m} Episodes watched" with the progress bar |
| Completed | the Watchlist toggle, a "Completed" chip with Clear (×) that clears every Episode with one Undo, and the full progress bar |

Guests see the same controls, which start the pending-action flow.

**Episode rows:** number, name, air date, runtime and the Watched toggle. The Episode overview is behind a per-Episode disclosure, collapsed by default. No Episode stills are shown (stills and overviews spoil). Consequence for the Episode data ticket: `still_path` is not needed for display, so the spec leaves it out unless stills are wanted later.

**Season and Episode edge cases**
- Specials is the last section, collapsed, labelled "Specials · don't count toward progress"; its Episodes can be marked.
- Unaired Episodes show "Upcoming · {date}", undated ones "Date to be announced"; neither can be marked and Season bulk actions skip them.
- A Season whose Episodes are all unaired shows collapsed and "Upcoming" with no bulk action. A Season with no Episodes stored is hidden, as are removed Seasons and Episodes.
- The default open Season is the first with an unwatched aired Episode, else the last aired Season; Season 1 for Not started.

**States (as the Movie page):** a first view is one response with no skeletons under the normal progress bar, so the SSR HTML carries the cast and structured data. `stale` renders normally with Where to watch "may be out of date". With the source down and a row present, stored fields, Seasons and Episodes render; Trailer and Cast show "temporarily unavailable" panels; Created by, Network and Rated read "Temporarily unavailable"; the certification badge is hidden. The accordion and Watched controls keep working because they need only stored rows.

**SEO and sharing:** title "{Name} ({first-air year}): where to watch, cast and ratings", description from the overview, Open Graph type `video.tv_show`, share image the backdrop else the poster, canonical tag, JSON-LD `TVSeries` (name, start date, genres, `numberOfSeasons`, `numberOfEpisodes`, creators, image, description, url). Sitemap entries stay out of scope.
