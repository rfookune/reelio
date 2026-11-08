# 10: Season and whole-TV Show bulk actions

**What to build:** Add bulk marking. Mark and clear a whole Season (skipping unaired, undated and already-watched Episodes so earlier dates stay), mark a whole TV Show (aired Episodes only) through a modal confirm dialog that names the number of aired Episodes (focus trapped and returned, Escape and Cancel change nothing), and a Clear for the whole TV Show (the Completed chip's control). Completion removes the TV Show from the Watchlist and it never returns on its own. Each bulk action is exactly one Undo, restoring the affected Episodes' previous Watched dates plus the Watchlist fact, with no size cap. Whole-TV Show mark with no Episodes stored first tries a refresh and changes nothing if that fails. A guest's bulk action is stored as the pending action (the confirm dialog shows first).

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** 08 Mark Episodes as watched, 09 Watchlist for TV Shows

**Status:** ready-for-agent

- [ ] Season mark and clear change only eligible Episodes; earlier Watched dates are kept
- [ ] The whole-TV Show mark requires the confirm dialog naming the aired Episode count
- [ ] Becoming Completed removes the TV Show from the Watchlist; later new Episodes do not re-add it
- [ ] One Undo restores Episode marks and Watchlist membership exactly, and fails when state has changed
- [ ] Whole-TV Show mark with no Episodes stored refreshes first and changes nothing on failure
- [ ] Guest replay works for Season and whole-TV Show actions
- [ ] Browser tests cover the Undo toast after a Season bulk action and the dialog's focus behaviour
