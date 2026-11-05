# 28: Auth rate limits and the 429 experience

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Authentication can't be abused, account existence isn't leaked, and anyone going too fast gets one clear message wherever they are. See the spec's "Security" rate-limit table.

**Blocked by:** [15: Save to the Watchlist](./15-save-to-the-watchlist.md), [21: Search page](./21-search-page.md), [25: Movie picker](./25-movie-picker.md)

**Status:** ready-for-agent

- [ ] Limits keyed by user id when signed in, IP for guests: registration 10/hour per IP, forgot-password 5/min per IP (the broker's per-email throttle stays), reset-password 5/min per IP, confirm-password and account deletion 5/min per user, profile update 6/min per user; Fortify's existing limits unchanged
- [ ] Forgot-password always says "If that email has an account, we've sent a link"
- [ ] Every 429 carries `Retry-After` and "You're going too fast. Try again in a moment.": in the error toast (with rollback) for actions and Undo, inline (and announced) on Search and in the picker, and as a styled 429 page for full visits
- [ ] Feature tests: the request after each limit gets a 429 with `Retry-After`; the non-enumerating response
