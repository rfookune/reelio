# Movie detail page

Type: prototype
Status: resolved
Map: [Reelio MVP](../map.md)

## Question

What does the Movie page look like and contain?

- Layout of the backdrop, poster, title block, overview, director and cast, genres and runtime, both External ratings with credits, the trailer, and the Availability block ([Country and Availability presentation](./11-country-and-availability.md)).
- Where the Watchlist and Watched actions sit ([Watchlist interactions](./13-watchlist-interactions.md)).
- Trailer behaviour: inline `youtube-nocookie` embed vs click-to-load facade, and the fallback link when embedding is disabled.
- States: loading skeletons, `stale` data, a dropped Movie (shell, 410), and no trailer, ratings or Availability.
- Builds on [Visual language and app shell](./06-visual-language-and-shell.md).

## Answer

Chosen over four prototype rounds. The layout is variant A, "Backdrop hero + side rail", with variant C's cast grid. Where to watch uses design A2, "Details, then tabs".

The prototype is throwaway and never merged: branch `prototype/movie-detail-page`, route `/prototype/movie` (local only). Open it with `?variant=A2`; the `state`, `facts`, `auth`, `trailer`, `country`, `imdb` and `theme` toggles switch the states below. Its data is the live TMDB *Interstellar* fixture.

**Layout (desktop, from 1024px)**

- **Hero.**
    - A full-bleed backdrop at `w1280`, with the top bar floating over it.
    - The poster sits bottom-left.
    - Next to it:
        - the title, and the original title when it differs;
        - a meta line: year · runtime · certification badge · genres;
        - both External ratings;
        - the actions.
    - No backdrop: a blurred poster fills the hero.
- **The hero fades into the page.**
    - The backdrop dissolves (a CSS mask) over a band kept clear below the title block: 7rem on mobile, 10rem from `md`. White hero text never sits on the faded part.
    - A blurred copy of the same backdrop glows behind the top of the body, as on Discover.
    - The body is pulled up into the bottom of that band.
- **Body: two columns.**
    - Left: tagline, overview, Trailer, Cast.
    - Right: the Details card first, then Where to watch.
- **The right column is sticky.**
    - It sticks 1.5rem below the top of the viewport.
    - When it is taller than the viewport, it scrolls with the page until its bottom is in view, then sticks there. Its `top` is `min(1.5rem, viewport height − column height − 1.5rem)`, re-measured whenever its size or the viewport changes. Nothing in it is ever out of reach.
    - Checked at 1440×900 with Canadian Availability: the column is 1029px tall, and its bottom pins 24px above the viewport edge.
- **Mobile (below 1024px): one column**, in this order: hero (no poster), overview, Where to watch, Trailer, Cast, Details.
- **Details card:**
    - Director(s);
    - Writers;
    - Released (US): the US theatrical date from `release_dates` (type 3, else the earliest type 2). Without a US date, the primary `release_date` labelled "Released";
    - Runtime;
    - Rated: the US certification;
    - Genres;
    - Original title, when it differs.
- **Cast:**
    - the top 12 billed, in a photo grid of 3, 4 or 6 columns;
    - each with name and character;
    - `w185` profile photos, lazy-loaded;
    - a person-icon tile when there is no photo.

**External ratings**

- Inline in the hero: the source name in the source's colour, the score, and a compact vote count ("IMDb 8.7 2.4M · TMDB 8.5 41.8K").
- Whenever the IMDb rating shows, the credit "IMDb rating: information courtesy of IMDb. Used with permission." sits under it.
- An absent rating is simply omitted; with none at all, the line reads "Not yet rated".
- With IMDb removed (`imdb=off`), nothing else on the page moves.

**Trailer**

- **Click to play.**
    - The cover is the TMDB backdrop, not YouTube's thumbnail, so nothing loads from Google until the visitor presses play.
    - A play button, and a label "{type} · {name}".
    - Pressing play swaps in `https://www.youtube-nocookie.com/embed/{key}?rel=0&autoplay=1` with `referrerpolicy="strict-origin-when-cross-origin"`, at least 200×200 and with no overlays.
    - Rejected: an inline embed on page load, which contacts Google on every view and loads YouTube's heavy player whether or not anyone presses play.
- **Caption:** "Plays from YouTube. YouTube may set cookies once you press play."
- **"Watch on YouTube" link:** always present, in a new tab with `rel="noopener noreferrer"`. TMDB doesn't say which videos allow embedding, so a blocked embed can't be detected in advance; the link is the fallback.
- **No trailer:** the section is hidden.
- **Which trailer:**
    - Among official YouTube Trailer-type videos in the requested languages, take the latest one published on or before the primary release date. If all were published after it, take the earliest.
    - Teasers are used only when there is no Trailer.
    - This replaces the "earliest `published_at`" tiebreak in [TMDB API coverage for MVP features](./02-tmdb-api-coverage.md).
    - Why: for *Interstellar* the old rule picks "Teaser Trailer" (2013-12-14); the new one picks "Trailer 4" (2014-10-01), the final theatrical trailer, and skips later re-release trailers such as the 2024 IMAX one.

**Where to watch (the tabs design)**

- The frame from [Country and Availability presentation](./11-country-and-availability.md):
    - the "Where to watch" heading and a "Showing {country} ▾" selector;
    - the unsupported-country notice and the `stale` notice;
    - the "not listed doesn't mean unavailable" caveat;
    - "Streaming data from JustWatch · Updated N days ago";
    - "See all watch options on TMDB" (new tab).
- **Tabs:**
    - Stream, Free, Rent and Buy, each with its count;
    - empty groups hidden, and the first non-empty group selected;
    - the Free panel is captioned "Free, may include ads".
- **Entries:**
    - logo and name rows, not clickable;
    - shown as JustWatch reports them, in TMDB's `display_priority` order.
- **Channel variants are not merged.** Paramount Plus Premium, Paramount Plus Essential and Paramount+ Amazon Channel stay separate entries. Many are genuinely different plans or ways to subscribe, and an alias list would go stale and misstate credited data. Merging them is a follow-up for P1 Streaming service preferences.
- **Empty:** "We don't have streaming information for this movie in {country}.", with the caveat, credit and TMDB link still shown.
- **Keyboard:** the tabs follow the WAI-ARIA tabs pattern, including arrow-key movement between tabs. The prototype lacks the arrow keys; the build must add them.

**Actions (in the hero)** These extend the detail-page row in [Watchlist interactions](./13-watchlist-interactions.md).

| User's state | Shown |
| --- | --- |
| Neither | "Add to Watchlist" (accent) and "Mark as watched" |
| On the Watchlist | "On your Watchlist" (pressed) and "Mark as watched" |
| Watched, not on the Watchlist | the Watchlist toggle and a "Watched {date}" chip with a Clear (×) button |
| Watched and on the Watchlist (a rewatch queued) | "On your Watchlist" and "Mark as watched", which moves the Watched date to today and takes the Movie off the Watchlist, plus a quiet "Last watched {date} · Clear" line |

Guests see the same controls, and using one starts the pending-action sign-in flow. Every action shows the toast with Undo.

**States**

- **Loading.**
    - The page arrives in one response, with no deferred props, so the server-rendered HTML carries the cast and the Director for the `Movie` JSON-LD.
    - Shimmer skeletons shaped like the hero, body and right column cover any client-side wait.
- **`stale`:** the page renders normally, and Where to watch shows the "may be out of date" notice.
- **Source unavailable, row present:**
    - Everything stored on the row renders.
    - Trailer and Cast show "temporarily unavailable" panels.
    - Director, Writers and Rated read "Temporarily unavailable", and the certification badge is hidden.
- **Sparse:** no backdrop means a blurred-poster hero; no tagline; "Not yet rated"; no Trailer section; the Availability empty state; a short cast.
- **Dropped Movie (shell): HTTP 410 for everyone, with `noindex`.**
    - "This movie is no longer available. Our data source, TMDB, has removed this movie, so we can't show its details any more."
    - No title: every TMDB field has been cleared.
    - A signed-in user with a fact on the Movie also sees "It's still in your lists": "On your Watchlist" with Remove, and "Watched {date}" with Clear. These use the normal toast with Undo.
    - Links to Discover, plus "Your Watchlist" when signed in.

**Rejected**

- Variant B, "Poster column, no hero", and variant C, "Trailer first, one column".
- Where to watch:
    - logo tiles: names hidden behind hover;
    - a full-width band under the hero: pushes the overview far down on mobile;
    - one row per service;
    - a hero summary strip with the full list last: the finalist that lost to tabs.

**Carry into the spec**

- **[Images and performance budget](./27-images-and-performance.md):**
    - The LCP element is the hero backdrop: `w1280`, `fetchpriority="high"`.
    - The glow reuses the same URL, so there is one download.
    - Logos are `w92`; profile photos are `w185`, lazy.
- **[Rate limiting and security headers](./28-rate-limits-and-security.md):**
    - CSP needs `frame-src https://www.youtube-nocookie.com` and `img-src https://image.tmdb.org`.
    - The Referrer-Policy must stay `strict-origin-when-cross-origin`, because YouTube embeds need the Referer.
- **[Privacy, terms and retention](./30-privacy-terms-and-retention.md):** say that YouTube may set cookies once a visitor presses play, and that nothing loads from YouTube before that.
- **Build gotchas from the prototype:**
    - Move Where to watch for mobile with CSS (grid placement or `order`), not a JavaScript media query. Under SSR a JS query renders the desktop placement and causes a hydration mismatch.
    - The sticky column's measured `top` is client-only; its server-rendered default is 1.5rem.
