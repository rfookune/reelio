# 07: Where to watch and ratings for TV Shows

**What to build:** Show TV Show-level Availability in the Where to watch block (same layout, country rules, offer types, JustWatch credit and freshness notices as a Movie, driven by the TV Show's one `synced_at`) and the Watch redirect to TMDB's TV watch page with its click event and signed-in-only rule. Show IMDb then TMDB ratings with vote counts and credits on the page; the TV Show's IMDb id comes from the appended `external_ids`. IMDb for TV Shows rests on the existing dataset permission, an assumption flagged for re-confirmation before launch; the existing source switch hides it.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 05 A TV Show page appears behind the flag

**Status:** resolved

- [x] Availability rows are stored per Title and replaced wholesale on refresh
- [x] Where to watch shows 'Updated n days ago' and the 'may be out of date' notice from the TV Show's `synced_at`
- [x] The Watch redirect goes to TMDB's TV watch page; it is `noindex` and `nofollow`
- [x] The page shows IMDb then TMDB with vote counts and credits; 'Not yet rated' when neither exists
- [x] Switching IMDb off hides it for TV Shows too
- [x] No Season-level or Episode-level Availability exists

## Comments

### 2026-11-08 — Implemented

- Connected TV Show-level stored Availability to the shared Where to watch component, including country preferences, offer types, JustWatch credit, calendar-day freshness and stale notices from the TV Show's one `synced_at`. Refresh replacement stays atomic and Title-level.
- Added the flag-gated `/tv-shows/tmdb/{tmdbId}/watch` redirect with supported-country validation, the shared entry creation budget, catalog failure handling and `noindex, nofollow`. Cards without a local row work through the existing TV catalog entry path. Only signed-in clicks record `availability_link_clicked` with `title_id`, `kind` and `country`.
- Displayed IMDb then TMDB ratings with vote counts, credits and the existing empty state. The existing IMDb source switch applies. IMDb permission for TV Shows remains a pre-launch re-confirmation in the parent spec.
- The shared Where to watch component accepts a Wayfinder-generated Watch URL; Movie URLs and behavior stay the same.
- Standards and Spec reviews found no remaining issues after correcting the TV Watch identifier and splitting guest/signed-in tests. Added feature coverage for refresh replacement, countries, freshness, outages, partial reloads, redirects, flags, creation limits and ratings, plus rendered-page coverage for credits and empty states.
- Validation: `composer ci:check` passed all 807 Unit/Feature tests, formatting, linting, Vue typechecking and PHPStan. All 27 app JavaScript tests and 162 browser tests passed; client and SSR builds passed. An overlapping CI/browser run interfered through shared PostgreSQL/Redis state; the final CI run ran after the browser process finished and passed.
