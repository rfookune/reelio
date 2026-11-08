# 01: Record TMDB TV fixtures

**What to build:** Record real TMDB responses for TV Shows so every later ticket tests against recorded data, never invented JSON. One returning TV Show fetched with `external_ids`, `content_ratings`, `videos`, `credits` and `watch/providers` appended, with every Season response (including a Season 0 of Specials and at least one unaired or undated Episode), and one small Ended TV Show. Also record a `search/multi` response mixing movies and TV Shows. The fixtures sit beside the existing Movie fixtures and are described in a short note there. Needs the owner's TMDB token, so this is a human task.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** None (can start immediately)

**Status:** ready-for-human

- [ ] The returning TV Show fixture holds the details with all five appended sub-requests, plus one response per Season
- [ ] A Season 0 response and an unaired or undated Episode are present in the fixtures
- [ ] A small Ended TV Show fixture is recorded
- [ ] A `search/multi` fixture with both `movie` and `tv` results is recorded
- [ ] No token or personal data appears in any fixture
