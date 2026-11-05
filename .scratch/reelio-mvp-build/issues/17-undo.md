# 17: Undo

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Every Watchlist and Watched action can be undone from its toast, restoring exactly the earlier state, as long as nothing has changed since. See the spec's Undo bullets.

**Blocked by:** [16: Mark as watched and Clear Watched](./16-mark-as-watched-and-clear-watched.md)

**Status:** ready-for-agent

- [ ] Each action stores the Movie's previous state (both facts and dates, by catalog id) in the session; only the latest action is kept
- [ ] An Undo endpoint (path set at build) restores the snapshot only if the current state still matches what the action produced, including restoring no row at all or re-creating a purged Movie row by catalog id; otherwise it fails with "That can't be undone any more"; the client never supplies dates
- [ ] Toasts offer a keyboard-reachable Undo; Undo restores focus to the restored control
- [ ] `user_movie_action_undone` with `movie_id` and `action`; the original event stays
- [ ] Browser tests cover the toast Undo flow; feature tests cover restore, mismatch and the previous Watched date
