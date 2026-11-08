# Availability and External ratings for TV Shows

Type: grilling
Blocked by: 02
Status: resolved

## Question

At which level (TV Show, Season, Episode) does Reelio show Availability, using which attributions? Which Rating sources apply to a TV Show, and do they fit the existing ratings table and display rules?

## Answer

Settled in a grilling session; no new terms and no ADR (the choices follow ADR 0001 and ADR 0002). The glossary entries for Rating source, External rating and Availability now cover TV Shows.

**Availability**
- Shown at the TV Show level only: one Where to watch block with the same layout, country rules, offer types and JustWatch credit as a Movie. Nothing per Season or Episode; Season-level Availability is out of scope.
- `availabilities` and `streaming_services` follow the shared Title base (`title_id`). Rows are replaced wholesale on each full TV Show refresh. The TV Show's single `synced_at` drives "Updated n days ago" and the 7-day "may be out of date" notice.
- The Watch redirect route builds TMDB's TV watch URL for a TV Show; the click event, the signed-in-only rule and the `noindex`/`nofollow` rules are unchanged.

**External ratings**
- One `ratings` table, key unchanged. The TMDB source's id mapping produces a kind-prefixed `source_id` (`movie:550`, `tv:1399`) because TMDB ids are only unique within a kind; IMDb keeps its `tt` id, already unique across kinds. No new column; the migrations are edited in place.
- The TV Show page shows IMDb then TMDB with vote counts and credits, as for a Movie. Cards show one rating (IMDb if known, else TMDB, else "Not yet rated"). Seasons and Episodes show no ratings.
- A TV Show's `imdb_id` comes from `external_ids` appended to the details call (no extra request). Search and Trending cards for TV Shows use the same after-render IMDb lookup as Movies, extended to TV ids. The daily IMDb dataset sync is unchanged: the file already contains TV series.
- The 6-month cap, the purge and `catalog:purge` clear TMDB rating rows of both kinds.

**Assumption to flag in the spec**
- IMDb's permission is for the non-commercial ratings dataset, which contains TV series. IMDb scores for TV Shows ship on the same permission and credit wording; re-confirm with IMDb before launch. `ratings.sources.imdb.enabled` hides IMDb everywhere if permission is withdrawn.
