# 08: Mark Episodes as watched

**What to build:** A signed-in user can record Watched progress on the TV Show page. Build the Season accordion (each Season collapsible with 'n of m watched', a progress bar and the default-open rule), compact Episode rows (number, Name, air date, runtime, a round toggle with `aria-pressed`, the overview behind a disclosure), the Specials section (last, collapsed, labelled), and the unaired and undated rules. The Episode toggle marks and unmarks with the derived TV Show state (Not started, In progress, Completed) and the header progress; it is optimistic with rollback and a message on failure. Marking again moves the Watched date to now. A guest's tap becomes their pending action and is replayed after sign-in. Undo (one endpoint by TV Show TMDB id) restores the exact prior Episode state, refusing with 'That can't be undone any more' when things changed.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 06 TV Show page content

**Status:** ready-for-agent

- [ ] Nested Episode endpoints key by local ids, with scoped bindings 404ing an Episode outside its Season or TV Show and removed ones; unaired and undated Episodes are rejected
- [ ] The derived state ignores Specials, unaired, undated and removed Episodes
- [ ] The header shows 'n of m Episodes watched' with a progress bar
- [ ] The default open Season follows the spec (first with an unwatched aired Episode, else last aired; Season 1 for Not started)
- [ ] A guest's Episode action replays after sign-in and nothing is saved before
- [ ] Undo restores exactly and refuses when state has changed
- [ ] Browser tests cover the optimistic toggle with rollback on a failing endpoint
