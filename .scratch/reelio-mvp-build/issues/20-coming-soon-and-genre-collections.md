# 20: Coming soon and genre collections

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Discover gains Coming soon with real US opening dates and three genre collections that rotate daily. See the spec's Discover table rows 3 and 5–7.

**Blocked by:** [19: Discover homepage](./19-discover-homepage.md)

**Status:** ready-for-agent

- [ ] Coming soon uses `discover` with `region=US`, theatrical and digital release types, US release dates from tomorrow to 60 days out; each Movie's US date comes from its `release_dates` (fetched with the payload) and shows as "Opens 21 Nov"; past or missing US dates are dropped
- [ ] Three genre collections are picked each day, deterministically from the date, from the config list (Action, Comedy, Drama, Science Fiction, Horror, Animation, Thriller, Romance), each using `discover` by popularity with at least 300 votes
- [ ] Both join the cross-section dedupe and failure rules, in the order Trending, Popular, Coming soon, Top rated, genres
- [ ] Feature tests cover the date window, dropped dates, the deterministic rotation and dedupe
