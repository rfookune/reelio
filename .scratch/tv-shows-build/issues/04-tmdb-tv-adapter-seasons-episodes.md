# 04: TMDB TV adapter, Seasons and Episodes

**What to build:** Add the Season and Episode tables (matched by TMDB id; Season: number, name, overview, poster path, air date, episode count; Episode: number, name, overview, air date, runtime, `episode_type`; a removed marker on both) and teach the TMDB adapter to fetch a whole TV Show: details with the appended sub-requests, plus every Season, concurrently, all-or-nothing. The adapter returns neutral objects (no TMDB JSON or the word 'provider' outside it). Extend the Catalog fake with TV Shows, Seasons and Episodes and per-call failure, including a single failing Season call. Verified entirely by adapter tests against the recorded fixtures.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 01 Record TMDB TV fixtures, 02 Movie becomes Title, no visible change

**Status:** resolved

- [x] Seasons and Episodes tables exist, hang off a Title and are keyed by TMDB id
- [x] The adapter parses Season 0 as Specials, unaired and undated Episodes, `episode_type`, US content rating, creators, network, status, `external_ids` and TV Availability (`free` and `ads` become `free`)
- [x] A 404 on the details call is reported as a removed TV Show
- [x] One failing Season call fails the whole fetch and writes nothing
- [x] The Catalog fake can return TV Shows and fail per call or for every call
- [x] No still image path is stored

## Comments

### 2026-11-08 — Implemented

Committed as `a2261bf` (Add complete TMDB TV fetches with Seasons and Episodes).

- Added Season and Episode tables, models, relationships, factories and seeders, with TMDB identity and removal markers. No Episode still path is stored.
- Added neutral TV Show, Season and Episode data objects and a complete TMDB TV fetch: appended details followed by concurrent Season requests under one shared interactive timeout. A details 404 returns the removed-TV-Show result; any failed or incomplete response rejects the entire fetch.
- Extended the Catalog fake and guard for TV Shows, including per-TV-Show and per-Season failures and failure of every call.
- Standards and spec reviews completed; no remaining material findings after completeness and regression-coverage fixes.
- Validation: `composer ci:check` passed, including frontend checks, Vue typechecking, Pint, PHPStan and all 746 unit/feature tests. All 161 browser tests passed before the final adapter-only review fixes; affected tests and the full CI checks were rerun after those fixes.

TV page and refresh/write orchestration remain in ticket 05.
