# 06: Open a Movie from its TMDB id

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** The Catalog tracer bullet: following a link to `/movies/tmdb/{tmdbId}` confirms the Movie with TMDB, creates its row, and 301-redirects to a minimal Movie page at `/movies/{slug}-{id}`. It establishes the Catalog source seam (TMDB adapter plus in-memory fake) and the `Catalog` module every later ticket goes through. See the spec's "Modules", "Data model", "Movie identity and URLs" and "Catalog" sections.

**Blocked by:** [01: Postgres and Redis test foundation](./01-postgres-redis-test-foundation.md), [02: App shell and visual language](./02-app-shell-and-visual-language.md), [05: Analytics event recording](./05-analytics-event-recording.md)

**Status:** ready-for-agent

- [ ] A neutral Catalog adapter interface (starting with `movie(tmdbId)` returning `MovieDetails`, with a `CatalogContext` for language and region), a TMDB adapter (Bearer token, `en-US` from config, `include_adult=false`, the single appended detail call) and an in-memory fake that can return given Movies or fail; nothing outside the adapter sees TMDB JSON or says "provider"
- [ ] The recorded fixtures from the map's research folder move into the test suite; the adapter is tested with `Http::fake()` against them
- [ ] A deep `Catalog` module is the only thing controllers call; cache keys come from one builder (`catalog:v1:{kind}:{params-hash}:{language}:{region}`)
- [ ] `movies` (unique `tmdb_id`, slug, the spec's metadata columns, all TMDB-derived columns nullable, `synced_at`, `last_viewed_at`, a removed marker), `genres` and `genre_movie` tables
- [ ] The entry route redirects to an existing row; otherwise TMDB confirms the id before a row is created; an unknown id is a 404; creation is limited to 20/min per user or IP, counting only requests that need the TMDB check
- [ ] `/movies/{slug}-{id}` renders a minimal Movie page (title, year, overview, poster); a wrong or stale slug 301s to the canonical URL; `last_viewed_at` is set on creation and each view
- [ ] A TMDB image component builds URLs from the configured base and a validated path (`^/[A-Za-z0-9_-]+\.(jpg|png|svg)$`), with `srcset`/`sizes`, a fixed-ratio box filled with the raised colour, and fallback to the missing-poster tile
- [ ] `movie_viewed` with `movie_id` and `referrer` (`discover`, `search`, `watchlist`, `watched`, `picker`, `direct`) is recorded for signed-in users
- [ ] Feature tests use the fake adapter and cover creation, redirects, 404, the limit and the event
