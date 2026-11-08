# 11: Search with TV Shows

**What to build:** Search results include TV Shows beside Movies in one poster grid with All / Movies / TV Shows chips and counts (client-side over the one grid). A TV Show card shows the 'TV · n Seasons' badge, Name, first-air year and one rating (IMDb if known, else TMDB, else 'Not yet rated'), linking through the entry route. The after-render IMDb lookup is extended to TV ids and cards update without a reload. With the flag off, TV results are dropped.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 03 Kind-aware TMDB rating ids, 05 A TV Show page appears behind the flag

**Status:** resolved

- [x] Search returns both kinds, told apart by `media_type`, with the right Name and date fields for each
- [x] Chips filter the grid and show counts; All is the default
- [x] TV cards link to the entry route (or the canonical URL when the row exists)
- [x] The IMDb lookup resolves TV ids and fills cards in after render
- [x] With the flag off, no TV results appear
- [x] Existing Movie search tests stay green

## Comments

### 2026-11-08 — Implemented

- With the flag on, search reads TMDB `search/multi` and keeps Movies and TV Shows by `media_type` (people, adult and repeats dropped; Name and date fields mapped per kind onto `MovieListing`, which now carries a `kind`). With the flag off it stays on `search/movie`. The search cache key names "every kind" only while the flag is on, `SearchResults` round-trips `kind` (an old cached page reads as Movies), and `SearchPage` drops TV listings when the flag is off even if a source or cache supplies them.
- Cards carry `kind` and a `key` (catalog id for a Movie, `tv:<id>` for a TV Show) used for the infinite-scroll merge, so a Movie and a TV Show sharing a TMDB id never collide. TV cards link to the canonical URL when the row exists, else `tv-shows.entry`, and show one rating (IMDb if known, else TMDB, else "Not yet rated"). TV cards have no Movie Watchlist toggle and `userMovies` covers Movies only.
- The IMDb lookup is kind-aware: new `CatalogSource::tvImdbId()` (TMDB `tv/{id}/external_ids`), remembered ids keyed by kind for TV only (Movie keys unchanged), TMDB ratings recorded under `TmdbRatingId::for($kind, …)`. The ratings response keys TV Shows as `tv:<id>` and Movies as before, so the Movie response is unchanged.
- UI: All / Movies / TV Shows chips with counts over the loaded cards, shown only while TV Shows are searched; a TV badge on the poster. Pure helpers in `resources/js/lib/searchKinds.ts`.
- Tests: TMDB multi parsing and `tvImdbId`, cache round-trip and flag keying, kind-separate ratings and IMDb ids, mixed result shape, canonical link and season count, id collision, flag off, TV ratings lookup, helper unit tests, and a browser test for chips and the late TV IMDb fill-in. Existing Movie tests changed only where the shape grew (`kind`, `key`, `matchOn`) or to set the flag off for the Movie-only source tests.
- Validation: 1058 feature/browser tests, 31 frontend tests, type check, PHPStan, Pint and `vp check` on changed files passed. After the review fix, the Search feature, browser and accessibility tests were rerun, not the full suite. Committed as `f966223`.
- Known limitations: TMDB search carries no season count, so a TV Show with no row yet shows a bare "TV" badge until its row exists. The result-count heading and announcement use TMDB's multi-search total, which includes people, so they can read higher than the cards shown; chip counts cover only loaded cards. A query matching only people shows the no-results message even if later pages hold titles; this fix has no test. The IMDb lookup's `max_per_request` and `budget_seconds` now apply per kind. Review smells left alone: per-kind branching in `SearchPage` and the parallel `tvImdbId()` method.
