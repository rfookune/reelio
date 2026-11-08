# 06: TV Show page content

**What to build:** Fill in the TV Show page beside the Season accordion (built later): the hero with Name, original Name when different, year range, Season count, US content rating, genres and External ratings slot; the sticky Details panel (Created by, Network, Status, First aired, Last aired, Seasons and Episodes counts counting only numbered Seasons and aired Episodes, Rated, Genres, Original title); overview and tagline; click-to-play Trailer; the top-12 Cast with no full-cast page. Also the SEO basics (title, description, Open Graph `video.tv_show`, share image, `TVSeries` JSON-LD) and the Movie-style states: one response with no skeletons, stale, and source-unavailable with 'temporarily unavailable' panels. The right column is sticky at the top on desktop and stacks below `lg`.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 05 A TV Show page appears behind the flag

**Status:** resolved

- [x] Hero, Details panel, overview, Trailer and Cast render from stored and appended data
- [x] Counts exclude Specials and unaired Episodes; no next-episode date is shown
- [x] SEO tags and `TVSeries` JSON-LD are present and correct
- [x] A source-unavailable stored TV Show shows the 'temporarily unavailable' panels and hides the certification badge
- [x] No Episode stills are shown anywhere
- [x] Feature tests cover each state

## Comments

### 2026-11-08 — Implemented

- Expanded the TV Show page with the shared backdrop/poster hero, original Name, year range, aired Season count, US rating badge, genres, overview/tagline, click-to-play Trailer and top-12 Cast. The Details column is sticky on desktop and stacks below the overview on smaller screens. No TV full-cast link, next-episode date or Episode stills are exposed.
- Added context-keyed, expiring page-appends caching for creators, networks, certification, Trailer and Cast. Missing appends are refetched even on a recently synced Title. A failed refresh uses stale cached appends when available; otherwise the stored facts remain, unavailable panels appear and the certification badge is hidden. Removal deletes cached appends.
- Counts include only dated, aired, nonremoved Episodes in numbered, nonremoved Seasons, and Seasons containing those Episodes.
- Added TV SSR/indexing eligibility, the requested title and description, canonical/share tags, `video.tv_show` and safely serialized `TVSeries` JSON-LD.
- Standards and spec reviews completed with no remaining actionable findings; the suggested cache-removal coverage was added and passed. Desktop and mobile screenshots were inspected.
- Validation: `composer ci:check` passed all 775 unit/feature tests plus formatting, linting, Vue typechecking and PHPStan. All 25 app JavaScript tests passed, and client/SSR builds passed. The final full browser run passed all 162 tests (an earlier run had three transient timeouts in existing Search/Undo/Watchlist tests, all of which passed on rerun).

Where to watch and actual External rating integration remain in ticket 07; the hero retains an empty ratings extension slot. Season accordion and Watched controls remain in ticket 08.
