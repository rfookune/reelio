# Reelio MVP

Label: wayfinder:map

## Destination

A locked, implementation-ready spec for the Reelio MVP: every open decision settled, the domain model named in `GLOSSARY.md`, and the module and data design set out, ready for `/to-spec` → `/to-tickets`. P1/P2 features are listed in the spec as follow-ups, not designed.

## Notes

- **Source PRD:** [prd.md](./prd.md) (v1.0, 2026-11-04). It is the guideline, not gospel: decisions recorded on this map override it.
- **Scope:** PRD P0 plus its §17 "Recommended Initial Decisions" (auth required to save; TMDB as Catalog source; US-first, configurable country; picker limited to unwatched watchlist; personal ratings deferred; "Reelio" is a working name; free, no billing). P1/P2 features are recorded as spec follow-ups only.
- **Scope change from PRD:** IMDb, Rotten Tomatoes and Metacritic ratings (FR-023, P2 in the PRD) are **in the MVP**, sourced from OMDb unless TMDB turns out to provide them, and subject to licensing (see [OMDb ratings: coverage, terms and limits](./issues/03-omdb-ratings.md)).
- **Audience:** a real public beta, so licensing, attribution, brand, and production concerns are on the map.
- **Stack baseline:** the existing starter kit (Laravel 13, Inertia v3, Vue 3, Fortify, reka-ui components, Wayfinder routes, Pest 5). Any departure from the PRD's §7.1 table is an explicit decision on this map, not a default. The starter kit's optional 2FA and passkeys stay.
- **Vocabulary:** use `GLOSSARY.md` terms (Catalog source, Rating source, External rating, Streaming service, Availability). Never say "provider" except inside TMDB-specific code.
- **Skills to consult:** `grilling` + `domain-modeling` for every grilling ticket; `codebase-design` for boundary and module tickets; `prototype` + `frontend-design` for UI tickets; `laravel-best-practices`, `inertia-vue-development`, `testing-best-practices` where the ticket touches those areas.
- **TMDB licence constraints (standing):** no revenue of any kind (ads, affiliate links, paid tier) without a paid TMDB agreement; no AI/ML on TMDB data, which constrains the P2/v2 follow-ups; TMDB-derived data must stay separable from user data and be purgeable. See [TMDB terms, attribution and caching limits](./issues/01-tmdb-terms.md).
- **Plan, don't do:** tickets produce decisions, not application code. Prototypes are throwaway.

## Decisions so far

<!-- one line per closed ticket: [title](link): gist -->

- [TMDB terms, attribution and caching limits](./issues/01-tmdb-terms.md): fine for a free beta under TMDB's non-commercial licence. Requires a TMDB logo and notice in the footer and an About page, a JustWatch text credit on every Availability block, refresh or purge within 6 months, hotlinked images only, no AI/ML on TMDB data, and no deep links to Streaming services (only TMDB's watch page). Any revenue needs a paid licence; SEO/"destination site" use is a grey area to confirm in writing.
- [TMDB API coverage for MVP features](./issues/02-tmdb-api-coverage.md): TMDB v3 covers every P0 feature. Homepage from trending, popular, top_rated, upcoming and discover by genre; search via `/search/movie`; the detail page in one `/movie/{id}` call with credits, videos, release_dates and watch/providers appended. Director = crew with job Director; the trailer must be ranked by Reelio. Availability for all countries comes in one response (flatrate/rent/buy plus free/ads) with one TMDB watch-page link per country. Pages capped at 500; trending can't be scoped to a country; ratings are TMDB's own only, with `imdb_id` as the OMDb join key; about 40 req/s, no SLA.

## Not yet specified

- **Homepage curation:** which genre collections appear, how the sections are put together and ordered, and whether to deduplicate across sections (the PRD only requires it within a section). Waits on API coverage.
- **Cache lifetimes and metadata refresh:** concrete TTLs per data kind and how stored Movies are refreshed or purged within TMDB's 6-month cap. Waits on the OMDb terms and the storage decision.
- **Rate limiting and security specifics:** limits for auth, search and outbound API calls, safe handling of third-party URLs, open-redirect protection for outbound links, and security headers (a global `no-referrer` Referrer-Policy breaks YouTube trailer embeds; see the TMDB API coverage findings).
- **Images and performance budget:** poster and backdrop size variants per breakpoint, lazy loading, how the LCP target is checked.
- **Accessibility verification:** how WCAG 2.2 AA is checked (automated checks, manual review).
- **Privacy, terms and retention:** content of the privacy and terms pages, the published retention policy, and third-party data-sharing disclosures.
- **Deployment and operations:** pipeline, environments, backups, health check, log hygiene. Waits on the infrastructure baseline.
- **Spec assembly:** turning the resolved decisions into the final spec and handing off to `/to-tickets`.

## Out of scope

- Designing P1/P2 features (advanced filters, streaming-service preferences, personal ratings, viewing dates, custom lists, stats, export, Google sign-in, related movies). The spec lists them as follow-ups only.
- Building the MVP. That happens from the spec's tickets after this map is done.
