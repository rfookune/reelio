# 04: TMDB TV adapter, Seasons and Episodes

**What to build:** Add the Season and Episode tables (matched by TMDB id; Season: number, name, overview, poster path, air date, episode count; Episode: number, name, overview, air date, runtime, `episode_type`; a removed marker on both) and teach the TMDB adapter to fetch a whole TV Show: details with the appended sub-requests, plus every Season, concurrently, all-or-nothing. The adapter returns neutral objects (no TMDB JSON or the word 'provider' outside it). Extend the Catalog fake with TV Shows, Seasons and Episodes and per-call failure, including a single failing Season call. Verified entirely by adapter tests against the recorded fixtures.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 01 Record TMDB TV fixtures, 02 Movie becomes Title, no visible change

**Status:** ready-for-agent

- [ ] Seasons and Episodes tables exist, hang off a Title and are keyed by TMDB id
- [ ] The adapter parses Season 0 as Specials, unaired and undated Episodes, `episode_type`, US content rating, creators, network, status, `external_ids` and TV Availability (`free` and `ads` become `free`)
- [ ] A 404 on the details call is reported as a removed TV Show
- [ ] One failing Season call fails the whole fetch and writes nothing
- [ ] The Catalog fake can return TV Shows and fail per call or for every call
- [ ] No still image path is stored
