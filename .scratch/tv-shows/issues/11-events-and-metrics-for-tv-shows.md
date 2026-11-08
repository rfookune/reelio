# Events and metrics for TV Show actions

Type: grilling
Blocked by: 08
Status: resolved

## Question

Which analytics events do TV Show actions record (Watchlist add and remove, Episode Watched and cleared, Season and TV Show bulk actions, Undo, the Watch link click), with what properties, and how do they relate to the existing Movie events? How does the `metrics:report` treat TV Shows (separately from Movies or combined), and do the events survive the 13-month roll-up unchanged?

## Answer

Settled in a grilling session; no new terms and no ADR. Events are signed-in only, recorded after the response, one event per user action (never per Episode).

**Naming:** Movie events keep their names, properties and meaning, so Movie metrics and tests are untouched. TV Shows get parallel `tv_show_*`, `season_*` and `episode_*` events. Only two events are kind-neutral, because one action serves both kinds: search and the Watch link click. Nothing is live, so those two renames are internal.

| Event                                                | Properties                                                                                   |
| ---------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| `tv_show_viewed`                                     | `tv_show_id`, `referrer` (same values as `movie_viewed`, without `picker`)                   |
| `tv_show_watchlisted`, `tv_show_unwatchlisted`       | `tv_show_id`, `source`                                                                       |
| `episode_marked_watched`, `episode_unmarked_watched` | `tv_show_id`, `season_id`, `episode_id`, `source`, `was_on_watchlist`                        |
| `season_marked_watched`, `season_unmarked_watched`   | `tv_show_id`, `season_id`, `episode_count` (Episodes changed)                                |
| `tv_show_marked_watched`, `tv_show_unmarked_watched` | `tv_show_id`, `source`, `episode_count`                                                      |
| `tv_show_completed`                                  | `tv_show_id` (when the derived state becomes Completed and the TV Show leaves the Watchlist) |
| `tv_show_action_undone`                              | `tv_show_id`, `action`, plus `season_id` or `episode_id` when the undone action had one      |
| `title_searched` (was `movie_searched`)              | `query_length`, `result_count` (all results shown on page 1, both kinds; no text)            |
| `availability_link_clicked`                          | `title_id`, `kind` (`movie` or `tv_show`), `country`; recorded by the redirect route         |

- **Ids:** `season_id` and `episode_id` are local ids and plain integers, with no foreign key, so a purged Season or Episode never blocks anything. Season events carry their `season_id`; whole-TV Show events carry no Season or Episode ids (they could list hundreds), only `episode_count`.
- **Not recorded:** no view event on the Removed Title 410 or the cap-cleared 503. With `catalog.tv_shows.enabled` off no TV events occur. A guest's replayed pending action records on replay, as for Movies.

**`metrics:report`:** the existing metrics count both kinds so a TV-only user is not invisible. Weekly returning activates on a first `movie_watchlisted` or `tv_show_watchlisted` ("returning" already means any event). Search-to-watchlist counts viewers from `movie_viewed` or `tv_show_viewed` and conversion by either kind's Watchlist event. Picker usage is unchanged and Movies-only. A new "TV Shows" block shows, per window: TV Shows watchlisted, users who marked at least one Episode, Episode marks per marking user, and TV Shows completed. The SQL stays in the one documented place.

**Roll-up:** unchanged. One row per day and event name with a count and no properties; no TV event gets a stored-totals exception, so `episode_count` is not summed after the roll-up.
