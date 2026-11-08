# 17: TV Show Full Cast page

**What to build:** A Full Cast page for TV Shows, mirroring the Movie one (`/movies/{slug}-{id}/casts`): `/tv-shows/{slug}-{id}/casts`, named `tv-shows.cast`, behind the TV flag. The TV Show page's Cast section links to it when the cast has more than the top 12, as the Movie page does with `castCount` and `castUrl`.

The TV Show fetch switches from TMDB `credits` (the latest Season's cast only) to `aggregate_credits` (everyone across the whole run, one request already covered by the existing append). Cast entries carry every role the actor played and their total Episode count, shown on each card like "Dr. Smith · 42 episodes"; actors are ordered by TMDB's `order`. Crew come from the same call, grouped by department-style jobs like the Movie page (Creators are already on the TV Show page). The top-12 Cast on the TV Show page now draws from the same series-wide list.

The page reuses the Movie page's tiers (Starring, Supporting, plain text for the rest), name filter and crew groups; the card caption is the only change. States match the Movie cast page: a stale slug 301s, a Removed TV Show is the title-less 410, a TV Show without details is refreshed before its slug is checked and is a 503 while that fails, a source-unavailable page shows the 'temporarily unavailable' panel, and no view event is recorded. The page carries a canonical tag and no structured data.

No Season or Episode filter and no per-Episode or per-Season cast: TMDB has no single call for it (one request per Season, or per Episode), and per-Episode guest stars are a spoiler channel that conflicts with the spoiler-safe Episode rows.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 06 TV Show page content

**Status:** ready-for-agent

- [ ] `/tv-shows/{slug}-{id}/casts` renders the whole cast in tiers with the name filter and crew groups; with the flag off it 404s
- [ ] A wrong slug 301s; a Removed TV Show is a 410; no details gives a refresh then a 503 on failure; unavailable details show the panel
- [ ] The TV Show page's Cast links to the page only when more than 12 are billed
- [ ] The adapter appends `aggregate_credits`; each cast entry has all roles and a total Episode count, shown as "Role · n episodes" (singular for one); the recorded TV fixtures and the Catalog fake are updated
- [ ] The top-12 Cast on the TV Show page comes from the aggregate list
- [ ] No Season or Episode filter, no per-Episode cast and no Episode stills appear anywhere
- [ ] Feature tests cover the redirect, 410, 404 with the flag off, 503, unavailable and the link threshold; the accessibility sweep includes the page (desktop and mobile, both themes)

## Comments
