# 07: Movie page layout and details

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** The Movie page gets its designed layout: a backdrop hero with the poster, meta line and title block, then the overview, cast and a sticky Details column on desktop, and a single column in the decided order on mobile. See the spec's "Movie page" section and the prototype it names.

**Blocked by:** [06: Open a Movie from its TMDB id](./06-open-a-movie-from-its-tmdb-id.md)

**Status:** resolved

- [x] The hero: full-bleed backdrop (`<picture>`, `w780` below 768px, `w1280` from 768px, the only `fetchpriority="high"` image) that fades through a CSS mask over a 7rem/10rem band, with a glow reusing the same file; a blurred poster when there's no backdrop
- [x] Title, original title (with `lang`) when it differs, and year · runtime · certification badge · genres; the hero poster only from `sm` and not downloaded below it
- [x] Body from 1024px: tagline, overview and Cast on the left; a sticky right column with the Details card (Director(s), Writers, Released (US) else "Released", Runtime, Rated, Genres, Original title); its `top` is client-measured with a 1.5rem server default so a tall column is never out of reach (implemented as 1.5rem clear of the 4rem sticky top bar, so the default is 5.5rem)
- [x] Below 1024px: one column ordered hero, overview, (Where to watch slot), (Trailer slot), Cast, Details, ordered with CSS, never a JS media query
- [x] Cast: the top 12 billed in a 3/4/6-column grid with name and character, lazy `w185` photos and a person tile fallback
- [x] The adapter returns Director(s), Writers, cast, US certification and US release date (type 3, else earliest type 2); these live in a per-Movie detail cache (30-day hard lifetime, fetched in the request if missing); `tagline` is a nullable `movies` column
- [x] The page arrives in one response with no deferred props; sparse states render (blurred-poster hero, no tagline, short cast)

## Note from the full cast page prototype (2026-11-07)

The full cast page (`/movies/{slug}-{id}/casts`, added after this ticket) was redesigned from the plain photo grid. The alternatives are kept as a primary source on the throwaway branch `prototype/full-cast-page` (three rounds: a filterable list, billing tiers with three Starring and three filtered-list layouts, then the back button and filter toggle each drawn three ways). The chosen design is on main:

- **Billing tiers:** the first 6 billed are Starring, on the same profile cards as the Movie page; the next 24 are Supporting, as small round photos; everyone else is Also appearing, as plain text. A long cast loads about 30 photos, not all of them. The tier sizes are `STARRING_COUNT` and `SUPPORTING_COUNT` in `resources/js/lib/cast.ts`.
- **Filter:** hidden behind a "Filter" button with a key hint, at the right of the title on desktop. ⌘/ on a Mac and Ctrl+/ elsewhere open it (a bare `/` does not), Escape or the ✕ closes it and clears it, returning focus to the button. While it has text, the tiers are replaced by one list of the matches in billing order, each with its tier and the match marked, and "N matches" announced.
- **Back to the movie:** a breadcrumb, "Discover › {title} ({year}) › Full cast".
