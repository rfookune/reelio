# TMDB fixtures

Real TMDB responses, recorded with `language=en-US` and saved pretty-printed. Tests replay them through `Http::fake()`; nothing here is hand-written. Re-record rather than edit.

## Movies

`movie_157336` (Interstellar, with `credits,videos,release_dates,watch/providers` appended), `external_ids_157336`, `search_inter`, `trending_p2`, `upcoming_us`.

## TV Shows (recorded 2026-11-08)

Details were fetched with `append_to_response=external_ids,content_ratings,videos,credits,watch/providers` and `include_video_language=en,null`. Season responses are plain `tv/{id}/season/{n}`.

| Fixture | What it is | Why it is here |
| --- | --- | --- |
| `tv_153312` plus `tv_153312_season_0` to `_4` | Tulsa King, a Returning Series | Season 0 (one Special), four numbered Seasons, and Season 4 with six Episodes that had not aired yet |
| `tv_87108` plus `tv_87108_season_1` | Chernobyl, an Ended mini-series | A small Ended TV Show with no Season 0 |
| `tv_95396` plus `tv_95396_season_3` | Severance, a Returning Series | An announced Season 3 whose Season response has no Episodes |
| `search_multi_tulsa` | `search/multi` for "tulsa" | `tv`, `movie` and `person` results in one response |

The dates inside the Tulsa King fixture are as of the recording date: Season 4 episodes 5 to 10 are in the future then. A test needing "unaired" Episodes should compare against a fixed clock set before 2026-11-08, not the real date.

No recorded Episode has a null `air_date`. A test for an undated Episode builds one by nulling the date in a copy of a recorded Season.

Findings from recording: a Season request accepts `append_to_response` (`external_ids,watch/providers` returned 200 with both keys), so Season-level appends work.
